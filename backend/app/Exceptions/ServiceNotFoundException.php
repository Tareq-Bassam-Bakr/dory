<?php

namespace App\Exceptions;

class ServiceNotFoundException extends ResourceNotFoundException
{
    public function __construct()
    {
        parent::__construct(__('api.service.not_found'));
    }

    public function errorCode(): string
    {
        return 'service_not_found';
    }
}
