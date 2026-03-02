<?php

namespace App\Models;

use App\Services\Finance\RefundRateResolver;
use App\Services\Finance\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_key',
        'ticket_number',
        'order_id',
        'ticket_type_id',
        'item_occurrence_id',
        'price',
        'status',
        'is_cancellable',
        'validated_at',
        'cancelled_at',
        'transferred_at',
        'user_id',
        'distributor_user_id',
        'email',
        'phone',
        'full_name',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_cancellable' => 'bool',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    public static function newTicketKey(): string
    {
        return Str::uuid()->toString();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ItemOccurrence::class, 'item_occurrence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributor_user_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TicketTransfer::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(TicketRefund::class);
    }

    public function validateTicket(?int $validatorUserId = null): void
    {
        if ($this->status === 'validated') {
            return;
        }

        $this->status = 'validated';
        $this->validated_at = now();
        $this->save();
    }

    public function cancelAndRefund(?string $reason = null): ?TicketRefund
    {
        if (! $this->is_cancellable || $this->status === 'cancelled') {
            return null;
        }

        return DB::transaction(function () use ($reason) {
            $this->refresh();

            if (! $this->is_cancellable || $this->status === 'cancelled') {
                return null;
            }

            $this->status = 'cancelled';
            $this->cancelled_at = now();
            $this->save();

            $rate = app(RefundRateResolver::class)->resolveForTicket($this);
            $amount = round(((float) $this->price) * $rate, 2);

            if ($amount <= 0) {
                return TicketRefund::create([
                    'ticket_id' => $this->id,
                    'order_id' => $this->order_id,
                    'currency' => $this->order?->currency ?? ($this->occurrence?->item?->currency ?? 'XOF'),
                    'amount' => 0,
                    'rate' => $rate,
                    'reason' => $reason ?? 'no_refund',
                ]);
            }

            $walletTx = app(WalletService::class)->refundTicket($this, $amount, $reason);

            return TicketRefund::create([
                'ticket_id' => $this->id,
                'order_id' => $this->order_id,
                'user_wallet_transaction_id' => $walletTx?->id,
                'currency' => $walletTx?->currency ?? ($this->order?->currency ?? 'XOF'),
                'amount' => $amount,
                'rate' => $rate,
                'reason' => $reason ?? 'ticket_cancelled',
            ]);
        });
    }

    public function qrPayload(): string
    {
        return base64_encode(json_encode([
            'ticket_key' => $this->ticket_key,
            'ticket_number' => $this->ticket_number,
        ], JSON_THROW_ON_ERROR));
    }

    public function transferTo(?User $toUser, ?string $toEmail = null, ?string $toPhone = null, ?string $toFullName = null): void
    {
        DB::transaction(function () use ($toUser, $toEmail, $toPhone, $toFullName) {
            $this->refresh();

            $fromUserId = $this->user_id;
            $toUserId = $toUser?->id;

            $this->user_id = $toUserId;
            $this->email = $toEmail ?? $this->email;
            $this->phone = $toPhone ?? $this->phone;
            $this->full_name = $toFullName ?? $this->full_name;
            $this->status = 'transferred';
            $this->transferred_at = now();
            $this->save();

            $this->transfers()->create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'transferred_at' => $this->transferred_at,
            ]);
        });
    }
}

