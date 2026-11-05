<?php

namespace App\Services;

use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Exceptions\ServiceException;
use App\Models\SiteDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pull domain connectivity from Vercel and sync the local site_domains (+ site) row.
 */
class SiteDomainConnectivityService extends Service
{
    public function __construct(
        private readonly VercelService $vercel,
    ) {}

    /**
     * Check Vercel verification/DNS status and persist the result locally.
     *
     * @return array{
     *     domain: SiteDomain,
     *     status: string,
     *     verified: bool,
     *     misconfigured: bool|null,
     *     configured_by: string|null,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     found_on_vercel: bool
     * }
     */
    public function refreshAndSync(SiteDomain $domain): array
    {
        $domain->loadMissing('site');

        try {
            $vercel = $this->vercel->getDomainStatus($domain->hostname);
        } catch (ServiceException $exception) {
            if ($exception->getStatusCode() === Response::HTTP_NOT_FOUND) {
                return $this->markMissingOnVercel($domain);
            }

            throw $exception;
        }

        $domainStatus = $this->mapVercelStatus((string) $vercel['status']);
        $verified = (bool) ($vercel['verified'] ?? false);
        $misconfigured = $vercel['misconfigured'] ?? null;

        $domain->fill([
            'status' => $domainStatus,
            'vercel_added' => true,
            'dns_instructions' => $vercel['dns_records'] ?? $domain->dns_instructions,
            'verified_at' => $verified
                ? ($domain->verified_at ?? now())
                : $domain->verified_at,
            'activated_at' => $domainStatus === SiteDomainStatusEnum::ACTIVE
                ? ($domain->activated_at ?? now())
                : null,
            'last_error' => $this->resolveLastError($domainStatus, $misconfigured, $domain->last_error),
        ]);
        $domain->save();

        if ($domainStatus === SiteDomainStatusEnum::ACTIVE) {
            $domain->site?->update([
                'status' => SiteStatusEnum::ACTIVE,
            ]);
        }

        $domain = $domain->fresh(['site']) ?? $domain->load('site');

        return [
            'domain' => $domain,
            'status' => (string) $vercel['status'],
            'verified' => $verified,
            'misconfigured' => is_bool($misconfigured) ? $misconfigured : null,
            'configured_by' => is_string($vercel['configured_by'] ?? null)
                ? $vercel['configured_by']
                : null,
            'dns_records' => $vercel['dns_records'] ?? [],
            'found_on_vercel' => true,
        ];
    }

    /**
     * Apply local state when Vercel no longer has this hostname on the project.
     *
     * @return array{
     *     domain: SiteDomain,
     *     status: string,
     *     verified: bool,
     *     misconfigured: bool|null,
     *     configured_by: string|null,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     found_on_vercel: bool
     * }
     */
    public function markMissingOnVercel(SiteDomain $domain): array
    {
        $domain->loadMissing('site');

        $domain->fill([
            'status' => SiteDomainStatusEnum::FAILED,
            'vercel_added' => false,
            'activated_at' => null,
            'last_error' => 'Domain was not found on the Vercel project.',
        ]);
        $domain->save();

        $domain = $domain->fresh(['site']) ?? $domain->load('site');

        return [
            'domain' => $domain,
            'status' => SiteDomainStatusEnum::FAILED->value,
            'verified' => false,
            'misconfigured' => null,
            'configured_by' => null,
            'dns_records' => is_array($domain->dns_instructions) ? $domain->dns_instructions : [],
            'found_on_vercel' => false,
        ];
    }

    public function mapVercelStatus(string $status): SiteDomainStatusEnum
    {
        return match ($status) {
            'active' => SiteDomainStatusEnum::ACTIVE,
            'pending_verification' => SiteDomainStatusEnum::PENDING_VERIFICATION,
            'pending_dns' => SiteDomainStatusEnum::PENDING_DNS,
            'failed' => SiteDomainStatusEnum::FAILED,
            default => SiteDomainStatusEnum::PENDING_DNS,
        };
    }

    private function resolveLastError(
        SiteDomainStatusEnum $domainStatus,
        mixed $misconfigured,
        ?string $previousError,
    ): ?string {
        if ($domainStatus === SiteDomainStatusEnum::ACTIVE) {
            return null;
        }

        if ($misconfigured === true) {
            return 'Domain DNS is not pointing to Vercel yet.';
        }

        if ($domainStatus === SiteDomainStatusEnum::PENDING_VERIFICATION) {
            return 'Domain ownership is pending verification on Vercel.';
        }

        return $previousError;
    }
}
