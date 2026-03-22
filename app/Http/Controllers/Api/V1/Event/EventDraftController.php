<?php

namespace App\Http\Controllers\Api\V1\Event;

use App\Events\EventPublished;
use App\Exceptions\ApiCodes;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDraft;
use App\Rules\BooleanString;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Event Draft
 *
 * Event draft management (multi-step creation and publication).
 *
 * All routes of this controller require an authenticated user (auth:api).
 */
class EventDraftController extends Controller
{
    /**
     * List current user's event drafts.
     *
     * Returns a paginated list of all non-published event drafts
     * belonging to the authenticated user.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (1-100). Example: 15
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "code": 0,
     *   "locale": "en",
     *   "message": "Event drafts retrieved successfully",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "user_id": 1,
     *         "type": "event",
     *         "current_step": 2,
     *         "data": {
     *           "title": "My event",
     *           "group": "event"
     *         }
     *       }
     *     ],
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     *
     * @authenticated
     */
    public function myEventDrafts(Request $request): JsonResponse
    {
        $validated = $this->validateWithPagination($request->all());

        $query = EventDraft::where('user_id', auth()->id())
            ->event()
            ->unpublished();

        return $this->buildPaginatedResponse(
            $query,
            $validated,
            'messages.event.drafts_retrieved',
            fn (EventDraft $draft) => $this->toDraftListArray($draft)
        );
    }

    /**
     * Get a single event draft.
     *
     * Returns details of a specific draft belonging to the authenticated user.
     *
     * @urlParam id integer required Draft ID. Example: 1
     *
     * @authenticated
     */
    public function myEventDraft($id): JsonResponse
    {
        $draft = EventDraft::query()
            ->unpublished()
            ->findOrFail((int) $id);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.draft_retrieved'))
            ->withData($draft->fresh()->makeHidden(['media'])->toArray())
            ->build();
    }

    /**
     * Delete an event draft.
     *
     * Deletes the draft and its associated media.
     *
     * @urlParam id integer required Draft ID. Example: 1
     *
     * @authenticated
     */
    public function myDestroy($id): JsonResponse
    {
        $draft = EventDraft::where('user_id', auth()->id())
            ->event()
            ->findOrFail($id);

        $draft->deleteDraft();

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.draft_deleted'))
            ->build();
    }

