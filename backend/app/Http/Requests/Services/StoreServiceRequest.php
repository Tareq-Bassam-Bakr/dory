<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Http\Requests\Services\Concerns\ValidatesServiceName;
use App\Models\Branch;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /branches/{branch}/services: by the owner or a manager with access
 * to the branch.
 */
class StoreServiceRequest extends FormRequest
{
    use BusinessFieldRules, ValidatesServiceName;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manageServices', $this->branch());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->serviceRules(nameRules: [$this->uniqueNameInBranch($this->branch())]),
            ...$this->protectedServiceFieldRules(),
        ];
    }

    public function branch(): Branch
    {
        /** @var Branch */
        return $this->route('branch');
    }
}
