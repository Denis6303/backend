<?php

namespace App\Models;

use App\Services\Finance\RefundRateResolver;
use App\Services\Finance\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FundraisingContribution extends Model
{
    protected $fillable = [
        'fundraising_id',
        'payment_intent_id',
        'payment_method_id',
        'payer_user_id',
        'email',
        'phone',
        'name',
        'amount',
        'fees',
        'is_amount_visible',
        'message',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'is_amount_visible' => 'bool',
        'paid_at' => 'datetime',
    ];

    public function fundraising(): BelongsTo
    {
        return $this->belongsTo(Fundraising::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(FundraisingPaymentIntent::class, 'payment_intent_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function cancel(?string $reason = null): ?UserWalletTransaction
    {
        return DB::transaction(function () use ($reason) {
            $rate = app(RefundRateResolver::class)->resolveForFundraisingContribution($this);
            $refundAmount = round(((float) $this->amount) * $rate, 2);

            if ($refundAmount <= 0) {
                return null;
            }

            return app(WalletService::class)->refundFundraisingContribution($this, $refundAmount, $reason);
        });
    }
}