    /**
     * Create event - step 1 (basic info + image).
     *
     * Entry point for creating or updating an event draft. Saves
     * the basic event information and optional cover image.
     *
     * @bodyParam draft_id integer optional Existing draft ID to update. Example: 1
     * @bodyParam image file optional Event cover image (required when creating a new draft).
     * @bodyParam title string required Event title (max 50 chars). Example: My first event
     * @bodyParam category_id integer required Category ID. Example: 1
     * @bodyParam description string optional Detailed description (max 5000 chars). Example: This is a great event.
     * @bodyParam attendance_type string required Event attendance type (in_person or online). Example: in_person
     *
     * @response 200 scenario="Updated draft" {
     *   "success": true,
     *   "code": 0,
     *   "locale": "en",
     *   "message": "Step 1 saved successfully",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "type": "event",
     *     "current_step": 2,
     *     "data": {
     *       "title": "My first event",
     *       "description": "This is a great event.",
     *       "attendance_type": "in_person",
     *       "group": "event"
     *     }
     *   }
     * }
     *
     * @authenticated
     */
    public function storeStep1(Request $request): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'draft_id' => 'nullable|exists:event_drafts,id',
            'image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'title' => 'required|string|max:50',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:5000',
            'attendance_type' => 'required|string|in:' . implode(',', Event::ATTENDANCE_TYPES),
        ]);

        DB::beginTransaction();

        $partial = [
            'event' => [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category_id' => (int) $validated['category_id'],
                'attendance_type' => $validated['attendance_type'],
            ],
        ];

        $userId = auth()->id();
        $draftId = $validated['draft_id'] ?? null;

        if (isset($draftId)) {
            $draft = EventDraft::where('user_id', $userId)
                ->event()
                ->unpublished()
                ->findOrFail($draftId);

            $draft->updatePartialData($partial, 2);
            $draft->category_id = (int) $validated['category_id'];
            $draft->save();
        } else {
            if (! $request->hasFile('image')) {
                return ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('validation.required', ['attribute' => 'image']))
                    ->withData(['errors' => ['image' => [__('validation.required', ['attribute' => 'image'])]]])
                    ->build();
            }

            $draft = EventDraft::create([
                'user_id' => $userId,
                'category_id' => (int) $validated['category_id'],
                'data' => $partial,
                'current_step' => 2,
            ]);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::slug((string) $validated['title']) . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

            $draft->clearMediaCollection('cover');
            $draft->addMedia($file)
                ->usingFileName($filename)
                ->toMediaCollection('cover');
        }

        DB::commit();

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.step1_saved'))
            ->withData($this->toDraftArray($draft->fresh()))
            ->build();
    }

    public function getUtcOffsetsByCountry(string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $timezoneIdentifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);

        if (empty($timezoneIdentifiers)) {
            $timezoneIdentifiers = [config('app.timezone', 'UTC')];
        }

        $now = CarbonImmutable::now();
        $offsets = [];

        foreach ($timezoneIdentifiers as $timezoneId) {
            $timezone = new DateTimeZone($timezoneId);
            $offsetInSeconds = $timezone->getOffset($now);
            $offsets[] = [
                'offset' => (int) ($offsetInSeconds / 3600),
                'timezone' => $timezoneId,
            ];
        }

        return $offsets;
    }

    /**
     * Create event - step 2 (location, country, currency and dates).
     *
     * Saves location information, country, currency, and one or more
     * event occurrences (start/end dates).
     *
     * @urlParam id integer required Draft ID. Example: 1
     *
     * @bodyParam attendance_type string required Attendance type (in_person or online). Example: in_person
     * @bodyParam country_code string required Country code (tg for Togo, other for other countries). Example: tg
     * @bodyParam currency string required Currency code (XOF, USD, EUR). Example: XOF
     * @bodyParam address string optional Street address (required if attendance_type=in_person). Example: 10 Avenue de la Paix
     * @bodyParam city string optional City (required if attendance_type=in_person). Example: Lomé
     * @bodyParam start_dates array required Array of start datetimes (YYYY-MM-DD HH:MM:SS). Example: ["2026-07-01 20:00:00","2026-07-02 20:00:00"]
     * @bodyParam end_dates array required Array of end datetimes (same length as start_dates). Example: ["2026-07-01 23:00:00","2026-07-02 23:00:00"]
     *
     * @authenticated
     */
    public function storeStep2(Request $request, string $version, $id): JsonResponse
    {
        $countries = config('custom.allowed_countries');

        $validated = $this->validateOrFail($request->all(), [
            'country_code' => 'required|string|in:' . implode(',', array_keys($countries)),
            'currency' => 'required|string|in:' . implode(',', Event::CURRENCIES),
            'address' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:50',
            'start_dates' => 'required|array|min:1',
            'start_dates.*' => 'required|date',
            'end_dates' => 'required|array|min:1',
            'end_dates.*' => 'required|date',
        ]);

        $draft = EventDraft::query()
            ->where('user_id', auth()->id())
            ->unpublished()
            ->findOrFail($id);

        // attendance_type now comes from step 1
        $attendanceType = (string) ($draft->getData('event.attendance_type') ?? Event::ATTENDANCE_IN_PERSON);

        // Manual validation for address/city when in_person
        if ($attendanceType === Event::ATTENDANCE_IN_PERSON) {
            $errors = [];
            if (empty($validated['address'] ?? null)) {
                $errors['address'][] = __('validation.required', ['attribute' => 'address']);
            }
            if (empty($validated['city'] ?? null)) {
                $errors['city'][] = __('validation.required', ['attribute' => 'city']);
            }

            if (! empty($errors)) {
                return ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('validation.failed'))
                    ->withData(['errors' => $errors])
                    ->build();
            }
        }

        $offsets = $this->getUtcOffsetsByCountry($validated['country_code']);

        // Normalisation des dates / occurrences
        $now = Carbon::now();
        $startDates = [];
        $endDates = [];
        $count = min(count($validated['start_dates']), count($validated['end_dates'] ?? []));

        for ($i = 0; $i < $count; $i++) {
            $start = Carbon::parse($validated['start_dates'][$i]);
            $end = Carbon::parse($validated['end_dates'][$i]);

            if ($start->lte($now)) {
                $start = $now->copy()->addDay();
            }

            $minEnd = $start->copy()->addDay();
            if ($end->lt($minEnd)) {
                $end = $minEnd;
            }

            $startDates[] = $start->format('Y-m-d H:i:s');
            $endDates[] = $end->format('Y-m-d H:i:s');
        }

        $tickets = (array) ($draft->getData('tickets') ?? []);
        $occurrences = [];
        foreach ($startDates as $idx => $startDate) {
            $occurrences[] = [
                'start_date' => $startDate,
                'end_date' => $endDates[$idx] ?? null,
                'status' => Event::STATUS_UPCOMING,
                'free_event' => (bool) $draft->getData('free_event', false),
                'ticket_types' => $this->normalizeTicketTypes($tickets),
            ];
        }

        $partial = [
            'country_code' => $validated['country_code'],
            'currency' => $validated['currency'],
            'timezones' => $offsets,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'start_dates' => $startDates,
            'end_dates' => $endDates,
            'has_single_date' => count($startDates) === 1,
            'timezone_name' => 'GMT+00',
            'event' => array_merge((array) ($draft->getData('event') ?? []), [
                'country_code' => $validated['country_code'],
                'city' => $validated['city'] ?? null,
                'address' => $validated['address'] ?? null,
                'currency' => $validated['currency'],
                'timezone_name' => 'GMT+00',
            ]),
            'occurrences' => $occurrences,
        ];

        $draft->updatePartialData($partial, 3);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.step2_saved'))
            ->withData($this->toDraftArray($draft->fresh()))
            ->build();
    }

    /**
     * Create event - step 3 (tickets / pricing / free event).
     *
     * Defines tickets and pricing for the event. Currency was already
     * chosen at step 2 and is reused here.
     *
     * @urlParam id integer required Draft ID. Example: 1
     *
     * @bodyParam free_event boolean required Whether the event is free. Example: false
     * @bodyParam tickets array required List of ticket configurations.
     * @bodyParam tickets[].name string required Ticket name (max 50 chars). Example: VIP
     * @bodyParam tickets[].price number required Ticket price. Example: 5000
     * @bodyParam tickets[].online_quantity integer required Online quantity (min 1). Example: 100
     * @bodyParam tickets[].print_quantity integer optional Printed quantity (min 0). Example: 50
     * @bodyParam tickets[].description string optional Ticket description (max 200 chars). Example: Access to VIP area
     * @bodyParam tickets[].general_conditions string optional General conditions (max 1000 chars). Example: Wristband required
     *
     * @authenticated
     */
    public function storeStep3(Request $request, string $version, $id): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'free_event' => ['required', new BooleanString],
            'tickets' => 'required|array|min:1',
            'tickets.*.name' => 'required|string|max:50',
            'tickets.*.price' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use (&$validated, &$currency) {
                    $freeEvent = filter_var($validated['free_event'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $minPrices = config('custom.ticket.min_price', []);
                    $minPrice = $minPrices[$currency] ?? 0;

                    if ($freeEvent) {
                        if ($value != 0) {
                            $fail(__('validation.price_must_be_zero_for_free_event'));
                        }
                    } else {
                        if ($value < $minPrice) {
                            $fail(__('validation.min.numeric', [
                                'attribute' => 'prix du ticket (catégorie ' . ((int) filter_var($attribute, FILTER_SANITIZE_NUMBER_INT) + 1) . ')',
                                'min' => $minPrice,
                            ]));
                        }
                    }
                },
            ],
            'tickets.*.online_quantity' => 'required|integer|min:1',
            'tickets.*.print_quantity' => 'nullable|integer|min:0',
            'tickets.*.description' => 'nullable|string|max:200',
            'tickets.*.general_conditions' => 'nullable|string|max:1000',
        ]);

        $validated['free_event'] = filter_var($validated['free_event'], FILTER_VALIDATE_BOOLEAN);

        if ($validated['free_event'] && count($validated['tickets']) !== 1) {
            return ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withMessage(__('validation.only_one_ticket_for_free_event'))
                ->build();
        }

        $draft = EventDraft::query()
            ->where('user_id', auth()->id())
            ->unpublished()
            ->findOrFail($id);

        $currency = (string) ($draft->getData('currency') ?? 'XOF');

        $ticketTypes = $this->normalizeTicketTypes((array) $validated['tickets']);
        $occurrences = collect((array) ($draft->getData('occurrences') ?? []))->map(function (array $occ) use ($validated, $ticketTypes) {
            $occ['free_event'] = (bool) $validated['free_event'];
            $occ['ticket_types'] = $ticketTypes;

            return $occ;
        })->all();

        $partial = [
            'currency' => $currency,
            'free_event' => (bool) $validated['free_event'],
            'tickets' => $validated['tickets'],
            'event' => array_merge((array) ($draft->getData('event') ?? []), [
                'currency' => $currency,
            ]),
            'occurrences' => $occurrences,
        ];

        $draft->updatePartialData($partial, 4);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.step4_saved'))
            ->withData($this->toDraftArray($draft->fresh()))
            ->build();
    }

    /**
     * Finalize event draft.
     *
     * Finalizes the draft and creates the real Event with its occurrences
     * and ticket types. All previous steps must be completed.
     *
     * @urlParam id integer required Draft ID. Example: 1
     *
     * @bodyParam publish_now boolean required Publish immediately (true) or keep as saved (false). Example: true
     * @bodyParam scheduled_at datetime optional Scheduled publication datetime (ISO 8601). Example: 2026-07-01T18:00:00Z
     * @bodyParam is_private boolean required Whether the event is private. Example: false
     *
     * @authenticated
     */
    public function finalizeEventDraft(Request $request, string $version, $id): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'publish_now' => ['required', new BooleanString],
            'scheduled_at' => 'nullable|date',
            'is_private' => ['required', new BooleanString],
        ]);

        $validated['publish_now'] = filter_var($validated['publish_now'], FILTER_VALIDATE_BOOLEAN);
        $validated['is_private'] = filter_var($validated['is_private'], FILTER_VALIDATE_BOOLEAN);

        $draft = EventDraft::query()
            ->where('user_id', auth()->id())
            ->unpublished()
            ->findOrFail($id);

        if ((int) $draft->current_step < 4) {
            return ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withMessage(__('messages.event.incomplete_draft'))
                ->build();
        }

        $eventData = (array) ($draft->getData('event') ?? []);
        $eventData['is_private'] = (bool) $validated['is_private'];

        $draftData = [
            'publish_now' => (bool) $validated['publish_now'],
            'scheduled_at' => isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at'])->toDateTimeString() : null,
            'event' => $eventData,
        ];

        $draft->updatePartialData($draftData, 4);

        $printTicket = $this->getHasPrintTicket($draft);
        $scheduled = ! $draftData['publish_now'];

        $event = Event::saveEventWithRelations($draft);
        $occurrences = $event->occurrences->map->only(['id']);

        $draft->markAsPublished();

        EventPublished::dispatch($event, (bool) $draftData['publish_now']);

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.created'))
            ->withData([
                'event' => ['id' => $event->id],
                'occurrences' => $occurrences,
                'print_ticket' => $printTicket && count($occurrences) == 1,
                'scheduled' => $scheduled,
            ])
            ->build();
    }

    public function getHasPrintTicket(EventDraft $draft): bool
    {
        $tickets = $draft->getData('tickets') ?? [];

        return collect($tickets)
            ->where('print_quantity', '>', 0)
            ->isNotEmpty();
    }

    /**
     * @param  array<int,array<string,mixed>>  $tickets
     * @return array<int,array<string,mixed>>
     */
    private function normalizeTicketTypes(array $tickets): array
    {
        $result = [];

        foreach ($tickets as $t) {
            $onlineQty = (int) ($t['online_quantity'] ?? 0);
            $printQty = (int) ($t['print_quantity'] ?? 0);
            $totalQty = max(0, $onlineQty + $printQty);

            $result[] = [
                'name' => $t['name'] ?? 'Ticket',
                'description' => $t['description'] ?? null,
                'general_conditions' => $t['general_conditions'] ?? null,
                'price' => (float) ($t['price'] ?? 0),
                'last_price' => null,
                'total_quantity' => $totalQty,
                'remaining_quantity' => $totalQty,
                'real_remaining_quantity' => $totalQty,
                'printed_quantity' => $printQty,
                'status' => 'active',
            ];
        }

        return $result;
    }

    /**
     * Same as {@see toDraftArray} but strips fields duplicated for list responses
     * (event summary vs data payload, dates vs occurrences, tickets vs occurrence ticket_types).
     *
     * @return array<string,mixed>
     */
    private function toDraftListArray(EventDraft $draft): array
    {
        $base = $this->toDraftArray($draft);
        /** @var array<string,mixed> $event */
        $event = (array) ($base['event'] ?? []);
        /** @var array<string,mixed> $data */
        $data = (array) ($base['data'] ?? []);

        foreach (['city', 'address', 'currency', 'country_code', 'timezone_name', 'category_id'] as $key) {
            if (array_key_exists($key, $event) && array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        $occurrences = $data['occurrences'] ?? null;
        if (is_array($occurrences) && $occurrences !== []) {
            unset($data['start_dates'], $data['end_dates']);

            if (! empty($data['tickets'])) {
                $data['occurrences'] = array_values(array_map(
                    static function (mixed $occ): mixed {
                        if (! is_array($occ)) {
                            return $occ;
                        }
                        unset($occ['ticket_types']);

                        return $occ;
                    },
                    $occurrences
                ));
            }
        }

        $base['data'] = $data;

        return $base;
    }

    /**
     * Normalize an EventDraft model into an API-friendly array.
     *
     * @return array<string,mixed>
     */
    private function toDraftArray(EventDraft $draft): array
    {
        /** @var array<string,mixed> $data */
        $data = $draft->data ?? [];
        $eventData = (array) ($data['event'] ?? []);

        // Avoid duplicating event information at both "event" and "data.event" levels
        if (array_key_exists('event', $data)) {
            unset($data['event']);
        }

        return [
            'id' => $draft->id,
            'event_id' => $draft->event_id,
            'user_id' => $draft->user_id,
            'category_id' => $draft->category_id,
            'current_step' => (int) ($draft->current_step ?? 1),
            'event' => $eventData,
            'cover_url' => $draft->getFirstMediaUrl('cover') ?: null,
            'data' => $data,
            'published_at' => optional($draft->published_at)?->toIso8601String(),
            'deleted_at' => optional($draft->deleted_at)?->toIso8601String(),
            'created_at' => optional($draft->created_at)?->toIso8601String(),
            'updated_at' => optional($draft->updated_at)?->toIso8601String(),
        ];
    }
}

