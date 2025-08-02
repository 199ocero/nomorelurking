<?php

namespace App\Http\Controllers;

use App\Jobs\MonitorRedditKeywords;
use App\Models\LastFetch;
use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use App\Models\RedditMention;
use App\Services\RedditTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MentionController extends Controller
{
    protected $tokenService;

    public function __construct(RedditTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function index(Request $request)
    {

        // Get all credentials for the user
        $credentials = RedditCredential::where('user_id', Auth::id())
            ->select('id', 'username', 'reddit_id', 'token_expires_at')
            ->first();

        // Get all keywords for the user with only the needed fields
        $keywords = null;

        $mentions = null;

        $lastFetch = null;

        if ($credentials) {
            $keywords = RedditKeyword::query()
                ->where('user_id', Auth::id())
                ->where('reddit_id', $credentials->reddit_id)
                ->latest()
                ->select([
                    'id',
                    'user_id',
                    'reddit_credential_id',
                    'reddit_id',
                    'keyword',
                    'subreddits',
                    'scan_comments',
                    'match_whole_word',
                    'case_sensitive',
                    'alert_enabled',
                    'alert_methods',
                    'alert_min_upvotes',
                    'alert_sentiment',
                    'last_checked_at',
                ])
                ->get();

            $mentions = RedditMention::query()
                ->with('keyword:id,keyword')
                ->where('user_id', Auth::id())
                ->orderByDesc('reddit_created_at')
                ->select([
                    'id',
                    'user_id',
                    'reddit_keyword_id',
                    'reddit_post_id',
                    'reddit_comment_id',
                    'subreddit',
                    'author',
                    'title',
                    'content',
                    'url',
                    'mention_type',
                    'upvotes',
                    'downvotes',
                    'comment_count',
                    'is_stickied',
                    'is_locked',
                    'sentiment',
                    'sentiment_confidence',
                    'intent',
                    'intent_confidence',
                    'suggested_reply',
                    'reddit_created_at',
                    'found_at',
                ])
                ->get();

            $lastFetch = LastFetch::where('user_id', Auth::id())
                ->where('reddit_credential_id', $credentials->id)
                ->select([
                    'id',
                    'user_id',
                    'reddit_credential_id',
                    'dispatch_at',
                    'last_fetched_at',
                ])
                ->first();
        }

        return Inertia::render('Mentions', [
            'keywords' => $keywords ?? [],
            'mentions' => $mentions ?? [],
            'credentials' => $credentials ? [
                'id' => $credentials->id,
                'reddit_id' => $credentials->reddit_id,
                'username' => $credentials->username,
                'token_expires_at' => $credentials->token_expires_at?->toISOString(),
            ] : [],
            'dispatch_at' => $lastFetch && $lastFetch->dispatch_at?->toISOString() ?? null,
            'last_fetched_at' => $lastFetch && $lastFetch->last_fetched_at
                ? $lastFetch->last_fetched_at->toISOString()
                : null,

        ]);
    }

    /**
     * Store a newly created keyword in storage.
     */
    public function storeKeyword(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'reddit_credential_id' => [
                'required',
                'integer',
                Rule::exists('reddit_credentials', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                }),
            ],
            'subreddits' => 'nullable|array',
            'subreddits.*' => 'string|max:255',
            'scan_comments' => 'boolean',
            'match_whole_word' => 'boolean',
            'case_sensitive' => 'boolean',
            'reddit_id' => 'string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        RedditKeyword::create($validated);

        return redirect()->back()->with('success', 'Keyword created successfully!');
    }

    /**
     * Start monitoring for mentions.
     */
    public function startMonitoring()
    {
        $credential = RedditCredential::where('user_id', Auth::id())
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => 'Reddit credential not found for this user.',
            ], 404);
        }

        $activeKeywordsCount = RedditKeyword::where('reddit_credential_id', $credential->id)->count();

        if ($activeKeywordsCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No active keywords found to monitor.',
            ], 422);
        }

        try {
            MonitorRedditKeywords::dispatch($credential->user_id, $credential->reddit_id)
                ->onQueue('reddit-monitoring');

            LastFetch::query()->updateOrCreate([
                'user_id' => $credential->user_id,
                'reddit_credential_id' => $credential->id,
            ], [
                'dispatch_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reddit keyword monitoring started successfully.',
                'data' => [
                    'user_id' => $credential->user_id,
                    'reddit_id' => $credential->reddit_id,
                    'keywords_count' => $activeKeywordsCount,
                    'dispatched_at' => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch Reddit monitoring job: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to start monitoring. Please try again later.',
            ], 500);
        }
    }

    /**
     * Update the specified keyword in storage.
     */
    public function updateKeyword(Request $request, RedditKeyword $keyword)
    {
        // Ensure the user owns this keyword
        if ($keyword->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'reddit_credential_id' => [
                'required',
                'integer',
                Rule::exists('reddit_credentials', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                }),
            ],
            'subreddits' => 'nullable|array',
            'subreddits.*' => 'string|max:255',
            'scan_comments' => 'boolean',
            'match_whole_word' => 'boolean',
            'case_sensitive' => 'boolean',
        ]);

        $keyword->update($validated);

        return redirect()->back()->with('success', 'Keyword updated successfully!');
    }

    /**
     * Remove the specified keyword from storage.
     */
    public function destroyKeyword(RedditKeyword $keyword)
    {
        // Ensure the user owns this keyword
        if ($keyword->user_id !== Auth::id()) {
            abort(403);
        }

        $keyword->delete();

        return redirect()->back()->with('success', 'Keyword deleted successfully!');
    }

    /**
     * Search for subreddits using Reddit API.
     */
    public function searchSubreddits(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1|max:100',
            'credential_id' => [
                'required',
                'integer',
                Rule::exists('reddit_credentials', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                }),
            ],
        ]);

        $credential = RedditCredential::where('id', $validated['credential_id'])
            ->where('user_id', Auth::id())
            ->first();

        if (! $credential) {
            return response()->json(['error' => 'Credential not found'], 404);
        }

        // Check if token needs refreshing
        if ($this->tokenService->tokenNeedsRefresh($credential)) {
            $refreshed = $this->tokenService->refreshAccessToken($credential);
            if (! $refreshed) {
                return response()->json(['error' => 'Unable to refresh access token'], 401);
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.Crypt::decryptString($credential->access_token),
                'User-Agent' => config('services.reddit.user_agent', 'NoMoreLurking/1.0'),
            ])->get('https://oauth.reddit.com/subreddits/search', [
                'q' => $validated['query'],
                'type' => 'sr',
                'limit' => 10,
                'include_over_18' => false,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $subreddits = collect($data['data']['children'] ?? [])
                    ->map(function ($item) {
                        $subreddit = $item['data'];

                        return [
                            'name' => $subreddit['display_name'],
                            'subscribers' => $subreddit['subscribers'] ?? 0,
                            'description' => $subreddit['public_description'] ?? '',
                        ];
                    })
                    ->filter(function ($subreddit) {
                        // Filter out banned or quarantined subreddits
                        return ! empty($subreddit['name']);
                    })
                    ->values();

                return response()->json(['subreddits' => $subreddits]);
            }

            return response()->json(['error' => 'Failed to search subreddits'], 500);
        } catch (\Exception $e) {
            Log::error('Reddit API error: '.$e->getMessage());

            return response()->json(['error' => 'API request failed'], 500);
        }
    }
}
