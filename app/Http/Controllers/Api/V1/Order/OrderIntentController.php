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
 * Public purchase flow: temporary ticket reservation, then payment (Yass / Flooz) or free checkout.
 * Authentication: `Authorization: Bearer <token>` header (Passport user token or OAuth client token, `user_or_client` middleware).
 * Real PSP calls go through `App\Services\Payments\PaymentGatewayRegistry` (provider codes `yass`, `flooz`).
 */
class OrderIntentController extends Controller
{
    public function __construct(
        protected OrderIntentPurchaseService $purchaseService
    ) {
    }

    /**
     * Create an order intent (temporary stock reservation).
     *
     * @bodyParam type string required Purchase type. Only `online` is currently supported. Example: online
     * @bodyParam event_occurrence_id integer required ID of the event occurrence (session). Example: 1
     * @bodyParam tickets object required Quantities per ticket type: keys = ticket type IDs, values = quantities (> 0). Example: {"12":1,"15":2}
     * @bodyParam delivery_method string required How to send the confirmation: `email` or `sms`. Example: email
     * @bodyParam customer_id integer required Buyer user ID (must match the authenticated account). Example: 42
     * @bodyParam email string Customer email (required if `delivery_method` = email). Example: buyer@example.com
     * @bodyParam phone string Customer phone (required if `delivery_method` = sms). Example: +22890123456
     * @bodyParam coupon_code string optional Discount coupon code. Example: SUMMER2026
     * @bodyParam customer_full_name string optional Name to display on the order. Example: John Doe
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
     * Get an order intent details (reservations, amounts).
     *
     * @urlParam key string required UUID of the intent. Example: 550e8400-e29b-41d4-a716-446655440000
     * @queryParam customer_id integer optional If provided, must match the customer of the intent. Example: 42
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
     * Start the payment (PSP checkout) or confirm a free purchase.
     *
     * For `yass` or `flooz`, `country`, `operator` and `phone_number` are required. For a paid purchase, `success_url` and `failure_url` are required.
     * `*-deposit` modes are reserved for authenticated administrators.
     *
     * @urlParam key string required UUID of the intent. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam payment_method string required `yass`, `flooz`, `free`, `yass-deposit`, `flooz-deposit`. Example: yass
     * @bodyParam accept_terms boolean required Acceptance of terms (Laravel `accepted` rule). Example: true
     * @bodyParam success_url string Success return URL (required if an amount is due). Example: https://app.example.com/order/success
     * @bodyParam failure_url string Failure return URL (required if an amount is due). Example: https://app.example.com/order/failed
     * @bodyParam country string ISO 3166-1 alpha-2 country code (required for yass/flooz). Example: TG
     * @bodyParam operator string Operator identifier (required for yass/flooz). Example: YASS
     * @bodyParam phone_number string Phone number to be charged (required for yass/flooz). Example: 90123456
     * @bodyParam customer_id integer optional If provided, must match the customer of the intent. Example: 42
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
            'country' => 'required_if:payment_method,yass,flooz|nullable|string|size:2',
            'operator' => 'required_if:payment_method,yass,flooz|nullable|string|max:32',
            'phone_number' => 'required_if:payment_method,yass,flooz|nullable|string|max:32',
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
     * Verify the payment status (after PSP redirect or via polling).
     *
     * @urlParam key string required UUID of the intent. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam customer_id integer optional If provided, must match the customer of the intent. Example: 42
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

        if (! in_array($intent->status, ['processing', 'confirming'], true)) {
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
     * Cancel a pending order intent (release reserved stock).
     *
     * @urlParam key string required UUID of the intent. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam customer_id integer optional If provided, must match the customer of the intent. Example: 42
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
