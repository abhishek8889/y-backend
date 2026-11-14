<?php

namespace App\Console\Commands;

use App\Enum\NewsStatusEnum;
use App\Models\News;
use Illuminate\Console\Command;

class PublishDueNewsCommand extends Command
{
    protected $signature = 'news:publish-due';

    protected $description = 'Flip scheduled news to published when published_at is due';

    public function handle(): int
    {
        $updated = News::query()
            ->where('status', NewsStatusEnum::SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update([
                'status' => NewsStatusEnum::PUBLISHED->value,
            ]);

        $this->info("Published {$updated} scheduled news item(s).");

        return self::SUCCESS;
    }
}
