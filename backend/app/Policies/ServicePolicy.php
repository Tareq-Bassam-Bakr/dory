<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Services are always authorized through their parent branch: whoever may
 * manage the branch's services may change this one.
 */
class ServicePolicy
{
    public function __construct(private readonly BranchPolicy $branches) {}

    public function view(User $user, Service $service): Response
    {
        return $this->branches->view($user, $service->branch)->allowed()
            ? Response::allow()
            : self::notFound();
    }

    public function update(User $user, Service $service): Response
    {
        return $this->throughBranch($user, $service);
    }

    public function delete(User $user, Service $service): Response
    {
        return $this->throughBranch($user, $service);
    }

    public static function notFound(): Response
    {
        return Response::denyAsNotFound(__('api.service.not_found'), 'service_not_found');
    }

    private function throughBranch(User $user, Service $service): Response
    {
        $response = $this->branches->manageServices($user, $service->branch);

        // Hide the branch from users who cannot reach it, as for the service itself.
        return $response->status() === 404 ? self::notFound() : $response;
    }
}
