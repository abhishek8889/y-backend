<?php

namespace App\Services\Organisation;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Service;
use Symfony\Component\HttpFoundation\Response;

class OrganisationService extends Service
{
    /**
     * Get organisation details for the authenticated user.
     *
     * Business logic can be added here later.
     *
     * @return array{organisation: Organisation}
     */
    public function getOrganisationDetail(User $user): array
    {
        $organisationId = $user->loginContext()['organisation_id'];

        $organisation = $organisationId !== null
            ? Organisation::query()->find($organisationId)
            : null;

        if ($organisation === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_not_found'),
            );
        }

        return [
            'organisation' => $organisation,
        ];
    }
}
