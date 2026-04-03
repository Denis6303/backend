<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Exceptions\ApiCodes;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;

/**
 * @group User tickets
 *
 * Endpoints related to the authenticated user's tickets.
 */

class TicketController extends Controller
{
    /**
     * My tickets.
     *
     * @authenticated
     */
    public function myTickets(Request $request): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'statuses' => 'sometimes|array',
            'statuses.*' => 'string|in:' . implode(',', array_keys(Ticket::STATUSES)),
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user('api');
        $query = Ticket::query()
            ->with(['ticketType', 'occurrence.event', 'order'])
            ->where('user_id', $user->id);

        if (!empty($validated['statuses'])) {
            $query->whereIn('status', (array) $validated['statuses']);
        }

        $query->orderByDesc('created_at');

        return $this->buildPaginatedResponse(
            $query,
            $validated,
            'messages.ticket.list_retrieved',
            fn (Ticket $t) => $this->transformTicketForApi($t),
        );
    }

    /**
     * Get user's transferred tickets.
     *
     * @authenticated
     */
    public function myTransferredTickets(Request $request): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user('api');

        $ticketIds = TicketTransfer::query()
            ->where('from_user_id', $user->id)
            ->pluck('ticket_id')
            ->unique()
            ->values();

        $query = Ticket::query()
            ->with(['ticketType', 'occurrence.event', 'order'])
            ->whereIn('id', $ticketIds)
            ->orderByDesc('created_at');

        return $this->buildPaginatedResponse(
            $query,
            $validated,
            'messages.ticket.transferred_list_retrieved',
            fn (Ticket $t) => $this->transformTicketForApi($t),
        );
    }

    /**
     * Get user's ticket.
     *
     * @urlParam id integer required Ticket ID. Example: 1
     *
     * @authenticated
     */
    public function myTicket(Request $request, $version, $id = null): JsonResponse
    {
        $id = $this->resolveTicketId($version, $id);
        $ticket = Ticket::query()
            ->with(['ticketType', 'occurrence.event', 'order', 'transfers'])
            ->where('user_id', $request->user('api')->id)
            ->findOrFail($id);

        return ResponseBuilder::asSuccess()
            ->withMessage('messages.ticket.retrieved')
            ->withData($this->transformTicketForApi($ticket))
            ->build();
    }

    /**
     * Transfer a ticket.
     *
     * @urlParam id integer required Ticket ID. Example: 1
     * @bodyParam email string required Recipient email. Example: recipient@example.com
     * @bodyParam email_confirmation string required Email confirmation. Example: recipient@example.com
     *
     * @authenticated
     */
    public function transferTicket(Request $request, $version, $id = null): JsonResponse
    {
        $user = $request->user('api');
        $id = $this->resolveTicketId($version, $id);
        $validated = $this->validateOrFail($request->all(), [
            'email' => ['required', 'string', 'email:rfc,dns', 'confirmed'],
            'email_confirmation' => ['required', 'string', 'email:rfc,dns'],
        ]);

        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->where('status', '<>', 'cancelled')
            ->whereNull('transferred_at')
            ->findOrFail($id);

        if ($validated['email'] === $user->email) {
            return ResponseBuilder::asError(ApiCodes::NOT_FOUND)
                ->withHttpCode(404)
                ->withMessage('messages.ticket.recipient_cannot_be_self')
                ->build();
        }

        $recipient = User::query()->where('email', $validated['email'])->first();
        $ticket->transferTo($recipient, $validated['email']);

        return ResponseBuilder::asSuccess()
            ->withMessage('messages.ticket.transfer_success')
            ->build();
    }

    /**
     * Update transferred ticket email.
     *
     * @urlParam id integer required Ticket ID. Example: 1
     * @bodyParam email string required New recipient email. Example: newrecipient@example.com
     * @bodyParam email_confirmation string required Email confirmation. Example: newrecipient@example.com
     *
     * @authenticated
     */
    public function updateTransferredTicketEmail(Request $request, $version, $id = null): JsonResponse
    {
        $user = $request->user('api');
        $id = $this->resolveTicketId($version, $id);
        $validated = $this->validateOrFail($request->all(), [
            'email' => ['required', 'string', 'email:rfc,dns', 'confirmed'],
            'email_confirmation' => ['required', 'string', 'email:rfc,dns'],
        ]);

        $ticket = Ticket::query()
            ->with('transfers')
            ->where('id', $id)
            ->whereHas('transfers', fn (Builder $q) => $q->where('from_user_id', $user->id))
            ->firstOrFail();

        // Allow update only if the recipient hasn't created an account yet (no to_user bound).
        if ($ticket->user_id !== null) {
            return ResponseBuilder::asError(ApiCodes::BAD_REQUEST)
                ->withHttpCode(400)
                ->withMessage('messages.ticket.retransfer_not_allowed')
                ->build();
        }

        // Do not create a new transfer record here: we only update the contact email
        // for a recipient who hasn't created an account yet.
        $ticket->email = $validated['email'];
        $ticket->save();

        return ResponseBuilder::asSuccess()
            ->withMessage('messages.ticket.retransfer_success')
            ->build();
    }

    /**
     * Cancel a ticket.
     *
     * @urlParam id integer required Ticket ID. Example: 1
     * @bodyParam reason string required Cancellation reason. Example: Change of plans
     * @bodyParam password string required Current user password. Example: mypassword123
     *
     * @authenticated
     */
    public function cancelTicket(Request $request, $version, $id = null): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'current_password:api'],
        ]);

        $id = $this->resolveTicketId($version, $id);
        $ticket = Ticket::query()
            ->with('order')
            ->where('user_id', $request->user('api')->id)
            ->where('status', '<>', 'cancelled')
            ->where('is_cancellable', true)
            ->findOrFail($id);

        $refund = $ticket->cancelAndRefund($validated['reason']);
        if ($refund === null) {
            return ResponseBuilder::asError(ApiCodes::BAD_REQUEST)
                ->withHttpCode(400)
                ->withMessage('messages.ticket.cannot_cancel')
                ->build();
        }

        return ResponseBuilder::asSuccess()
            ->withMessage('messages.ticket.cancelled')
            ->build();
    }

    private function transformTicketForApi(Ticket $ticket): array
    {
        $event = $ticket->occurrence?->event;

        return [
            'id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'email' => $ticket->email,
            'phone' => $ticket->phone,
            'full_name' => $ticket->full_name,
            'price' => (float) $ticket->price,
            'order_id' => $ticket->order_id,
            'order_number' => $ticket->order?->number,
            'ticket_refund_id' => $ticket->refunds?->first()?->id,
            'status' => $ticket->status,
            'is_cancellable' => (bool) $ticket->is_cancellable,
            'transferred_at' => optional($ticket->transferred_at)?->toIso8601String(),
            'qr_encoded_data' => $ticket->qrPayload(),
            'item_occurrence' => $ticket->occurrence ? [
                'start_date' => optional($ticket->occurrence->start_date)?->toDateTimeString(),
                'end_date' => optional($ticket->occurrence->end_date)?->toDateTimeString(),
                'item' => $event ? [
                    'title' => $event->title,
                    'city' => $event->city,
                    'address' => $event->address,
                    'online_link' => $event->online_link,
                    'currency' => $event->currency,
                    'category_name' => $event->category?->name,
                    'country_name' => $event->country_name ?? null,
                ] : null,
            ] : null,
            'ticket_type' => $ticket->ticketType ? [
                'name' => $ticket->ticketType->name,
            ] : null,
        ];
    }

    private function normalizeTicketId($id): int
    {
        if (!is_numeric($id) || (int) $id < 1) {
            abort(422, 'Invalid ticket id. Replace :id with a numeric value.');
        }

        return (int) $id;
    }

    private function resolveTicketId($version, $id): int
    {
        if (is_numeric($id) && (int) $id > 0) {
            return (int) $id;
        }

        if (is_numeric($version) && (int) $version > 0) {
            return (int) $version;
        }

        abort(422, 'Invalid ticket id. Replace :id with a numeric value.');
    }
}

