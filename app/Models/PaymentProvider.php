<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentProvider extends Model
{
    protected $fillable = [
        'provider_code',
        'wave_checkout_id',
        'djamo_charge_id',
        'paystack_reference',
        'hub2_reference',
        'intouch_reference',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function orderIntent(): HasOne
    {
        return $this->hasOne(OrderIntent::class);
    }
}

