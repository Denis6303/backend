<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EventDraft extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * Table des brouillons d'événements.
     */
    protected $table = 'event_drafts';

    protected $fillable = [
        'event_id',
        'user_id',
        'data',
        'published_at',
    ];

    protected $casts = [
        'data' => 'array',
        'published_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

