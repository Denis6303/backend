<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\ApiCodes;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\OrderIntent;
use App\Models\PaymentMethod;
use App\Services\OrderIntents\OrderIntentPurchaseService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Order Intent
 *
 * Parcours d’achat public : réservation temporaire des billets puis paiement (Yass / Flooz) ou gratuit.
 * Authentification : en-tête `Authorization: Bearer <token>` (utilisateur Passport ou client OAuth, middleware `user_or_client`).
 * Les appels PSP réels passent par `App\Services\Payments\PaymentGatewayRegistry` (codes `yass`, `flooz`).
 */
class OrderIntentController extends Controller
{
    public function __construct(
        protected OrderIntentPurchaseService $purchaseService
    ) {
    }

    /**
     * Créer une intention de commande (réservation temporaire du stock).
     *
     * @bodyParam type string required Type d'achat. Seul `online` est pris en charge pour l'instant. Example: online
     * @bodyParam event_occurrence_id integer required ID de la séance (occurrence). Example: 1
     * @bodyParam tickets object required Quantités par type de billet : clés = ID du type, valeurs = quantités (> 0). Example: {"12":1,"15":2}
     * @bodyParam delivery_method string required Canal d'envoi de la confirmation : `email` ou `sms`. Example: email
     * @bodyParam customer_id integer required ID utilisateur acheteur (doit correspondre au compte authentifié). Example: 42
     * @bodyParam email string Email du client (requis si `delivery_method` = email). Example: acheteur@example.com
     * @bodyParam phone string Téléphone (requis si `delivery_method` = sms). Example: +22890123456
     * @bodyParam coupon_code string optional Code promo. Example: SUMMER2026
     * @bodyParam customer_full_name string optional Nom affiché sur la commande. Example: Jean Dupont
     *
     * @authenticated
     */
    public function store(Request $request, string $version): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'type' => 'required|string|in:online,invitation,print',
            'event_occurrence_id' => 'required|integer|exists:event_occurrences,id',
            'tickets' => 'required|array|min:1',
            'delivery_method' => 'required|string|in:email,sms',
            'customer_id' => 'required|integer|exists:users,id',
            'email' => 'required_if:delivery_method,email|nullable|email',
            'phone' => 'required_if:delivery_method,sms|nullable|string|max:30',
            'coupon_code' => 'nullable|string|max:64',
            'customer_full_name' => 'nullable|string|max:120',
        ]);

        $intent = $this->purchaseService->create($validated);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.order_intent.created'))
            ->withData($this->transformIntent($intent))
            ->build();
    }

    /**
     * Afficher une intention de commande (détail, réservations, montant).
     *
     * @urlParam key string required UUID de l'intention. Example: 550e8400-e29b-41d4-a716-446655440000
     * @queryParam customer_id integer optional Si renseigné, doit correspondre au client de l'intention. Example: 42
     *
     * @authenticated
     */
    public function show(Request $request, string $version, string $key): JsonResponse
    {
        $intent = $this->findIntentByKeyOrFail($key);
        $customerId = $request->query('customer_id') !== null ? (int) $request->query('customer_id') : null;
        $this->purchaseService->assertIntentAccessible($intent, $customerId);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.order_intent.retrieved'))
            ->withData($this->transformIntent($intent->fresh(['occurrence.event', 'discount', 'temporaryReservations.ticketType', 'paymentProvider'])))
            ->build();
    }

    /**
     * Démarrer le paiement (checkout PSP) ou confirmer un achat gratuit.
     *
     * Pour `yass` ou `flooz`, `country`, `operator` et `phone_number` sont requis. Pour un paiement payant, `success_url` et `failure_url` sont requis.
     * Les modes `*-deposit` réservés aux administrateurs authentifiés.
     *
     * @urlParam key string required UUID de l'intention. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam payment_method string required `yass`, `flooz`, `free`, `yass-deposit`, `flooz-deposit`. Example: yass
     * @bodyParam accept_terms boolean required Acceptation des conditions (valeur acceptée par Laravel `accepted`). Example: true
     * @bodyParam success_url string URL de retour succès (obligatoire si le montant est dû). Example: https://app.example.com/order/success
     * @bodyParam failure_url string URL de retour échec (obligatoire si le montant est dû). Example: https://app.example.com/order/failed
     * @bodyParam country string Code pays ISO 3166-1 alpha-2 (requis pour yass/flooz). Example: TG
     * @bodyParam operator string Identifiant opérateur (requis pour yass/flooz). Example: YASS
     * @bodyParam phone_number string Numéro à débiter (requis pour yass/flooz). Example: 90123456
     * @bodyParam customer_id integer optional Si renseigné, doit correspondre au client de l'intention. Example: 42
     *
     * @authenticated
     */
    public function checkout(Request $request, string $version, string $key): JsonResponse
    {
        $intent = $this->findIntentByKeyOrFail($key);
        $customerId = $request->input('customer_id') !== null ? (int) $request->input('customer_id') : null;
        $this->purchaseService->assertIntentAccessible($intent, $customerId);

        if ($intent->status !== 'pending') {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.not_pending'))
                    ->build()
            );
        }

        if ($intent->expired_at && $intent->expired_at->isPast()) {
            $this->purchaseService->releaseStockAndExpire($intent);

            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.expired'))
                    ->build()
            );
        }

        $validated = $this->validateOrFail($request->all(), [
            'payment_method' => 'required|string|in:yass,flooz,free,yass-deposit,flooz-deposit',
            'accept_terms' => 'required|accepted',
            'success_url' => 'nullable|url',
            'failure_url' => 'nullable|url',
            'country' => 'nullable|string|max:2',
            'operator' => 'nullable|string|max:32',
            'phone_number' => 'nullable|string|max:32',
            'customer_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($this->purchaseService->isDepositPayment($validated['payment_method'])) {
            $user = $request->user('api');
            if (! $user) {
                throw new HttpResponseException(
                    ResponseBuilder::asError(ApiCodes::FORBIDDEN)
                        ->withHttpCode(Response::HTTP_FORBIDDEN)
                        ->withMessage(__('messages.order_intent.deposit_requires_user'))
                        ->build()
                );
            }
            if (! $user->is_admin) {
                throw new HttpResponseException(
                    ResponseBuilder::asError(ApiCodes::FORBIDDEN)
                        ->withHttpCode(Response::HTTP_FORBIDDEN)
                        ->withMessage(__('messages.order_intent.deposit_admin_only'))
                        ->build()
                );
            }
        }

        $paymentMethod = $validated['payment_method'];

        if ($paymentMethod !== 'free') {
            if (empty($validated['success_url']) || empty($validated['failure_url'])) {
                throw new HttpResponseException(
                    ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                        ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                        ->withMessage(__('messages.order_intent.urls_required'))
                        ->build()
                );
            }
        }

        $payable = $this->calculatePayableAmount($intent);
        if ($paymentMethod === 'free' && $payable > 0.00001) {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.free_not_zero'))
                    ->build()
            );
        }

        if ($paymentMethod === 'free') {
            return DB::transaction(function () use ($intent, $validated, $paymentMethod) {
                $intent->refresh();
                $meta = (array) ($intent->meta ?? []);
                $meta['payment_method'] = $paymentMethod;
                $pm = PaymentMethod::query()->where('code', 'free')->where('is_active', true)->first();
                $meta['payment_method_id'] = $pm?->id;
                $intent->meta = $meta;
                $intent->save();
                $intent->confirm();

                return ResponseBuilder::asSuccess()
                    ->withMessage(__('messages.order_intent.checkout_started'))
                    ->withData(array_merge($this->transformIntent($intent->fresh(['occurrence.event', 'discount', 'temporaryReservations', 'paymentProvider'])), [
                        'checkout' => [
                            'payment_method' => $paymentMethod,
                            'next_action' => ['type' => 'none', 'message' => 'payment_confirmed'],
                        ],
                    ]))
                    ->build();
            });
        }

        $providerCode = OrderIntentPurchaseService::mapPaymentMethodToProviderCode($paymentMethod);

        $payload = [
            'payment_method' => $paymentMethod,
            'success_url' => $validated['success_url'] ?? null,
            'failure_url' => $validated['failure_url'] ?? null,
            'country' => $validated['country'] ?? null,
            'operator' => $validated['operator'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
        ];

        $provider = $intent->checkout($providerCode, $payload);

        $meta = (array) ($intent->meta ?? []);
        $meta['payment_method'] = $paymentMethod;
        $pm = PaymentMethod::query()->where('code', $paymentMethod)->where('is_active', true)->first();
        $meta['payment_method_id'] = $pm?->id;
        $intent->meta = $meta;
        $intent->save();

        $intent->refresh();

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.order_intent.checkout_started'))
            ->withData(array_merge($this->transformIntent($intent->fresh(['occurrence.event', 'discount', 'temporaryReservations', 'paymentProvider'])), [
                'checkout' => [
                    'payment_method' => $paymentMethod,
                    'provider_code' => $provider->provider_code,
                    'next_action' => $this->buildNextAction($intent, $provider, $payload),
                ],
            ]))
            ->build();
    }

    /**
     * Vérifier le statut du paiement (après redirection PSP ou en polling).
     *
     * @urlParam key string required UUID de l'intention. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam customer_id integer optional Si renseigné, doit correspondre au client de l'intention. Example: 42
     *
     * @authenticated
     */
    public function verify(Request $request, string $version, string $key): JsonResponse
    {
        $intent = $this->findIntentByKeyOrFail($key);
        $customerId = $request->input('customer_id') !== null ? (int) $request->input('customer_id') : null;
        $this->purchaseService->assertIntentAccessible($intent, $customerId);

        if ($intent->status === 'confirmed') {
            return ResponseBuilder::asSuccess()
                ->withMessage(__('messages.order_intent.already_confirmed'))
                ->withData(['paid' => true, 'intent' => $this->transformIntent($intent)])
                ->build();
        }

        if ($intent->expired_at && $intent->expired_at->isPast() && $intent->status === 'pending') {
            $this->purchaseService->releaseStockAndExpire($intent);

            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.expired'))
                    ->build()
            );
        }

        if ($intent->status !== 'processing' && $intent->status !== 'confirming') {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.not_pending_payment'))
                    ->build()
            );
        }

        $paid = $intent->verify();

        if (! $paid) {
            return ResponseBuilder::asSuccess()
                ->withMessage(__('messages.order_intent.verify_pending'))
                ->withData(['paid' => false])
                ->build();
        }

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.order_intent.payment_confirmed'))
            ->withData(['paid' => true, 'intent' => $this->transformIntent($intent->fresh(['occurrence.event', 'discount', 'temporaryReservations', 'paymentProvider']))])
            ->build();
    }

    /**
     * Annuler une intention encore en attente (libère le stock réservé).
     *
     * @urlParam key string required UUID de l'intention. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam customer_id integer optional Si renseigné, doit correspondre au client de l'intention. Example: 42
     *
     * @authenticated
     */
    public function cancel(Request $request, string $version, string $key): JsonResponse
    {
        $intent = $this->findIntentByKeyOrFail($key);
        $customerId = $request->input('customer_id') !== null ? (int) $request->input('customer_id') : null;
        $this->purchaseService->assertIntentAccessible($intent, $customerId);

        if ($intent->status !== 'pending') {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('messages.order_intent.cancel_not_pending'))
                    ->build()
            );
        }

        $this->purchaseService->releaseStockAndExpire($intent);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.order_intent.cancelled'))
            ->withData($this->transformIntent($intent->fresh()))
            ->build();
    }

    protected function findIntentByKeyOrFail(string $key): OrderIntent
    {
        if (! $key || strlen($key) < 36) {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::NOT_FOUND)
                    ->withHttpCode(Response::HTTP_NOT_FOUND)
                    ->withMessage(__('messages.order_intent.not_found'))
                    ->build()
            );
        }

        $intent = OrderIntent::query()->where('key', $key)->first();
        if (! $intent) {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::NOT_FOUND)
                    ->withHttpCode(Response::HTTP_NOT_FOUND)
                    ->withMessage(__('messages.order_intent.not_found'))
                    ->build()
            );
        }

        return $intent;
    }

    /**
     * @return array<string,mixed>
     */
    protected function transformIntent(OrderIntent $intent): array
    {
        $meta = (array) ($intent->meta ?? []);
        $lines = (array) ($meta['lines'] ?? []);

        return [
            'key' => $intent->key,
            'status' => $intent->status,
            'price' => (float) $intent->price,
            'fees' => (float) $intent->fees,
            'currency' => $intent->currency,
            'amount' => $this->calculatePayableAmount($intent),
            'expired_at' => optional($intent->expired_at)?->toIso8601String(),
            'event_occurrence_id' => $intent->event_occurrence_id,
            'delivery_method' => $intent->delivery_method,
            'customer_email' => $intent->customer_email,
            'customer_phone' => $intent->customer_phone,
            'meta' => [
                'type' => $meta['type'] ?? 'online',
                'lines' => $lines,
            ],
            'temporary_reservations' => $intent->temporaryReservations->map(fn ($r) => [
                'ticket_type_id' => $r->ticket_type_id,
                'quantity' => $r->quantity,
                'expires_at' => optional($r->expires_at)?->toIso8601String(),
            ])->values()->all(),
            'occurrence' => $intent->occurrence ? [
                'id' => $intent->occurrence->id,
                'start_date' => optional($intent->occurrence->start_date)?->toIso8601String(),
                'end_date' => optional($intent->occurrence->end_date)?->toIso8601String(),
            ] : null,
        ];
    }

    protected function calculatePayableAmount(OrderIntent $intent): float
    {
        $base = (float) $intent->price;
        /** @var Discount|null $discount */
        $discount = $intent->discount;
        if (! $discount || ! $discount->isActive()) {
            return max(0, $base);
        }

        if ($discount->type === 'fixed') {
            return max(0, $base - min($base, (float) $discount->value));
        }

        return max(0, $base - min($base, round($base * ((float) $discount->value / 100), 2)));
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    protected function buildNextAction(OrderIntent $intent, \App\Models\PaymentProvider $provider, array $payload): array
    {
        $providerMeta = (array) ($provider->meta ?? []);

        return [
            'type' => 'redirect',
            'url' => $payload['success_url'] ?? null,
            'provider_reference' => $provider->external_reference,
            'provider_meta' => $providerMeta,
        ];
    }
}
