<?php

namespace App\Jobs;

use App\Models\LastFetch;
use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use App\Models\RedditMention;
use App\Services\RedditTokenService;
use App\Services\SentimentIntentAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSingleRedditPost implements ShouldQueue
{
    use Queueable;

    public $timeout = 240;

    protected $sentimentService;

    protected $tokenService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $credentialId,
        protected int $keywordId,
        protected string $postId,
        protected ?string $subreddit = null
    ) {
        $this->tokenService = new RedditTokenService;
        $this->sentimentService = new SentimentIntentAnalysisService;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $credential = RedditCredential::find($this->credentialId);
            $keyword = RedditKeyword::find($this->keywordId);

            if (! $credential) {
                return;
            }

            if (! $keyword) {
                return;
            }

            $postData = $this->fetchPostFromRedditApi($credential, $this->postId);

            if (! $postData) {
                return;
            }

            $result = $this->processPost($credential, $keyword, $postData);

            if ($result) {
                $this->updateLastFetch($credential);
            }
        } catch (\Exception $e) {
            Log::error('Error in ProcessSingleRedditPost job', [
                'credential_id' => $this->credentialId,
                'keyword_id' => $this->keywordId,
                'post_id' => $this->postId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch post details from Reddit API.
     */
    protected function fetchPostFromRedditApi(RedditCredential $credential, string $postId): ?array
    {
        try {
            // Get OAuth token
            $accessToken = $this->tokenService->getValidAccessToken($credential);
            if (! $accessToken) {
                Log::error('Failed to get Reddit access token', [
                    'credential_id' => $credential->id,
                    'post_id' => $postId,
                ]);

                return null;
            }

            $fullPostId = str_starts_with($postId, 't3_') ? $postId : 't3_'.$postId;

            $url = 'https://oauth.reddit.com/api/info';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            ])->timeout(30)->get($url, [
                'id' => $fullPostId,
            ]);

            if (! $response->successful()) {
                Log::error('Reddit API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'post_id' => $postId,
                    'full_post_id' => $fullPostId,
                    'url' => $url,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['data']['children']) || ! is_array($data['data']['children'])) {
                Log::warning('Invalid Reddit API response structure', [
                    'post_id' => $postId,
                    'full_post_id' => $fullPostId,
                    'response_keys' => array_keys($data),
                ]);

                return null;
            }

            if (empty($data['data']['children'])) {
                return null;
            }

            $post = $data['data']['children'][0];

            if (! isset($post['kind']) || $post['kind'] !== 't3') {
                return null;
            }

            if (isset($post['data']['id'])) {
                $returnedId = $post['data']['id'];
                $expectedId = str_replace('t3_', '', $fullPostId);

                if ($returnedId !== $expectedId) {
                    return null;
                }
            }

            return $post;
        } catch (\Exception $e) {
            Log::error('Exception while fetching post from Reddit API', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Process a single post.
     */
    protected function processPost(RedditCredential $credential, RedditKeyword $keyword, array $post): bool
    {
        try {

            $keyword->load('persona');

            if (! isset($post['data'])) {
                return false;
            }

            $postData = $post['data'];

            if (empty($postData['id'])) {
                return false;
            }

            $postId = $postData['id'];

            if (RedditMention::where('reddit_post_id', $postId)->exists()) {
                RedditMention::query()->where('reddit_post_id', $postId)->update([
                    'reddit_keyword_id' => $keyword->id,
                ]);

                return false;
            }

            // Handle title and content separately
            $title = ! empty($postData['title']) ? trim($postData['title']) : null;
            $selftext = ! empty($postData['selftext']) ? trim($postData['selftext']) : null;

            // Create content for analysis by combining title and selftext
            $analysisContent = '';
            if ($title) {
                $analysisContent .= $title;
            }
            if ($selftext) {
                $analysisContent .= ($title ? ' ' : '').$selftext;
            }

            $analysisContent = trim($analysisContent);

            if (empty($analysisContent)) {
                return false;
            }

            if (! $selftext) {
                return false;
            }

            if (! $this->keywordMatches($keyword, $analysisContent)) {
                return false;
            }

            try {
                $analysis = $this->sentimentService->analyzeSentimentIntentAndReply($analysisContent, $keyword->keyword, $keyword->persona->settings, $keyword->persona->user_type);
            } catch (\Exception $e) {
                Log::warning('Sentiment analysis failed, using defaults', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                ]);

                $analysis = [
                    'sentiment' => 'neutral',
                    'sentiment_confidence' => 0.0,
                    'intent' => 'irrelevant',
                    'intent_confidence' => 0.0,
                    'suggested_reply' => 'Thank you for sharing. Feel free to reach out if you have any questions or need assistance.',
                ];
            }

            $mentionData = [
                'user_id' => $credential->user_id,
                'reddit_keyword_id' => $keyword->id,
                'reddit_post_id' => $postId,
                'reddit_comment_id' => null,
                'keyword' => $keyword->keyword,
                'subreddit' => $postData['subreddit'] ?? '',
                'author' => $postData['author'] ?? '',
                'title' => $title ? $this->truncateContent($title, 500) : null, // Separate title field
                'content' => $selftext ? $this->truncateContent($selftext) : null, // Only selftext in content
                'url' => 'https://reddit.com'.($postData['permalink'] ?? ''),
                'mention_type' => 'post',
                'upvotes' => $postData['ups'] ?? 0,
                'downvotes' => $postData['downs'] ?? 0,
                'comment_count' => $postData['num_comments'] ?? 0,
                'is_stickied' => $postData['stickied'] ?? false,
                'is_locked' => $postData['locked'] ?? false,
                'sentiment' => $analysis['sentiment'],
                'sentiment_confidence' => $analysis['sentiment_confidence'],
                'intent' => $analysis['intent'],
                'intent_confidence' => $analysis['intent_confidence'],
                'suggested_reply' => $this->truncateContent($analysis['suggested_reply'], 1000), // Add suggested reply field
                'reddit_created_at' => isset($postData['created_utc'])
                    ? Carbon::createFromTimestamp($postData['created_utc'])
                    : now(),
                'found_at' => now(),
                'persona' => $keyword->persona->settings,
            ];

            RedditMention::create($mentionData);

            Log::info('Reddit mention processed successfully', [
                'post_id' => $postId,
                'keyword_id' => $keyword->id,
                'sentiment' => $analysis['sentiment'],
                'intent' => $analysis['intent'],
                'has_title' => ! is_null($title),
                'has_content' => ! is_null($selftext),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing individual post', [
                'post_id' => $postData['id'] ?? 'unknown',
                'keyword_id' => $keyword->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function keywordMatches(RedditKeyword $keyword, string $content): bool
    {
        try {
            $searchTerm = trim($keyword->keyword);
            $searchContent = $content;

            if (empty($searchTerm)) {
                return false;
            }

            if (! $keyword->case_sensitive) {
                $searchTerm = strtolower($searchTerm);
                $searchContent = strtolower($searchContent);
            }

            $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);

            if ($keyword->match_whole_word) {
                $pattern = '/\b('.preg_quote($searchTerm, '/').')\b/u';
                $result = preg_match($pattern, $searchContent);

                if ($result === false) {
                    Log::error('Regex error in keywordMatches', [
                        'keyword_id' => $keyword->id,
                        'pattern' => $pattern,
                        'preg_last_error' => preg_last_error(),
                    ]);

                    return false;
                }

                return $result === 1;
            }

            $terms = explode(' ', $searchTerm);
            foreach ($terms as $term) {
                if (strpos($searchContent, $term) !== false) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error in keywordMatches method', [
                'keyword_id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Truncate content to fit database constraints.
     */
    protected function truncateContent(string $content, int $maxLength = 2000): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength - 3).'...';
    }

    /**
     * Update last fetch timestamp.
     */
    protected function updateLastFetch(RedditCredential $credential): void
    {
        try {
            LastFetch::query()->updateOrCreate([
                'user_id' => $credential->user_id,
                'reddit_credential_id' => $credential->id,
            ], [
                'last_fetched_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update LastFetch record', [
                'user_id' => $credential->user_id,
                'reddit_credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw here as it's not critical
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSingleRedditPost job failed', [
            'credential_id' => $this->credentialId,
            'keyword_id' => $this->keywordId,
            'post_id' => $this->postId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
