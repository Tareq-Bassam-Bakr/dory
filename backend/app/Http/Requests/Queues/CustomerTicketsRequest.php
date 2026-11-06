<?php

namespace App\Http\Requests\Queues;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * GET /me/tickets filters: ?group=active|history, or an explicit
 * ?status=waiting,called list. The tickets are always the caller's own.
 */
class CustomerTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group' => ['nullable', Rule::in(['active', 'history'])],
            'status' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->requestedStatuses() as $status) {
                if (TicketStatus::tryFrom($status) === null) {
                    $validator->errors()->add('status', __('validation.in', ['attribute' => 'status']));

                    return;
                }
            }
        }];
    }

    /**
     * The statuses to return, or null for every ticket.
     *
     * @return list<string>|null
     */
    public function statuses(): ?array
    {
        $requested = $this->requestedStatuses();
        if ($requested !== []) {
            return $requested;
        }

        return match ($this->query('group')) {
            'active' => TicketStatus::activeValues(),
            'history' => array_values(array_diff(
                array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases()),
                TicketStatus::activeValues(),
            )),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function requestedStatuses(): array
    {
        return array_values(array_filter(explode(',', (string) $this->query('status'))));
    }
}
