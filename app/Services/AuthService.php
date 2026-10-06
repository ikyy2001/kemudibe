<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuthRepository;

class AuthService
{
    private $authRepository;

    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function login(array $data)
    {
        return $this->authRepository->login($data);
    }

    public function tokenLogin(array $data)
    {
        return $this->authRepository->tokenLogin($data);
    }

    public function superAdminLogin(array $data)
    {
        return $this->authRepository->superAdminLogin($data);
    }

    public function changePassword(User $user, array $data)
    {
        return $this->authRepository->changePassword($user, $data);
    }
}
