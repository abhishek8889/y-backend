<?php

namespace App\Services;

use App\Models\User;

class ProfileService extends Service
{
    /**
     * Get profile details for the authenticated user.
     *
     * Business logic can be added here later.
     *
     * @return array{user: User}
     */
    public function details(User $user): array
    {
        return [
            'user' => $user,
        ];
    }
}
