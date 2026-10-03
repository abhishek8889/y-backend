<?php

namespace App\Console\Commands;

use App\Enum\MediaStorageDriverEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PurgeTmpMediaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:purge-tmp';

    /**
     * @var string
     */
    protected $description = 'Delete orphaned temporary media files older than the configured TTL';

    public function handle(): int
    {
        if (MediaStorageDriverEnum::fromConfig() !== MediaStorageDriverEnum::LOCAL) {
            $this->warn('media:purge-tmp currently supports the local driver only.');

            return self::SUCCESS;
        }

        $diskName = (string) config('media.local_disk', 'public');
        $tmpDirectory = trim((string) config('media.tmp_directory', 'media/tmp'), '/');
        $ttlHours = (int) config('media.tmp_ttl_hours', 24);
        $cutoff = Carbon::now()->subHours($ttlHours)->getTimestamp();

        $disk = Storage::disk($diskName);
        $deleted = 0;

        foreach ($disk->allFiles($tmpDirectory) as $path) {
            $lastModified = $disk->lastModified($path);

            if ($lastModified !== false && $lastModified < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info(__('messages.media_tmp_purged', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
