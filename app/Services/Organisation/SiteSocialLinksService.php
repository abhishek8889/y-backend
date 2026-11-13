<?php

namespace App\Services\Organisation;

use App\Exceptions\ServiceException;
use App\Models\Site;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteSocialLinksService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
     * @return array{site: Site, social_links: array<string, string>}
     */
    public function show(User $actor, int $siteId): array
    {
        $site = $this->resolveSite($actor, $siteId);

        return [
            'site' => $site,
            'social_links' => $this->normalizeSocialLinks($site->social_links),
        ];
    }

    /**
     * First-time save of social links (fails when links already exist — use update).
     *
     * @param  array{site_id: int, social_links: array<string, mixed>}  $data
     * @return array{site: Site, social_links: array<string, string>}
     */
    public function save(User $actor, array $data): array
    {
        $site = $this->resolveSite($actor, (int) $data['site_id']);
        $existing = $this->normalizeSocialLinks($site->social_links);

        if ($existing !== []) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_social_links_already_saved'),
            );
        }

        return $this->persist($site, $data['social_links'] ?? []);
    }

    /**
     * Replace social links for a site that already has them (or allow first write).
     *
     * @param  array{site_id: int, social_links: array<string, mixed>}  $data
     * @return array{site: Site, social_links: array<string, string>}
     */
    public function update(User $actor, array $data): array
    {
        $site = $this->resolveSite($actor, (int) $data['site_id']);

        return $this->persist($site, $data['social_links'] ?? []);
    }

    /**
     * @param  mixed  $socialLinks
     * @return array{site: Site, social_links: array<string, string>}
     */
    private function persist(Site $site, mixed $socialLinks): array
    {
        $normalized = $this->normalizeSocialLinks($socialLinks);

        try {
            DB::transaction(function () use ($site, $normalized): void {
                $site->social_links = $normalized === [] ? null : $normalized;
                $site->save();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_social_links_save_failed'),
                $exception->getMessage(),
            );
        }

        $site->refresh();

        return [
            'site' => $site,
            'social_links' => $this->normalizeSocialLinks($site->social_links),
        ];
    }

    private function resolveSite(User $actor, int $siteId): Site
    {
        $organisationId = (int) $this->organisation->getOrganisationDetail($actor)['organisation']->id;

        $site = Site::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_not_found'),
            );
        }

        return $site;
    }

    /**
     * Normalize to { platform: url } with lowercase keys; drop empty values.
     *
     * @return array<string, string>
     */
    private function normalizeSocialLinks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $links = [];

        foreach ($value as $platform => $url) {
            if (! is_string($platform) && ! is_int($platform)) {
                continue;
            }

            $key = strtolower(trim((string) $platform));
            if ($key === '' || ! is_string($url)) {
                continue;
            }

            $trimmed = trim($url);
            if ($trimmed === '') {
                continue;
            }

            $links[$key] = $trimmed;
        }

        ksort($links);

        return $links;
    }
}
