<?php

namespace App\Http\Requests\Discovery;

use App\Support\Pagination;

/**
 * GET /discovery/nearby: a location is required. Pages may be larger than
 * other lists, because a map shows many branches at once.
 */
class NearbyRequest extends DiscoveryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['latitude'][] = 'required';
        $rules['longitude'][] = 'required';

        return $rules;
    }

    protected function maxPerPage(): int
    {
        return Pagination::MAX_PER_PAGE;
    }
}
