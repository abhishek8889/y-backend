<?php

namespace App\Services\Platform;

use App\Enum\MailSenderEnum;
use App\Models\Organisation;
use App\Services\MailService;
use App\Services\Service;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

class OrganisationService extends Service
{
    public function __construct(
        private MailService $mail,
    ) {}

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

    /**
     * Update organisation approve status for the platform (superadmin).
     *
     * @return array{organisation: Organisation}
     */
    public function manageApproveStatus(
        int $organisationId,
        bool $approveStatus,
        ?string $approveStatusReason = null,
    ): array {
        $organisation = Organisation::query()
            ->with('owner')
            ->find($organisationId);

        if ($organisation === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_not_found'),
            );
        }

        if ($organisation->approve_status === true) {
            $this->fail(
                Response::HTTP_BAD_REQUEST,
                __('messages.organisation_approve_status_already_set', [
                    'approve_status' => $approveStatus ? 'approved' : 'rejected',
                ]),
            );
        }

        $organisation->update([
            'approve_status' => $approveStatus,
            'approve_status_reason' => $approveStatus ? null : $approveStatusReason,
        ]);

        $organisation->refresh()->load('owner');

        $this->notifyOwnerOfApproveStatus($organisation);

        return [
            'organisation' => $organisation,
        ];
    }

    private function notifyOwnerOfApproveStatus(Organisation $organisation): void
    {
        $owner = $organisation->owner;

        if ($owner === null || blank($owner->email)) {
            return;
        }

        if ($organisation->approve_status) {
            $this->mail->send(
                to: $owner->email,
                subject: __('messages.organisation_approved_mail_subject'),
                view: 'mail.organisation-approved',
                data: [
                    'owner' => $owner,
                    'organisation' => $organisation,
                ],
                sentBy: MailSenderEnum::PLATFORM,
            );

            return;
        }

        $this->mail->send(
            to: $owner->email,
            subject: __('messages.organisation_rejected_mail_subject'),
            view: 'mail.organisation-rejected',
            data: [
                'owner' => $owner,
                'organisation' => $organisation,
                'reason' => $organisation->approve_status_reason,
            ],
            sentBy: MailSenderEnum::PLATFORM,
        );
    }
}
