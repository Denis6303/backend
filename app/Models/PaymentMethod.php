<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code',
        'label_fr',
        'label_en',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];
}

