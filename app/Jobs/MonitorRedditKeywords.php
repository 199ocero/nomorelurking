<?php

namespace App\Jobs;

use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use App\Services\RedditTokenService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MonitorRedditKeywords implements ShouldQueue
{
    use Batchable, Queueable;

    public $timeout = 300;

    public $tries = 3;

    protected $userId;

    protected $redditId;

    protected $tokenService;

    public function __construct($userId, $redditId)
    {
        $this->userId = $userId;
        $this->redditId = $redditId;
        $this->tokenService = new RedditTokenService;
    }

    public function handle()
    {
        try {
            $credential = RedditCredential::where('user_id', $this->userId)
                ->where('reddit_id', $this->redditId)
                ->first();

            if (! $credential) {
                Log::error("Reddit credential not found for user {$this->userId} and reddit_id {$this->redditId}");

                return;
            }

            $keywords = RedditKeyword::where('reddit_credential_id', $credential->id)->get();

            foreach ($keywords as $keyword) {
                $this->monitorKeyword($credential, $keyword);
            }
        } catch (\Exception $e) {
            Log::error('Error monitoring Reddit keywords: '.$e->getMessage());
            throw $e;
        }
    }

    protected function monitorKeyword(RedditCredential $credential, RedditKeyword $keyword)
    {
        $subreddits = $keyword->subreddits;

        if (empty($subreddits)) {
            ScrapeRedditPosts::dispatch(
                keyword: $keyword->keyword,
                userId: $credential->user_id,
                credentialId: $credential->id,
                keywordId: $keyword->id,
                subreddit: null
            )
                ->onQueue('reddit-processing')
                ->delay(now()->addSeconds(rand(2, 5)));
        } else {
            foreach ($subreddits as $subreddit) {
                ScrapeRedditPosts::dispatch(
                    keyword: $keyword->keyword,
                    userId: $credential->user_id,
                    credentialId: $credential->id,
                    keywordId: $keyword->id,
                    subreddit: $subreddit
                )
                    ->onQueue('reddit-processing')
                    ->delay(now()->addSeconds(rand(2, 5)));
            }
        }

        $keyword->update(['last_checked_at' => now()]);
    }
}
