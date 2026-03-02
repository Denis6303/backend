<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];
}

