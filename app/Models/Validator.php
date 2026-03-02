<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validator extends Model
{
    protected $fillable = [
        'item_occurrence_id',
        'user_id',
        'status',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ItemOccurrence::class, 'item_occurrence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

