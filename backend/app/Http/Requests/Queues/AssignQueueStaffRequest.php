<?php

namespace App\Http\Requests\Queues;

use App\Models\BusinessMember;
use App\Models\Queue;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * POST /queues/{queue}/staff: { "member": "<member public id>" }. The
 * member is looked up only inside the queue's own business.
 */
class AssignQueueStaffRequest extends FormRequest
{
    private ?BusinessMember $resolvedMember = null;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manage', $this->queue());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('member')) {
                    return;
                }

                $this->resolvedMember = $this->queue()->branch->business->members()
                    ->where('public_id', $this->input('member'))
                    ->with(['user', 'branches'])
                    ->first();

                if ($this->resolvedMember === null) {
                    $validator->errors()->add('member', __('api.queue.member_not_eligible'));
                }
            },
        ];
    }

    public function queue(): Queue
    {
        /** @var Queue */
        return $this->route('queue');
    }

    public function member(): BusinessMember
    {
        /** @var BusinessMember */
        return $this->resolvedMember;
    }
}
