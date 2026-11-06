<?php

namespace App\Http\Requests\Discovery;

use App\Services\Discovery\DiscoveryFilters;
use App\Services\Discovery\GeoCircle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters shared by the discovery lists
 * (docs/SEARCH_AND_FILTERS.md). Coordinates are used for this request only:
 * they are never stored or logged.
 */
class DiscoveryRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude', 'required_if:sort,nearest'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:'.$this->maxRadius()],
            'open_queue' => ['nullable', 'boolean'],
            'accepting' => ['nullable', 'boolean'],
            'max_wait' => ['nullable', 'integer', 'between:1,1440'],
            'min_rating' => ['nullable', 'numeric', 'between:1,5'],
            'sort' => ['nullable', Rule::in(DiscoveryFilters::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxPerPage()],
        ];
    }

    public function filters(): DiscoveryFilters
    {
        $data = $this->validated();

        return new DiscoveryFilters(
            q: $this->trimmed($data['q'] ?? null),
            category: $this->trimmed($data['category'] ?? null),
            country: isset($data['country']) ? strtoupper($data['country']) : null,
            city: $this->trimmed($data['city'] ?? null),
            circle: $this->circle(),
            openQueue: (bool) ($data['open_queue'] ?? false),
            accepting: (bool) ($data['accepting'] ?? false),
            maxWaitMinutes: isset($data['max_wait']) ? (int) $data['max_wait'] : null,
            minRating: isset($data['min_rating']) ? (float) $data['min_rating'] : null,
            service: $this->trimmed($data['service'] ?? null),
            sort: $data['sort'] ?? DiscoveryFilters::SORT_RELEVANCE,
        );
    }

    /**
     * The search area, when coordinates were given.
     */
    public function circle(): ?GeoCircle
    {
        $data = $this->validated();

        if (! isset($data['latitude'], $data['longitude'])) {
            return null;
        }

        return new GeoCircle(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) ($data['radius_km'] ?? config('dory.discovery.default_radius_km')),
        );
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    protected function maxPerPage(): int
    {
        return (int) config('dory.discovery.max_per_page');
    }

    private function maxRadius(): int
    {
        return (int) config('dory.discovery.max_radius_km');
    }

    private function trimmed(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
