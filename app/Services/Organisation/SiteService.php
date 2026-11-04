<?php

namespace App\Services\Organisation;

use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Exceptions\ServiceException;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Service;
use App\Services\VercelService;
use App\Support\UniqueIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly VercelService $vercel,
        private readonly MediaService $media,
    ) {}

    /**
     * List sites for the authenticated organiser's organisation (with domain + template).
     *
     * @return array{sites: Collection<int, Site>}
     */
    public function list(User $actor): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        $sites = Site::query()
            ->where('organisation_id', $organisation->id)
            ->with(['domain', 'template'])
            ->orderByDesc('id')
            ->get();

        return [
            'sites' => $sites,
        ];
    }

    /**
     * Create a draft site + domain row (name, hostname, optional template_id). No Vercel.
     *
     * @param  array{
     *     name: string,
     *     hostname: string,
     *     template_id?: int|null
     * }  $data
     * @return array{site: Site}
     */
    public function create(User $actor, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $hostname = $this->normalizeHostname((string) $data['hostname']);

        if (SiteDomain::query()->where('hostname', $hostname)->exists()) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_already_exists'),
            );
        }

        try {
            $site = DB::transaction(function () use ($actor, $organisation, $data, $hostname): Site {
                $uniqueId = UniqueIdGenerator::generate('STE');
                while (Site::query()->where('unique_id', $uniqueId)->exists()) {
                    $uniqueId = UniqueIdGenerator::generate('STE');
                }

                $site = Site::query()->create([
                    'unique_id' => $uniqueId,
                    'organisation_id' => $organisation->id,
                    'site_template_id' => $data['template_id'] ?? null,
                    'name' => (string) $data['name'],
                    'slug' => $this->generateUniqueSlug((string) $data['name'], (int) $organisation->id),
                    'status' => SiteStatusEnum::DRAFT,
                    'logo' => null,
                    'navigation' => null,
                    'feature_object' => [
                        'event_category' => 'on',
                        'news_category' => 'on',
                    ],
                    'created_by' => $actor->id,
                ]);

                SiteDomain::query()->create([
                    'site_id' => $site->id,
                    'hostname' => $hostname,
                    'status' => SiteDomainStatusEnum::PENDING_DNS,
                    'vercel_added' => false,
                    'dns_instructions' => [],
                    'verified_at' => null,
                    'last_error' => null,
                ]);

                return $site->load('domain');
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_create_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'site' => $site,
        ];
    }

    /**
     * Patch site fields. Only keys present in $data are updated.
     *
     * Supported keys: name, logo, navigation, status.
     * Navigation is always stored as:
     * [{ "name": string, "redirect_link": string }, ...]
     *
     * @param  array{
     *     name?: string,
     *     logo?: string|null,
     *     navigation?: list<array{name: string, redirect_link: string}>|null,
     *     status?: string
     * }  $data
     * @return array{
     *     site: Site,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     verified: bool|null
     * }
     */
    public function update(User $actor, int $siteId, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        $site = Site::query()
            ->where('organisation_id', $organisation->id)
            ->with('domain')
            ->find($siteId);

        if ($site === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_not_found'),
            );
        }

        $shouldPublish = array_key_exists('status', $data)
            && (string) $data['status'] === SiteStatusEnum::ACTIVE->value;

        try {
            $site = DB::transaction(function () use ($actor, $site, $data): Site {
                $updates = [];

                if (array_key_exists('name', $data)) {
                    $updates['name'] = (string) $data['name'];
                }

                if (array_key_exists('logo', $data)) {
                    $updates['logo'] = $this->resolveSiteMediaPath(
                        $actor,
                        $site,
                        $data['logo'] ?? null,
                        'logo',
                        $site->logo,
                    );
                }

                if (array_key_exists('navigation', $data)) {
                    $updates['navigation'] = $this->normalizeNavigation($data['navigation'] ?? null);
                }

                if (
                    array_key_exists('status', $data)
                    && (string) $data['status'] !== SiteStatusEnum::ACTIVE->value
                ) {
                    $updates['status'] = SiteStatusEnum::from((string) $data['status']);
                }

                if ($updates !== []) {
                    $site->fill($updates);
                    $site->save();
                }

                return $site->fresh(['domain']) ?? $site->load('domain');
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_update_failed'),
                $exception->getMessage(),
            );
        }

        $dnsRecords = [];
        $verified = null;

        if ($shouldPublish) {
            if (! $site->isBrandingComplete()) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.site_branding_required_to_publish'),
                );
            }

            $publishResult = $this->connectDomainOnPublish($site);
            $dnsRecords = $publishResult['dns_records'];
            $verified = $publishResult['verified'];

            $site->update([
                'status' => SiteStatusEnum::ACTIVE,
            ]);
            $site = $site->fresh(['domain']) ?? $site->load('domain');
        }

        return [
            'site' => $site,
            'dns_records' => $dnsRecords,
            'verified' => $verified,
        ];
    }

    /**
     * Canonical navigation JSON for sites.navigation (no client ids).
     *
     * @param  mixed  $raw
     * @return list<array{name: string, redirect_link: string}>|null
     */
    private function normalizeNavigation(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        if (! is_array($raw)) {
            return null;
        }

        $items = [];

        foreach (array_values($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = isset($row['name']) && is_string($row['name'])
                ? trim($row['name'])
                : '';
            $redirect = isset($row['redirect_link']) && is_string($row['redirect_link'])
                ? trim($row['redirect_link'])
                : (isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '/');

            if ($name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'redirect_link' => $redirect !== '' ? $redirect : '/',
            ];
        }

        return $items;
    }

    /**
     * Replace the site domain. Draft sites only update the local row; published sites swap on Vercel.
     *
     * @return array{
     *     site: Site,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     verified: bool|null
     * }
     */
    public function updateDomain(User $actor, int $siteId, string $domainInput): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $newHostname = $this->normalizeHostname($domainInput);

        $site = Site::query()
            ->where('organisation_id', $organisation->id)
            ->with('domain')
            ->find($siteId);

        if ($site === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_not_found'),
            );
        }

        $domain = $site->domain;
        if ($domain === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_not_found'),
            );
        }

        if ($domain->hostname === $newHostname) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_same'),
            );
        }

        $hostnameTaken = SiteDomain::query()
            ->where('hostname', $newHostname)
            ->where('id', '!=', $domain->id)
            ->exists();

        if ($hostnameTaken) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_already_exists'),
            );
        }

        // Draft: only persist the hostname locally — nothing is on Vercel yet.
        if ($site->status === SiteStatusEnum::DRAFT) {
            $domain->fill([
                'hostname' => $newHostname,
                'status' => SiteDomainStatusEnum::PENDING_DNS,
                'vercel_added' => false,
                'dns_instructions' => [],
                'verified_at' => null,
                'activated_at' => null,
                'last_error' => null,
            ]);
            $domain->save();

            return [
                'site' => $site->fresh(['domain']) ?? $site->load('domain'),
                'dns_records' => [],
                'verified' => null,
            ];
        }

        // Published (or otherwise non-draft): remove previous from Vercel, then add the new domain.
        $previousHostname = $domain->hostname;
        $wasOnVercel = (bool) $domain->vercel_added;

        $this->vercel->removeDomain($previousHostname);

        try {
            $vercel = $this->vercel->connectDomain($newHostname);

            $domainStatus = ! empty($vercel['verified'])
                ? SiteDomainStatusEnum::PENDING_DNS
                : SiteDomainStatusEnum::PENDING_VERIFICATION;

            $domain->fill([
                'hostname' => $newHostname,
                'status' => $domainStatus,
                'vercel_added' => true,
                'dns_instructions' => $vercel['dns_records'] ?? [],
                'verified_at' => ! empty($vercel['verified']) ? now() : null,
                'activated_at' => null,
                'last_error' => null,
            ]);
            $domain->save();

            $site = $site->fresh(['domain']) ?? $site->load('domain');

            return [
                'site' => $site,
                'dns_records' => $vercel['dns_records'] ?? [],
                'verified' => (bool) ($vercel['verified'] ?? false),
            ];
        } catch (Throwable $exception) {
            if ($wasOnVercel) {
                try {
                    $this->vercel->connectDomain($previousHostname);
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }

            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_domain_update_failed'),
                $exception->getMessage(),
            );
        }
    }

    /**
     * Check DNS/domain status on Vercel and update the site domain row.
     *
     * @return array{
     *     site: Site,
     *     status: string,
     *     verified: bool,
     *     misconfigured: bool|null,
     *     configured_by: string|null,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>
     * }
     */
    public function checkDomainStatus(User $actor, int $siteId): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        $site = Site::query()
            ->where('organisation_id', $organisation->id)
            ->with('domain')
            ->find($siteId);

        if ($site === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_not_found'),
            );
        }

        $domain = $site->domain;
        if ($domain === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_not_found'),
            );
        }

        if (! $domain->vercel_added) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_not_connected_yet'),
            );
        }

        $vercel = $this->vercel->getDomainStatus($domain->hostname);

        $domainStatus = match ($vercel['status']) {
            'active' => SiteDomainStatusEnum::ACTIVE,
            'pending_verification' => SiteDomainStatusEnum::PENDING_VERIFICATION,
            'pending_dns' => SiteDomainStatusEnum::PENDING_DNS,
            default => SiteDomainStatusEnum::PENDING_DNS,
        };

        $domain->fill([
            'status' => $domainStatus,
            'vercel_added' => true,
            'dns_instructions' => $vercel['dns_records'] ?? $domain->dns_instructions,
            'verified_at' => ! empty($vercel['verified'])
                ? ($domain->verified_at ?? now())
                : $domain->verified_at,
            'activated_at' => $domainStatus === SiteDomainStatusEnum::ACTIVE
                ? ($domain->activated_at ?? now())
                : null,
            'last_error' => $domainStatus === SiteDomainStatusEnum::ACTIVE
                ? null
                : ($vercel['misconfigured'] === true
                    ? 'Domain DNS is not pointing to Vercel yet.'
                    : $domain->last_error),
        ]);
        $domain->save();

        if ($domainStatus === SiteDomainStatusEnum::ACTIVE) {
            $site->update([
                'status' => SiteStatusEnum::ACTIVE,
            ]);
        }

        $site = $site->fresh(['domain']) ?? $site->load('domain');

        return [
            'site' => $site,
            'status' => $vercel['status'],
            'verified' => (bool) ($vercel['verified'] ?? false),
            'misconfigured' => $vercel['misconfigured'] ?? null,
            'configured_by' => $vercel['configured_by'] ?? null,
            'dns_records' => $vercel['dns_records'] ?? [],
        ];
    }

    /**
     * Connect the site domain to Vercel when publishing.
     *
     * @return array{
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     verified: bool
     * }
     */
    private function connectDomainOnPublish(Site $site): array
    {
        $domain = $site->domain;
        if ($domain === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_domain_required_to_publish'),
            );
        }

        if ($domain->vercel_added) {
            return [
                'dns_records' => is_array($domain->dns_instructions) ? $domain->dns_instructions : [],
                'verified' => $domain->verified_at !== null,
            ];
        }

        $vercel = $this->vercel->connectDomain($domain->hostname);

        $domainStatus = ! empty($vercel['verified'])
            ? SiteDomainStatusEnum::PENDING_DNS
            : SiteDomainStatusEnum::PENDING_VERIFICATION;

        $domain->fill([
            'status' => $domainStatus,
            'vercel_added' => true,
            'dns_instructions' => $vercel['dns_records'] ?? [],
            'verified_at' => ! empty($vercel['verified']) ? now() : null,
            'last_error' => null,
        ]);
        $domain->save();

        return [
            'dns_records' => $vercel['dns_records'] ?? [],
            'verified' => (bool) ($vercel['verified'] ?? false),
        ];
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#', '', $hostname) ?? $hostname;

        return rtrim($hostname, '/');
    }

    private function generateUniqueSlug(string $name, int $organisationId): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'site';
        }

        $slug = $base;
        $suffix = 2;

        while (
            Site::query()
                ->where('organisation_id', $organisationId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Persist a media path for site assets. Moves owned tmp uploads into sites/{id}/…
     */
    private function resolveSiteMediaPath(
        User $actor,
        Site $site,
        mixed $value,
        string $slot,
        ?string $previousPath,
    ): ?string {
        $path = $this->extractMediaPath($value);

        if ($path === null) {
            if (is_string($previousPath) && $previousPath !== '') {
                $this->media->deleteStoredFile($previousPath);
            }

            return null;
        }

        if ($previousPath !== null && $previousPath !== '' && $previousPath === $path) {
            return $previousPath;
        }

        if ($this->media->isTmpPath($path)) {
            $this->media->assertOwnedTmpFile($actor, $path);
            $moved = $this->media->moveOwnedTmpTo(
                $actor,
                $path,
                'sites/'.$site->id.'/'.$slot,
            );
            $path = $moved['path'];
        }

        if (
            is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $path
        ) {
            $this->media->deleteStoredFile($previousPath);
        }

        return $path;
    }

    private function extractMediaPath(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_array($value) && isset($value['path']) && is_string($value['path'])) {
            $trimmed = trim($value['path']);

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
