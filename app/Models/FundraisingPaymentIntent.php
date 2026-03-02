<?php

namespace App\Models;

use App\Jobs\HandleConfirmedFundraisingPayment;
use App\Services\Payments\PaymentGatewayRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundraisingPaymentIntent extends Model
{
    protected $fillable = [
        'key',
        'fundraising_id',
        'user_id',
        'amount',
        'fees',
        'currency',
        'payment_provider_id',
        'payment_method_id',
        'status',
        'customer_email',
        'customer_phone',
        'expires_at',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function fundraising(): BelongsTo
    {
        return $this->belongsTo(Fundraising::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function contribution(): HasOne
    {
        return $this->hasOne(FundraisingContribution::class, 'payment_intent_id');
    }

    public static function newKey(): string
    {
        return Str::uuid()->toString();
    }

    public static function createFundraisingPaymentIntent(array $attributes): self
    {
        $attributes['key'] ??= self::newKey();
        $attributes['status'] ??= 'pending';

        return self::create($attributes);
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
            $refs = $gateway->createCheckoutForFundraisingIntent($this, $payload);

            $provider->fill($refs);
            $provider->save();

            return $provider;
        });
    }

    public function verify(): bool
    {
        if (! $this->paymentProvider) {
            return false;
        }

        $this->status = 'confirming';
        $this->save();

        $gateway = app(PaymentGatewayRegistry::class)->for($this->paymentProvider->provider_code);
        $paid = $gateway->verifyFundraisingPayment($this);

        if ($paid) {
            $this->markAsPaid();
            return true;
        }

        return false;
    }

    public function markAsPaid(): void
    {
        if ($this->status === 'paid') {
            return;
        }

        $this->status = 'paid';
        $this->save();

        HandleConfirmedFundraisingPayment::dispatch($this->id);
    }
}

