<?php

namespace App\Models;

use App\Jobs\HandleConfirmedOrder;
use App\Services\Payments\PaymentGatewayRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderIntent extends Model
{
    protected $fillable = [
        'key',
        'claim_code',
        'price',
        'fees',
        'currency',
        'status',
        'discount_id',
        'payment_provider_id',
        'event_occurrence_id',
        'customer_user_id',
        'customer_email',
        'customer_phone',
        'delivery_method',
        'meta',
        'expired_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'fees' => 'decimal:2',
        'meta' => 'array',
        'expired_at' => 'datetime',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function temporaryReservations(): HasMany
    {
        return $this->hasMany(TemporaryTicketReservation::class, 'order_intent_id');
    }

    public static function newKey(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * @param array<int,array{ticket_type_id:int,quantity:int}> $lines
     */
    public function reserveStock(array $lines, int $ttlMinutes = 15): void
    {
        if (! in_array($this->status, ['pending', 'processing', 'confirming'], true)) {
            throw new RuntimeException('Order intent cannot reserve stock in current status.');
        }

        DB::transaction(function () use ($lines, $ttlMinutes) {
            $occurrence = $this->occurrence()->lockForUpdate()->firstOrFail();

            $total = 0.0;
            foreach ($lines as $line) {
                $type = TicketType::query()
                    ->where('id', $line['ticket_type_id'])
                    ->where('event_occurrence_id', $occurrence->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = max(0, (int) $line['quantity']);
                if ($qty <= 0) {
                    continue;
                }

                if ($type->status !== 'active') {
                    throw new RuntimeException('Ticket type is disabled.');
                }

                if ($type->real_remaining_quantity < $qty) {
                    throw new RuntimeException('Insufficient ticket stock.');
                }

                $type->real_remaining_quantity -= $qty;
                $type->remaining_quantity = min($type->remaining_quantity, $type->real_remaining_quantity);
                $type->save();

                $total += (float) $type->price * $qty;
            }

            $meta = $this->meta ?? [];
            $meta['lines'] = $lines;
            $this->meta = $meta;
            $this->price = $total;
            $this->expired_at = now()->addMinutes($ttlMinutes);
            $this->save();
        });
    }

    public function checkout(string $providerCode, array $payload = []): PaymentProvider
    {
        $this->status = 'processing';
        $this->save();

        return DB::transaction(function () use ($providerCode, $payload) {
            $provider = PaymentProvider::create([
                'provider_code' => $providerCode,
                'meta' => [
                    'checkout_payload' => $payload,
                ],
            ]);

            $this->payment_provider_id = $provider->id;
            $this->save();

            $gateway = app(PaymentGatewayRegistry::class)->for($providerCode);
            $refs = $gateway->createCheckoutForOrderIntent($this, $payload);

            $provider->fill($refs);
            $provider->save();

            return $provider;
        });
    }

    public function verify(): bool
    {
        if ($this->status === 'confirmed') {
            return true;
        }

        if (! $this->paymentProvider) {
            return false;
        }

        $this->status = 'confirming';
        $this->save();

        $gateway = app(PaymentGatewayRegistry::class)->for($this->paymentProvider->provider_code);
        $paid = $gateway->verifyOrderIntentPayment($this);

        if ($paid) {
            $this->confirm();

            return true;
        }

        return false;
    }

    public function confirm(): void
    {
        if ($this->status === 'confirmed') {
            return;
        }

        $this->status = 'confirmed';
        $this->save();

        HandleConfirmedOrder::dispatch($this->id);
    }
}

