<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'description',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}

