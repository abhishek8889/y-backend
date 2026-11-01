<?php

namespace App\Http\Controllers\Api\Webhooks\Vercel;

use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\SiteDomain;
use App\Services\VercelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Vercel domain webhooks (POST /api/vercel/webhooks).
 *
 * Handled events:
 * - project.domain.verified
 * - project.domain.unverified
 * - project.domain.created
 * - project.domain.updated
 * - project.domain.deleted
 * - domain.certificate.add / domain.certificate-add
 * - domain.certificate.add.failed / domain.certificate-add-failed
 */
class VercelWebhook extends Controller
{
    public function __construct(
        private readonly VercelService $vercel,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->parseEvent(
                $request->getContent(),
                (string) $request->header('x-vercel-signature', ''),
            );

            Log::channel('vercel_webhook')->info('Vercel webhook received', [
                'event_id' => $event['id'],
                'type' => $event['type'],
            ]);

            $handled = $this->dispatch($event);

            Log::channel('vercel_webhook')->info('Vercel webhook handled', [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'handled' => $handled,
            ]);

            return $this->success(__('messages.vercel_webhook_received'), [
                'type' => $event['type'],
                'handled' => $handled,
            ]);
        } catch (Throwable $exception) {
            Log::channel('vercel_webhook')->error('Vercel webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failed($exception);
        }
    }

    /**
     * @param  array{id: string, type: string, payload: array<string, mixed>}  $event
     */
    private function dispatch(array $event): bool
    {
        $type = $event['type'];
        $hostname = $this->extractHostname($event['payload']);

        return match ($type) {
            'project.domain.verified',
            'project.domain.created',
            'project.domain.updated',
            'domain.certificate.add',
            'domain.certificate-add' => $this->syncDomainFromVercel($hostname),
            'project.domain.unverified' => $this->markDomainUnverified($hostname),
            'domain.certificate.add.failed',
            'domain.certificate-add-failed' => $this->markCertificateFailed($hostname, $event['payload']),
            'project.domain.deleted' => $this->markDomainDeleted($hostname),
            default => false,
        };
    }

    private function syncDomainFromVercel(?string $hostname): bool
    {
        if ($hostname === null) {
            return false;
        }

        $domain = $this->findDomain($hostname);

        if ($domain === null) {
            return false;
        }

        $status = $this->vercel->getDomainStatus($hostname);

        $domainStatus = match ($status['status']) {
            'active' => SiteDomainStatusEnum::ACTIVE,
            'pending_verification' => SiteDomainStatusEnum::PENDING_VERIFICATION,
            'pending_dns' => SiteDomainStatusEnum::PENDING_DNS,
            default => SiteDomainStatusEnum::PENDING_DNS,
        };

        $domain->fill([
            'status' => $domainStatus,
            'vercel_added' => true,
            'dns_instructions' => $status['dns_records'] ?? $domain->dns_instructions,
            'verified_at' => ! empty($status['verified'])
                ? ($domain->verified_at ?? now())
                : $domain->verified_at,
            'activated_at' => $domainStatus === SiteDomainStatusEnum::ACTIVE
                ? ($domain->activated_at ?? now())
                : null,
            'last_error' => null,
        ]);
        $domain->save();

        if ($domainStatus === SiteDomainStatusEnum::ACTIVE) {
            $domain->site?->update([
                'status' => SiteStatusEnum::ACTIVE,
            ]);
        }

        return true;
    }

    private function markDomainUnverified(?string $hostname): bool
    {
        if ($hostname === null) {
            return false;
        }

        $domain = $this->findDomain($hostname);

        if ($domain === null) {
            return false;
        }

        $domain->update([
            'status' => SiteDomainStatusEnum::PENDING_VERIFICATION,
            'activated_at' => null,
            'last_error' => 'Domain became unverified on Vercel.',
        ]);

        $domain->site?->update([
            'status' => SiteStatusEnum::DRAFT,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markCertificateFailed(?string $hostname, array $payload): bool
    {
        if ($hostname === null) {
            return false;
        }

        $domain = $this->findDomain($hostname);

        if ($domain === null) {
            return false;
        }

        $error = $payload['error']['message']
            ?? $payload['message']
            ?? 'SSL certificate issuance failed on Vercel.';

        $domain->update([
            'status' => SiteDomainStatusEnum::FAILED,
            'activated_at' => null,
            'last_error' => is_string($error) ? $error : 'SSL certificate issuance failed on Vercel.',
        ]);

        $domain->site?->update([
            'status' => SiteStatusEnum::DRAFT,
        ]);

        return true;
    }

    private function markDomainDeleted(?string $hostname): bool
    {
        if ($hostname === null) {
            return false;
        }

        $domain = $this->findDomain($hostname);

        if ($domain === null) {
            return false;
        }

        $domain->update([
            'status' => SiteDomainStatusEnum::FAILED,
            'vercel_added' => false,
            'activated_at' => null,
            'last_error' => 'Domain was deleted from Vercel.',
        ]);

        $domain->site?->update([
            'status' => SiteStatusEnum::DRAFT,
        ]);

        return true;
    }

    private function findDomain(string $hostname): ?SiteDomain
    {
        $hostname = strtolower(trim($hostname));

        $domain = SiteDomain::query()
            ->with('site')
            ->where('hostname', $hostname)
            ->first();

        if ($domain === null) {
            Log::channel('vercel_webhook')->warning('Local site domain not found for Vercel webhook', [
                'hostname' => $hostname,
            ]);
        }

        return $domain;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractHostname(array $payload): ?string
    {
        $domain = $payload['domain'] ?? null;

        if (is_string($domain) && $domain !== '') {
            return strtolower(trim($domain));
        }

        if (is_array($domain) && filled($domain['name'] ?? null)) {
            return strtolower(trim((string) $domain['name']));
        }

        if (filled($payload['name'] ?? null) && is_string($payload['name'])) {
            return strtolower(trim($payload['name']));
        }

        return null;
    }

    /**
     * @return array{id: string, type: string, payload: array<string, mixed>}
     */
    private function parseEvent(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('services.vercel.webhook_secret', '');

        if ($secret === '') {
            throw new ServiceException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                __('messages.vercel_webhook_not_configured'),
            );
        }

        if ($signatureHeader === '') {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.vercel_webhook_invalid_signature'),
            );
        }

        $expected = hash_hmac('sha1', $payload, $secret);

        if (! hash_equals($expected, $signatureHeader)) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.vercel_webhook_invalid_signature'),
            );
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! filled($decoded['type'] ?? null)) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.vercel_webhook_invalid_payload'),
            );
        }

        $eventPayload = $decoded['payload'] ?? [];
        if (! is_array($eventPayload)) {
            $eventPayload = [];
        }

        return [
            'id' => (string) ($decoded['id'] ?? $decoded['createdAt'] ?? uniqid('vercel_', true)),
            'type' => (string) $decoded['type'],
            'payload' => $eventPayload,
        ];
    }
}
