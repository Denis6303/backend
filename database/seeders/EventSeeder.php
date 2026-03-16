<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        if ($categories->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        // 40 upcoming, 10 completed, 10 saved = 60 events
        $statuses = array_merge(
            array_fill(0, 40, Event::STATUS_UPCOMING),
            array_fill(0, 10, Event::STATUS_COMPLETED),
            array_fill(0, 10, Event::STATUS_SAVED),
        );

        $titles = [
            'Afrobeat Night in Lomé',
            'Business Networking Afterwork',
            'Startup Pitch & Demo Day',
            'Gospel Live Concert',
            'Street Food Festival',
            'Cinema Open Air',
            'Tech & Innovation Summit',
            'Yoga & Wellness Retreat',
            'Comedy Night Show',
            'Jazz & Blues Evening',
            'Sport & Fitness Day',
            'Cultural Dance Showcase',
            'Entrepreneurship Masterclass',
            'Digital Marketing Workshop',
            'Photography Exhibition',
            'Startup Founders Meetup',
            'Art & Creativity Fair',
            'Coding Bootcamp Weekend',
            'Music Producers Lab',
            'Wine & Cheese Tasting',
        ];

        foreach ($statuses as $index => $status) {
            $category = $categories->random();

            $baseTitle = $titles[$index % count($titles)];
            $title = $baseTitle.' #'.($index + 1);

            $event = Event::create([
                'user_id' => 1,
                'category_id' => $category->id,
                'slug' => Event::generateUniqueSlug($title),
                'title' => $title,
                'description' => 'Demo event seeded for homepage and listings ('.$status.').',
                'country_code' => 'tg',
                'city' => 'Lomé',
                'address' => '10 Avenue de la Paix',
                'currency' => 'XOF',
                'status' => $status,
                'is_private' => false,
                'is_verified' => true,
            ]);

            $occurrencesCount = rand(1, 3);
            $allPrices = [];

            for ($j = 0; $j < $occurrencesCount; $j++) {
                $start = match ($status) {
                    Event::STATUS_UPCOMING => $now->copy()->addDays(rand(1, 60))->setTime(20, 0),
                    Event::STATUS_COMPLETED => $now->copy()->subDays(rand(1, 60))->setTime(20, 0),
                    default => $now->copy()->addDays(rand(5, 90))->setTime(20, 0),
                };

                $end = $start->copy()->addHours(4);

                $occ = EventOccurrence::create([
                    'event_id' => $event->id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => $status,
                    'free_event' => false,
                ]);

                $ticketsCount = rand(1, 3);
                for ($k = 0; $k < $ticketsCount; $k++) {
                    $price = rand(10, 50) * 1000; // XOF-like prices
                    $ticket = TicketType::create([
                        'event_occurrence_id' => $occ->id,
                        'name' => 'Category '.($k + 1),
                        'description' => 'Access for category '.($k + 1),
                        'general_conditions' => null,
                        'price' => $price,
                        'last_price' => null,
                        'total_quantity' => 100,
                        'remaining_quantity' => 100,
                        'real_remaining_quantity' => 100,
                        'printed_quantity' => 0,
                        'status' => 'active',
                    ]);

                    $allPrices[] = $ticket->price;
                }
            }

            if (! empty($allPrices)) {
                $event->price_min = min($allPrices);
                $event->save();
            }

            // Attach a distinct placeholder image for each event
            try {
                $url = 'https://picsum.photos/seed/event-'.$event->id.'/800/600';
                $event->addMediaFromUrl($url)->toMediaCollection('cover');
            } catch (\Throwable $e) {
                // Ignore image failures in seeding
            }
        }
    }
}

