<?php

namespace App\Services\Organisation;

use App\Enum\MailSenderEnum;
use App\Enum\RoleEnum;
use App\Enum\StatusEnum;
use App\Exceptions\ServiceException;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\OrganiserStaff;
use App\Models\Role;
use App\Models\User;
use App\Services\MailService;
use App\Services\MediaService;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StaffMemberService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly MailService $mail,
        private readonly MediaService $media,
    ) {}

    /**
     * Cursor-paginated staff members for the authenticated organisation.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $actor, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = OrganiserStaff::query()
            ->where('organisation_id', $organisation->id)
            ->when(
                filled($status),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->whereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where(function ($inner) use ($search): void {
                            $inner->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhereRaw(
                                    "CONCAT(first_name, ' ', last_name) like ?",
                                    ['%'.$search.'%'],
                                )
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                    });
                },
            )
            ->with([
                'user:id,first_name,last_name,email,profile_image',
                'roles:id,name,slug',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Create a staff member user, attach them to the organisation, and assign roles.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     country_code: string,
     *     country: string,
     *     password: string,
     *     description?: string|null,
     *     profile_image?: string|null,
     *     role_ids: list<int>,
     *     status?: string|null
     * }  $data
     * @return array{staff_member: OrganiserStaff}
     */
    public function create(User $actor, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        $email = strtolower(trim((string) $data['email']));
        $plainPassword = (string) $data['password'];
        $description = filled($data['description'] ?? null)
            ? trim((string) $data['description'])
            : null;
        $profileImagePath = filled($data['profile_image'] ?? null)
            ? ltrim(trim((string) $data['profile_image']), '/')
            : null;

        if (User::query()->where('email', $email)->exists()) {
            $this->fail(
                Response::HTTP_CONFLICT,
                __('messages.organisation_staff_email_exists'),
            );
        }

        $roleIds = $this->resolveOrganisationRoleIds(
            $organisation->id,
            $data['role_ids'] ?? [],
        );

        $status = StatusEnum::tryFrom((string) ($data['status'] ?? StatusEnum::ACTIVE->value))
            ?? StatusEnum::ACTIVE;

        if ($profileImagePath !== null) {
            if (! $this->media->isTmpPath($profileImagePath)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.organisation_staff_profile_image_must_be_tmp'),
                );
            }

            $this->media->assertOwnedTmpFile($actor, $profileImagePath);
        }

        try {
            $staff = DB::transaction(function () use (
                $actor,
                $organisation,
                $data,
                $email,
                $roleIds,
                $status,
                $description,
                $profileImagePath,
            ): OrganiserStaff {
                $user = User::query()->create([
                    'first_name' => trim((string) $data['first_name']),
                    'last_name' => trim((string) $data['last_name']),
                    'email' => $email,
                    'phone' => trim((string) $data['phone']),
                    'country_code' => trim((string) $data['country_code']),
                    'country' => trim((string) $data['country']),
                    'password' => $data['password'],
                    'profile_image' => null,
                    'status' => StatusEnum::ACTIVE,
                    'email_verified_at' => now(),
                ]);

                if ($profileImagePath !== null) {
                    $moved = $this->media->moveOwnedTmpTo(
                        $actor,
                        $profileImagePath,
                        'users',
                    );

                    $user->forceFill([
                        'profile_image' => $moved['path'],
                    ])->save();
                }

                $staff = OrganiserStaff::query()->create([
                    'organisation_id' => $organisation->id,
                    'user_id' => $user->id,
                    'description' => $description,
                    'status' => $status,
                ]);

                $staff->roles()->sync($roleIds);

                return $staff->load([
                    'user',
                    'roles' => static fn ($query) => $query->orderBy('name'),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_create_failed'),
            );
        }

        $this->sendWelcomeEmail($staff, $organisation->name, $plainPassword);

        return [
            'staff_member' => $staff,
        ];
    }

    /**
     * Update organiser_staff status for a member (`member_id` = `user_id`).
     *
     * @return array{staff_member: OrganiserStaff}
     */
    public function manageStatus(User $actor, int $memberId, string $status): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $staff = $this->resolveOrganisationStaffMember($organisation->id, $memberId);

        if ($staff->user_id === $organisation->owner_id) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_owner_immutable'),
            );
        }

        $newStatus = StatusEnum::tryFrom($status);

        if ($newStatus === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_invalid_status'),
            );
        }

        if ($staff->status === $newStatus) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_status_already_set', [
                    'status' => $newStatus->value,
                ]),
            );
        }

        $staff->update([
            'status' => $newStatus,
        ]);

        return [
            'staff_member' => $staff->load([
                'user',
                'roles' => static fn ($query) => $query->orderBy('name'),
            ]),
        ];
    }

    /**
     * Get staff member details for the authenticated organisation (`member_id` = `user_id`).
     *
     * @return array{staff_member: OrganiserStaff}
     */
    public function details(User $actor, int $memberId): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $staff = $this->resolveOrganisationStaffMember($organisation->id, $memberId);

        $staff->load([
            'user',
            'roles' => static fn ($query) => $query->orderBy('name'),
        ]);

        return [
            'staff_member' => $staff,
        ];
    }

    /**
     * Update a staff member belonging to the authenticated organisation.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     country_code: string,
     *     country: string,
     *     password?: string|null,
     *     description?: string|null,
     *     profile_image?: string|null,
     *     role_ids: list<int>,
     *     status?: string|null
     * }  $data
     * @return array{staff_member: OrganiserStaff}
     */
    public function update(User $actor, int $memberId, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $staff = $this->resolveOrganisationStaffMember($organisation->id, $memberId);

        // dd($memberId, $organisation, $staff);

        if ($staff->user_id === $organisation->owner_id) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_owner_immutable'),
            );
        }

        $email = strtolower(trim((string) $data['email']));
        $description = array_key_exists('description', $data)
            ? (filled($data['description']) ? trim((string) $data['description']) : null)
            : $staff->description;

        $emailTaken = User::query()
            ->where('email', $email)
            ->where('id', '!=', $staff->user_id)
            ->exists();

        if ($emailTaken) {
            $this->fail(
                Response::HTTP_CONFLICT,
                __('messages.organisation_staff_email_exists'),
            );
        }

        $roleIds = $this->resolveOrganisationRoleIds(
            $organisation->id,
            $data['role_ids'] ?? [],
        );

        $status = array_key_exists('status', $data) && filled($data['status'] ?? null)
            ? (StatusEnum::tryFrom((string) $data['status']) ?? $staff->status)
            : $staff->status;

        $profileImagePath = array_key_exists('profile_image', $data)
            ? (filled($data['profile_image'] ?? null)
                ? ltrim(trim((string) $data['profile_image']), '/')
                : null)
            : false;

        if (is_string($profileImagePath) && $this->media->isTmpPath($profileImagePath)) {
            $this->media->assertOwnedTmpFile($actor, $profileImagePath);
        }

        try {
            $staff = DB::transaction(function () use (
                $actor,
                $staff,
                $data,
                $email,
                $roleIds,
                $status,
                $description,
                $profileImagePath,
            ): OrganiserStaff {
                $user = $staff->user;

                if ($user === null) {
                    $this->fail(
                        Response::HTTP_NOT_FOUND,
                        __('messages.organisation_staff_not_found'),
                    );
                }

                $userPayload = [
                    'first_name' => trim((string) $data['first_name']),
                    'last_name' => trim((string) $data['last_name']),
                    'email' => $email,
                    'phone' => trim((string) $data['phone']),
                    'country_code' => trim((string) $data['country_code']),
                    'country' => trim((string) $data['country']),
                ];

                if (filled($data['password'] ?? null)) {
                    $userPayload['password'] = $data['password'];
                }

                if ($profileImagePath !== false) {
                    $oldProfileImage = $user->profile_image;

                    if ($profileImagePath === null) {
                        $userPayload['profile_image'] = null;
                    } elseif ($this->media->isTmpPath($profileImagePath)) {
                        $moved = $this->media->moveOwnedTmpTo(
                            $actor,
                            $profileImagePath,
                            'users',
                        );
                        $userPayload['profile_image'] = $moved['path'];
                    } elseif ($profileImagePath === ltrim((string) $oldProfileImage, '/')) {
                        // Keep current image path.
                    } else {
                        $this->fail(
                            Response::HTTP_UNPROCESSABLE_ENTITY,
                            __('messages.organisation_staff_profile_image_must_be_tmp'),
                        );
                    }

                    $user->forceFill($userPayload)->save();

                    if (
                        isset($userPayload['profile_image'])
                        && filled($oldProfileImage)
                        && $oldProfileImage !== $userPayload['profile_image']
                    ) {
                        $this->media->deleteStoredFile($oldProfileImage);
                    }
                } else {
                    $user->forceFill($userPayload)->save();
                }

                $staff->update([
                    'description' => $description,
                    'status' => $status,
                ]);

                $staff->roles()->sync($roleIds);

                return $staff->load([
                    'user',
                    'roles' => static fn ($query) => $query->orderBy('name'),
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_update_failed'),
            );
        }

        return [
            'staff_member' => $staff,
        ];
    }

    /**
     * Resolve staff membership by organisation + user id (`member_id` = `user_id`).
     */
    private function resolveOrganisationStaffMember(int $organisationId, int $memberId): OrganiserStaff
    {
        $staff = OrganiserStaff::query()
            ->where('organisation_id', $organisationId)
            ->where('user_id', $memberId)
            ->with('user')
            ->first();

        if ($staff === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_staff_not_found'),
            );
        }

        return $staff;
    }

    private function sendWelcomeEmail(
        OrganiserStaff $staff,
        string $organisationName,
        string $plainPassword,
    ): void {
        $user = $staff->user;

        if ($user === null || blank($user->email)) {
            return;
        }

        $roleNames = $staff->roles
            ->pluck('name')
            ->filter()
            ->values()
            ->implode(', ');

        $this->mail->send(
            to: $user->email,
            subject: __('messages.organisation_staff_welcome_mail_subject', [
                'organisation' => $organisationName,
            ]),
            view: 'mail.staff-member-welcome',
            data: [
                'user' => $user,
                'organisationName' => $organisationName,
                'roleNames' => $roleNames !== '' ? $roleNames : '—',
                'password' => $plainPassword,
            ],
            sentBy: MailSenderEnum::ORGANISER,
        );
    }

    /**
     * @param  list<int|string>|mixed  $roleIds
     * @return list<int>
     */
    private function resolveOrganisationRoleIds(int $organisationId, mixed $roleIds): array
    {
        if (! is_array($roleIds) || $roleIds === []) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_roles_required'),
            );
        }

        $ids = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $roleIds,
        )));

        $roles = Role::query()
            ->where('organisation_id', $organisationId)
            ->whereIn('id', $ids)
            ->get(['id', 'slug']);

        if ($roles->count() !== count($ids)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_invalid_roles'),
            );
        }

        if ($roles->contains(fn (Role $role): bool => $role->slug === RoleEnum::OWNER->value)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_staff_owner_role_forbidden'),
            );
        }

        return $roles->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
