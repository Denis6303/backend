<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const ATTENDANCE_IN_PERSON = 'in_person';
    public const ATTENDANCE_ONLINE = 'online';
    public const ATTENDANCE_TYPES = [
        self::ATTENDANCE_IN_PERSON,
        self::ATTENDANCE_ONLINE,
    ];

    public const CURRENCIES = ['XOF', 'EUR', 'USD'];

    /**
     * Table des événements.
     */
    protected $table = 'events';

    protected $fillable = [
        'user_id',
        'category_id',
        'slug',
        'group',
        'title',
        'description',
        'images',
        'country_code',
        'city',
        'address',
        'online_link',
        'currency',
        'price_min',
        'status',
        'is_private',
        'is_verified',
        'commission_percentage',
        'commission_amount',
        'order_priority',
        'likes_count',
        'nb_visites',
        'invitations_is_free',
        'print_is_free',
        'category_level_1',
        'category_level_2',
        'category_level_3',
        'timezone_name',
        'cancelled_at',
    ];

    protected $casts = [
        'images' => 'array',
        'price_min' => 'decimal:2',
        'is_private' => 'bool',
        'is_verified' => 'bool',
        'commission_percentage' => 'decimal:3',
        'commission_amount' => 'decimal:2',
        'invitations_is_free' => 'bool',
        'print_is_free' => 'bool',
        'cancelled_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->width(480)
            ->height(480)
            ->nonQueued();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class, 'event_id');
    }

    public function ticketTypes(): HasManyThrough
    {
        return $this->hasManyThrough(
            TicketType::class,
            EventOccurrence::class,
            'event_id',
            'event_occurrence_id',
            'id',
            'id',
        );
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(EventDraft::class, 'event_id');
    }

    public function publish(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $this->status = 'upcoming';
        $this->save();
    }

    public function unpublish(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $this->status = 'saved';
        $this->save();
    }

    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public static function saveEventWithRelations(EventDraft $draft): self
    {
        /** @var array<string,mixed> $data */
        $data = $draft->data ?? [];

        return DB::transaction(function () use ($draft, $data) {
            $eventAttributes = (array) ($data['event'] ?? []);
            $occurrencesData = (array) ($data['occurrences'] ?? []);

            $event = $draft->event ?? new self();
            $event->fill($eventAttributes);
            $event->user_id ??= $draft->user_id;

            if (empty($event->slug)) {
                $event->slug = self::generateUniqueSlug((string) ($event->title ?? 'event'));
            }

            $event->save();

            foreach ($occurrencesData as $occData) {
                $occ = $event->occurrences()->updateOrCreate(
                    ['id' => $occData['id'] ?? null],
                    [
                        'subtitle' => $occData['subtitle'] ?? null,
                        'start_date' => $occData['start_date'] ?? null,
                        'end_date' => $occData['end_date'] ?? null,
                        'status' => $occData['status'] ?? 'upcoming',
                        'free_event' => (bool) ($occData['free_event'] ?? false),
                    ]
                );

                foreach ((array) ($occData['ticket_types'] ?? []) as $ttData) {
                    $occ->ticketTypes()->updateOrCreate(
                        ['id' => $ttData['id'] ?? null],
                        [
                            'name' => $ttData['name'] ?? 'Ticket',
                            'description' => $ttData['description'] ?? null,
                            'general_conditions' => $ttData['general_conditions'] ?? null,
                            'price' => $ttData['price'] ?? 0,
                            'last_price' => $ttData['last_price'] ?? null,
                            'total_quantity' => $ttData['total_quantity'] ?? 0,
                            'remaining_quantity' => $ttData['remaining_quantity'] ?? ($ttData['total_quantity'] ?? 0),
                            'real_remaining_quantity' => $ttData['real_remaining_quantity'] ?? ($ttData['total_quantity'] ?? 0),
                            'printed_quantity' => $ttData['printed_quantity'] ?? 0,
                            'tag' => $ttData['tag'] ?? null,
                            'tag_id' => $ttData['tag_id'] ?? null,
                            'status' => $ttData['status'] ?? 'active',
                        ]
                    );
                }
            }

            $draft->event()->associate($event);
            $draft->published_at = now();
            $draft->save();

            return $event->fresh(['occurrences.ticketTypes']);
        });
    }

    public function toArrayApi(): array
    {
        $this->loadMissing(['category', 'occurrences.ticketTypes']);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'is_private' => $this->is_private,
            'is_verified' => $this->is_verified,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'address' => $this->address,
            'online_link' => $this->online_link,
            'currency' => $this->currency,
            'price_min' => $this->price_min,
            'likes_count' => $this->likes_count,
            'nb_visites' => $this->nb_visites,
            'category' => $this->category?->only(['id', 'name', 'slug', 'level']),
            'cover_url' => $this->getFirstMediaUrl('cover') ?: null,
            'occurrences' => $this->occurrences->map(fn (EventOccurrence $o) => $o->toArrayApi())->values()->all(),
        ];
    }
}

