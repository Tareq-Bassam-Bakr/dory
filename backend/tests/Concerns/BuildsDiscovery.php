<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\Service;
use App\Models\User;

/**
 * Public catalogue fixtures. Use together with BuildsTenants and BuildsQueues.
 */
trait BuildsDiscovery
{
    /**
     * An active business with one active branch (Damascus by default).
     *
     * @param  array<string, mixed>  $branch
     * @return array{0: Business, 1: Branch}
     */
    protected function publicBusiness(string $name, array $branch = [], ?BusinessCategory $category = null): array
    {
        $business = $this->businessOwnedBy(User::factory()->create(), ['name' => $name], $category);

        return [$business, $this->branchOf($business, $branch + [
            'name' => 'Main',
            'city' => 'Damascus',
            'country' => 'SY',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ])];
    }

    /**
     * A service with a queue in the branch, open unless told otherwise.
     *
     * @param  array<string, mixed>  $queue
     * @return array{0: Service, 1: Queue, 2: QueueSession|null}
     */
    protected function openQueueIn(Branch $branch, array $queue = [], bool $open = true, string $service = 'Consultation'): array
    {
        $created = $this->serviceOf($branch, ['name' => $service]);
        $model = $this->queueFor($created, $queue);

        return [$created, $model, $open ? $this->openSession($model, User::factory()->create()) : null];
    }

    protected function setStatus(object $model, string $status): void
    {
        $model->forceFill(['status' => $status])->save();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function discovery(string $path, array $query = []): string
    {
        return '/api/v1/discovery/'.$path.($query === [] ? '' : '?'.http_build_query($query));
    }
}
