<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Http\Requests\Services\Concerns\ValidatesServiceName;
use App\Models\Service;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PUT /services/{service}: partial update, including activation and
 * deactivation. The service always stays in its branch.
 */
class UpdateServiceRequest extends FormRequest
{
    use BusinessFieldRules, ValidatesServiceName;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->service());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->service();

        return [
            ...$this->serviceRules(partial: true, nameRules: [$this->uniqueNameInBranch($service->branch, $service)]),
            ...$this->protectedServiceFieldRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->hasAny(self::serviceKeys())) {
                    $validator->errors()->add('service', __('api.service.fields_required'));
                }
            },
        ];
    }

    public function service(): Service
    {
        /** @var Service */
        return $this->route('service');
    }
}
