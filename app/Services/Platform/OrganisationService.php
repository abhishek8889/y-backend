<?php

namespace App\Services\Platform;

use App\Models\Organisation;
use App\Services\Service;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

class OrganisationService extends Service
{
    /**
     * List organisations for the platform (superadmin).
     *
     * Business logic / filters can be added here later.
     *
     * @return array{organisations: Collection<int, Organisation>}
     */
    public function list(): array
    {
        $organisations = Organisation::query()
            ->latest('id')
            ->get();

        return [
            'organisations' => $organisations,
        ];
    }

    /**
     * Get one organisation by id for the platform (superadmin).
     *
     * @return array{organisation: Organisation}
     */
    public function getOrganisationDetailById(int $organisationId): array
    {
        $organisation = Organisation::query()
            ->with('owner')
            ->find($organisationId);

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
