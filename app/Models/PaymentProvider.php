<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentProvider extends Model
{
    protected $fillable = [
        'provider_code',
        'external_reference',
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

