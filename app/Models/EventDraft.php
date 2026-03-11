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
        'category_id',
        'current_step',
        'data',
        'published_at',
    ];

    protected $casts = [
        'data' => 'array',
        'published_at' => 'datetime',
    ];

    public function scopeEvent($query)
    {
        return $query;
    }

    public function scopeUnpublished($query)
    {
        return $query->whereNull('published_at');
    }

    /**
     * Met à jour partiellement les données du draft et avance l'étape courante.
     *
     * @param  array<string,mixed>  $partialData
     */
    public function updatePartialData(array $partialData, int $step): void
    {
        /** @var array<string,mixed> $data */
        $data = $this->data ?? [];

        $this->data = array_merge($data, $partialData);
        $this->current_step = max((int) ($this->current_step ?? 1), $step);
        $this->save();
    }

    public function getData(string $key, mixed $default = null): mixed
    {
        /** @var array<string,mixed> $data */
        $data = $this->data ?? [];

        return \Illuminate\Support\Arr::get($data, $key, $default);
    }

    public function markAsPublished(): void
    {
        $this->published_at = now();
        $this->save();
        $this->delete();
    }

    public function deleteDraft(): void
    {
        $this->clearMediaCollection('cover');
        $this->clearMediaCollection('gallery');
        $this->delete();
    }

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

