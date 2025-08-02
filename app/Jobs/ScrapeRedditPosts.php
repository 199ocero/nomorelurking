<?php

namespace App\Jobs;

use App\ItemProcessors\RedditPostProcessor;
use App\Spiders\RedditSearchSpider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class ScrapeRedditPosts implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $keyword,
        public int $userId,
        public int $credentialId,
        public int $keywordId,
        public ?string $subreddit = null,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Roach::startSpider(RedditSearchSpider::class, new Overrides(
            startUrls: [$this->buildUrl()],
            itemProcessors: [
                [
                    RedditPostProcessor::class,
                    [
                        'user_id' => $this->userId,
                        'credential_id' => $this->credentialId,
                        'keyword_id' => $this->keywordId,
                    ],
                ],
            ]
        ));
    }

    private function buildUrl(): string
    {
        $encodedQuery = urlencode($this->keyword);

        if ($this->subreddit) {
            return "https://www.reddit.com/svc/shreddit/r/{$this->subreddit}/search/?q={$encodedQuery}&type=posts&sort=relevance&t=week";
        } else {
            return "https://www.reddit.com/svc/shreddit/search/?q={$encodedQuery}&type=posts&sort=relevance&t=week";
        }
    }
}
