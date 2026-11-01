<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class VercelService extends Service
{
    /**
     * Add a domain on Vercel (or fetch it if already connected) and return DNS records.
     *
     * @return array{
     *     hostname: string,
     *     verified: bool,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     vercel: array<string, mixed>
     * }
     */
    public function connectDomain(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);
        $this->assertConfigured();

        $addResponse = $this->request('post', $this->projectDomainsPath(), [
            'name' => $hostname,
        ]);

        if ($addResponse->successful()) {
            $vercelDomain = $addResponse->json() ?? [];

            return $this->formatConnectResponse($hostname, $vercelDomain);
        }

        if ($this->isAlreadyOnProject($addResponse)) {
            $getResponse = $this->request('get', $this->projectDomainsPath($hostname));

            if ($getResponse->successful()) {
                $vercelDomain = $getResponse->json() ?? [];

                return $this->formatConnectResponse($hostname, $vercelDomain);
            }
        }

        $this->failFromVercelResponse(
            $addResponse,
            __('messages.vercel_connect_domain_failed'),
        );
    }

    /**
     * Remove a domain from the Vercel project. Treats 404 as already removed.
     */
    public function removeDomain(string $hostname): void
    {
        $hostname = $this->normalizeHostname($hostname);
        $this->assertConfigured();

        $response = $this->request('delete', $this->projectDomainsPath($hostname));

        if ($response->successful() || $response->status() === HttpResponse::HTTP_NOT_FOUND) {
            return;
        }

        $this->failFromVercelResponse(
            $response,
            __('messages.vercel_remove_domain_failed'),
        );
    }

    /**
     * Check current Vercel verification + DNS configuration for a domain.
     *
     * @return array{
     *     hostname: string,
     *     status: string,
     *     verified: bool,
     *     misconfigured: bool|null,
     *     configured_by: string|null,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     vercel: array{domain: array<string, mixed>, config: array<string, mixed>|null}
     * }
     */
    public function getDomainStatus(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);
        $this->assertConfigured();

        // Refresh ownership verification when possible.
        $this->request('post', $this->projectDomainsPath($hostname).'/verify');

        $domainResponse = $this->request('get', $this->projectDomainsPath($hostname));

        if ($domainResponse->status() === HttpResponse::HTTP_NOT_FOUND) {
            $this->fail(
                HttpResponse::HTTP_NOT_FOUND,
                __('messages.domain_not_found_on_vercel'),
                'Domain is not connected to the Vercel project.',
            );
        }

        if (! $domainResponse->successful()) {
            $this->failFromVercelResponse(
                $domainResponse,
                __('messages.vercel_domain_status_failed'),
            );
        }

        /** @var array<string, mixed> $vercelDomain */
        $vercelDomain = $domainResponse->json() ?? [];
        $verified = (bool) ($vercelDomain['verified'] ?? false);

        $configResponse = $this->request(
            'get',
            '/v6/domains/'.rawurlencode($hostname).'/config',
            query: [
                'projectIdOrName' => (string) config('services.vercel.project_id'),
            ],
        );

        $vercelConfig = $configResponse->successful()
            ? ($configResponse->json() ?? [])
            : null;

        $misconfigured = is_array($vercelConfig)
            ? (bool) ($vercelConfig['misconfigured'] ?? true)
            : null;

        $configuredBy = is_array($vercelConfig)
            ? ($vercelConfig['configuredBy'] ?? null)
            : null;

        $status = $this->resolveStatus($verified, $misconfigured);

        return [
            'hostname' => (string) ($vercelDomain['name'] ?? $hostname),
            'status' => $status,
            'verified' => $verified,
            'misconfigured' => $misconfigured,
            'configured_by' => is_string($configuredBy) ? $configuredBy : null,
            'dns_records' => $this->buildDnsRecords($vercelDomain, is_array($vercelConfig) ? $vercelConfig : []),
            'vercel' => [
                'domain' => $vercelDomain,
                'config' => $vercelConfig,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $vercelDomain
     * @return array{
     *     hostname: string,
     *     verified: bool,
     *     dns_records: list<array{purpose: string, type: string, name: string, value: string}>,
     *     vercel: array<string, mixed>
     * }
     */
    private function formatConnectResponse(string $hostname, array $vercelDomain): array
    {
        return [
            'hostname' => (string) ($vercelDomain['name'] ?? $hostname),
            'verified' => (bool) ($vercelDomain['verified'] ?? false),
            'dns_records' => $this->buildDnsRecords($vercelDomain),
            'vercel' => $vercelDomain,
        ];
    }

    private function resolveStatus(bool $verified, ?bool $misconfigured): string
    {
        if (! $verified) {
            return 'pending_verification';
        }

        if ($misconfigured === true) {
            return 'pending_dns';
        }

        if ($misconfigured === false) {
            return 'active';
        }

        return 'pending_dns';
    }

    /**
     * @param  array<string, mixed>  $vercelDomain
     * @param  array<string, mixed>  $vercelConfig
     * @return list<array{purpose: string, type: string, name: string, value: string}>
     */
    private function buildDnsRecords(array $vercelDomain, array $vercelConfig = []): array
    {
        $records = [];
        $hostname = strtolower((string) ($vercelDomain['name'] ?? ''));
        $apexName = strtolower((string) ($vercelDomain['apexName'] ?? $hostname));

        if ($hostname !== '' && $apexName !== '') {
            if ($hostname === $apexName) {
                $aRecordIp = $this->recommendedARecordIp($vercelConfig)
                    ?: (string) config('services.vercel.a_record_ip');

                if (filled($aRecordIp)) {
                    $records[] = [
                        'purpose' => 'point_traffic',
                        'type' => 'A',
                        'name' => '@',
                        'value' => $aRecordIp,
                    ];
                }
            } else {
                $cnameTarget = $this->recommendedCnameTarget($vercelConfig)
                    ?: rtrim((string) config('services.vercel.cname_target'), '.');

                if (filled($cnameTarget)) {
                    $suffix = '.'.$apexName;
                    $name = Str::endsWith($hostname, $suffix)
                        ? substr($hostname, 0, -strlen($suffix))
                        : $hostname;

                    $records[] = [
                        'purpose' => 'point_traffic',
                        'type' => 'CNAME',
                        'name' => $name !== '' ? $name : '@',
                        'value' => $cnameTarget,
                    ];
                }
            }
        }

        foreach ($vercelDomain['verification'] ?? [] as $challenge) {
            if (! is_array($challenge)) {
                continue;
            }

            $type = strtoupper((string) ($challenge['type'] ?? 'TXT'));
            $domain = strtolower((string) ($challenge['domain'] ?? ''));
            $value = (string) ($challenge['value'] ?? '');

            if ($domain === '' || $value === '') {
                continue;
            }

            $name = $domain;
            if ($apexName !== '' && Str::endsWith($domain, '.'.$apexName)) {
                $name = substr($domain, 0, -strlen('.'.$apexName));
            } elseif ($domain === $apexName) {
                $name = '@';
            }

            $records[] = [
                'purpose' => 'domain_verification',
                'type' => $type,
                'name' => $name !== '' ? $name : '@',
                'value' => $value,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $vercelConfig
     */
    private function recommendedCnameTarget(array $vercelConfig): ?string
    {
        $recommended = $vercelConfig['recommendedCNAME'] ?? null;

        if (! is_array($recommended) || $recommended === []) {
            return null;
        }

        usort(
            $recommended,
            fn (mixed $left, mixed $right): int => (int) (is_array($left) ? ($left['rank'] ?? 999) : 999)
                <=> (int) (is_array($right) ? ($right['rank'] ?? 999) : 999),
        );

        $first = $recommended[0] ?? null;
        $value = is_array($first) ? ($first['value'] ?? null) : null;

        return is_string($value) && $value !== ''
            ? rtrim($value, '.')
            : null;
    }

    /**
     * @param  array<string, mixed>  $vercelConfig
     */
    private function recommendedARecordIp(array $vercelConfig): ?string
    {
        $recommended = $vercelConfig['recommendedIPv4'] ?? null;

        if (! is_array($recommended) || $recommended === []) {
            return null;
        }

        usort(
            $recommended,
            fn (mixed $left, mixed $right): int => (int) (is_array($left) ? ($left['rank'] ?? 999) : 999)
                <=> (int) (is_array($right) ? ($right['rank'] ?? 999) : 999),
        );

        $first = $recommended[0] ?? null;
        $value = is_array($first) ? ($first['value'] ?? null) : null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, scalar|null>  $query
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
    ): Response {
        $url = rtrim((string) config('services.vercel.base_url'), '/').$path;
        $teamId = config('services.vercel.team_id');

        if (filled($teamId)) {
            $query['teamId'] = (string) $teamId;
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $pending = Http::withToken((string) config('services.vercel.token'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);

        return match (strtolower($method)) {
            'get' => $pending->get($url),
            'post' => $pending->post($url, $body ?? []),
            'delete' => $pending->delete($url),
            default => $this->fail(
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.vercel_request_failed'),
                'Unsupported Vercel HTTP method.',
            ),
        };
    }

    private function projectDomainsPath(?string $hostname = null): string
    {
        $projectId = rawurlencode((string) config('services.vercel.project_id'));

        if ($hostname === null) {
            return "/v10/projects/{$projectId}/domains";
        }

        return '/v9/projects/'.$projectId.'/domains/'.rawurlencode($hostname);
    }

    private function assertConfigured(): void
    {
        if (! filled(config('services.vercel.token')) || ! filled(config('services.vercel.project_id'))) {
            $this->fail(
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.vercel_not_configured'),
                'Missing VERCEL_TOKEN or VERCEL_PROJECT_ID.',
            );
        }
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#', '', $hostname) ?? $hostname;
        $hostname = rtrim($hostname, '/');

        return $hostname;
    }

    private function isAlreadyOnProject(Response $response): bool
    {
        if (! in_array($response->status(), [400, 409], true)) {
            return false;
        }

        $payload = strtolower(json_encode($response->json()) ?: $response->body());

        return str_contains($payload, 'already')
            || str_contains($payload, 'exist');
    }

    private function failFromVercelResponse(Response $response, ?string $message = null): never
    {
        $json = $response->json();
        $error = null;

        if (is_array($json)) {
            $error = $json['error']['message']
                ?? $json['error']['code']
                ?? $json['message']
                ?? json_encode($json);
        }

        if (! is_string($error) || $error === '') {
            $error = $response->body() !== '' ? $response->body() : 'Vercel request failed.';
        }

        $status = $response->status();
        if ($status < 400 || $status > 599) {
            $status = HttpResponse::HTTP_BAD_GATEWAY;
        }

        $this->fail(
            $status,
            $message ?? __('messages.vercel_connect_domain_failed'),
            $error,
        );
    }
}
