<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Fundraising extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'creator_user_id',
        'category_id',
        'title',
        'description',
        'slug',
        'beneficiary_type',
        'beneficiary',
        'beneficiary_display_name',
        'currency',
        'target_amount',
        'current_amount',
        'is_amount_visible',
        'is_private',
        'is_verified',
        'status',
        'country_code',
        'order_priority',
        'nb_visites',
        'likes_count',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'beneficiary' => 'array',
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'is_amount_visible' => 'bool',
        'is_private' => 'bool',
        'is_verified' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('beneficiary_documents');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->width(480)
            ->height(480)
            ->nonQueued();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(FundraisingPaymentIntent::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(FundraisingContribution::class);
    }

    public function commission(): HasMany
    {
        return $this->hasMany(FundraisingCommission::class);
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
        return $this->hasMany(FundraisingDraft::class);
    }

    public function open(): void
    {
        $this->status = 'open';
        $this->save();
    }

    public function close(): void
    {
        $this->status = 'closed';
        $this->save();
    }

    public function stop(): void
    {
        $this->status = 'stopped';
        $this->save();
    }

    public function acceptsContributions(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function latestContributionMessages(int $limit = 10): array
    {
        return $this->contributions()
            ->whereNotNull('message')
            ->orderByDesc('paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (FundraisingContribution $c) => [
                'name' => $c->name,
                'message' => $c->message,
                'paid_at' => optional($c->paid_at)->toIso8601String(),
            ])
            ->all();
    }

    public function getIsGoalReached(): bool
    {
        if ($this->target_amount === null) {
            return false;
        }

        return (float) $this->current_amount >= (float) $this->target_amount;
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
}

