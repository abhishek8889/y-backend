<?php

namespace App\Services\Organisation;

use App\Enum\RoleEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RolePermissionService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
     * List all organisation-scoped permissions available for role assignment.
     *
     * @return array{permissions: Collection<int, Permission>}
     */
    public function getAllOrganisationPermissions(User $user): array
    {
        $permissions = Permission::query()
            ->organisation()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'module', 'name', 'scope', 'description']);

        return [
            'permissions' => $permissions,
        ];
    }

    /**
     * Cursor-paginated roles list for the authenticated organisation.
     *
     * @param  array{search?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function getRoleList(User $user, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($user)['organisation'];
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Role::query()
            ->where('organisation_id', $organisation->id)
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%');
                },
            )
            ->whereNot('slug', RoleEnum::OWNER->value)
            ->withCount(['permissions', 'staff'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Get a role for the authenticated organisation by slug, with permissions.
     *
     * @return array{role: Role}
     */
    public function getRoleWithPermissions(User $user, string $roleSlug): array
    {
        $organisation = $this->organisation->getOrganisationDetail($user)['organisation'];
        $role = $this->resolveOrganisationRole($organisation->id, $roleSlug);

        $role->load([
            'permissions' => static fn ($query) => $query
                ->orderBy('module')
                ->orderBy('name'),
        ]);

        return [
            'role' => $role,
        ];
    }

    /**
     * Create an organisation role and attach selected organisation permissions.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     permission_ids?: list<int>|null
     * }  $data
     * @return array{role: Role}
     */
    public function createRoleWithPermission(User $user, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($user)['organisation'];

        $name = trim((string) $data['name']);
        $slug = Role::slugFromName($name);
        $description = filled($data['description'] ?? null)
            ? trim((string) $data['description'])
            : null;

        $this->assertRoleNameIsAllowed($organisation->id, $name, $slug);

        $permissionIds = $this->resolveOrganisationPermissionIds(
            $data['permission_ids'] ?? [],
        );

        try {
            $role = DB::transaction(function () use ($organisation, $name, $slug, $description, $permissionIds): Role {
                $role = Role::query()->create([
                    'organisation_id' => $organisation->id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                ]);

                $role->permissions()->sync($permissionIds);

                return $role->load([
                    'permissions' => static fn ($query) => $query
                        ->orderBy('module')
                        ->orderBy('name'),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_create_failed'),
            );
        }

        return [
            'role' => $role,
        ];
    }

    /**
     * Update an organisation role and sync its permissions.
     *
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     permission_ids?: list<int>|null
     * }  $data
     * @return array{role: Role}
     */
    public function updateRoleWithPermission(User $user, string $roleSlug, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($user)['organisation'];
        $role = $this->resolveOrganisationRole($organisation->id, $roleSlug);

        if ($role->slug === RoleEnum::OWNER->value) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_owner_immutable'),
            );
        }

        $name = trim((string) $data['name']);
        $slug = Role::slugFromName($name);
        $description = array_key_exists('description', $data)
            ? (filled($data['description']) ? trim((string) $data['description']) : null)
            : $role->description;

        $this->assertRoleNameIsAllowed($organisation->id, $name, $slug, $role->id);

        $permissionIds = $this->resolveOrganisationPermissionIds(
            $data['permission_ids'] ?? [],
        );

        try {
            $role = DB::transaction(function () use ($role, $name, $slug, $description, $permissionIds): Role {
                $role->update([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                ]);

                $role->permissions()->sync($permissionIds);

                return $role->load([
                    'permissions' => static fn ($query) => $query
                        ->orderBy('module')
                        ->orderBy('name'),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_update_failed'),
            );
        }

        return [
            'role' => $role,
        ];
    }

    private function resolveOrganisationRole(int $organisationId, string $roleSlug): Role
    {
        $role = Role::query()
            ->where('organisation_id', $organisationId)
            ->where('slug', trim($roleSlug))
            ->first();

        if ($role === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_role_not_found'),
            );
        }

        return $role;
    }

    private function assertRoleNameIsAllowed(
        int $organisationId,
        string $name,
        string $slug,
        ?int $excludeRoleId = null,
    ): void {
        $reservedNames = array_map(
            static fn (RoleEnum $role): string => strtolower($role->value),
            RoleEnum::cases(),
        );

        if (
            in_array(strtolower($name), $reservedNames, true)
            || in_array($slug, $reservedNames, true)
        ) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_name_reserved'),
            );
        }

        $alreadyExists = Role::query()
            ->where('organisation_id', $organisationId)
            ->when(
                $excludeRoleId !== null,
                fn ($query) => $query->where('id', '!=', $excludeRoleId),
            )
            ->where(function ($query) use ($name, $slug): void {
                $query->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->orWhere('slug', $slug);
            })
            ->exists();

        if ($alreadyExists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_name_exists'),
            );
        }
    }

    /**
     * @param  list<int|string>|mixed  $permissionIds
     * @return list<int>
     */
    private function resolveOrganisationPermissionIds(mixed $permissionIds): array
    {
        if (! is_array($permissionIds) || $permissionIds === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $permissionIds,
        )));

        $validIds = Permission::query()
            ->organisation()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        sort($ids);
        $sortedValid = $validIds;
        sort($sortedValid);

        if ($ids !== $sortedValid) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_role_invalid_permissions'),
            );
        }

        return $validIds;
    }
}
