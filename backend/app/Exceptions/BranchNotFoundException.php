<?php

namespace App\Exceptions;

class BranchNotFoundException extends ResourceNotFoundException
{
    public function __construct()
    {
        parent::__construct(__('api.branch.not_found'));
    }

    public function errorCode(): string
    {
        return 'branch_not_found';
    }
}
