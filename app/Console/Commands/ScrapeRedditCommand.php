<?php

namespace App\Console\Commands;

use App\Spiders\RedditSearchSpider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class ScrapeRedditCommand extends Command
{
    protected $signature = 'scrape:reddit 
                          {query : Search query}
                          {--subreddit= : Specific subreddit to search in}
                          {--time=week : Time filter (hour, day, week, month, year, all)}
                          {--sort=relevance : Sort by (relevance, hot, top, new, comments)}';

    protected $description = 'Scrape Reddit search results';

    public function handle()
    {
        $query = $this->argument('query');
        $subreddit = $this->option('subreddit');
        $time = $this->option('time');
        $sort = $this->option('sort');

        // Build URL
        $url = $this->buildUrl($query, $subreddit, $time, $sort);

        $this->info("Starting Reddit scraping for query: '{$query}'");
        $this->info("URL: {$url}");
        $this->line('');

        try {
            // Configure and run spider
            Roach::startSpider(RedditSearchSpider::class, new Overrides(
                startUrls: [$url]
            ));

            $this->info('✅ Scraping completed successfully!');
            $this->info('📁 Results saved to: storage/app/reddit_posts_'.date('Y-m-d').'.json');
            $this->info('📋 Check logs for detailed processing information');
        } catch (\Exception $e) {
            $this->error('❌ Scraping failed: '.$e->getMessage());
            Log::error('Reddit scraping failed', [
                'query' => $query,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function buildUrl(string $query, ?string $subreddit, string $time, string $sort): string
    {
        $encodedQuery = urlencode($query);

        if ($subreddit) {
            return "https://www.reddit.com/svc/shreddit/r/{$subreddit}/search/?q={$encodedQuery}&type=posts&t={$time}&sort={$sort}";
        } else {
            return "https://www.reddit.com/svc/shreddit/search/?q={$encodedQuery}&type=posts&sort={$sort}&t={$time}";
        }
    }
}
