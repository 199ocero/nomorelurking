<?php

namespace App\Http\Controllers;

use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MentionController extends Controller
{
    public function index(Request $request)
    {

        // Get all credentials for the user
        $credentials = RedditCredential::where('user_id', Auth::id())
            ->select('id', 'username', 'reddit_id', 'token_expires_at')
            ->first();

        // Get all keywords for the user with only the needed fields
        $keywords = null;

        if ($credentials) {
            $keywords = RedditKeyword::with('credential')
                ->where('user_id', Auth::id())
                ->where('reddit_id', $credentials->reddit_id)
                ->latest()
                ->select([
                    'id',
                    'reddit_credential_id',
                    'keyword',
                    'subreddits',
                    'scan_comments',
                    'match_whole_word',
                    'case_sensitive',
                    'is_active',
                    'last_checked_at',
                ])
                ->get();
        }

        return Inertia::render('Mentions', [
            'keywords' => $keywords ?? [],
            'credentials' => $credentials ? [
                'id' => $credentials->id,
                'reddit_id' => $credentials->reddit_id,
                'username' => $credentials->username,
                'token_expires_at' => $credentials->token_expires_at?->toISOString(),
            ] : [],
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
            'is_active' => 'boolean',
            'reddit_id' => 'string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        RedditKeyword::create($validated);

        return redirect()->back()->with('success', 'Keyword created successfully!');
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
            'is_active' => 'boolean',
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
        if ($credential->token_expires_at && $credential->token_expires_at->isPast()) {
            $refreshed = $this->refreshAccessToken($credential);
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

    /**
     * Refresh the access token for a Reddit credential.
     */
    private function refreshAccessToken(RedditCredential $credential)
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.reddit.client_id'),
                    config('services.reddit.client_secret')
                )
                ->post('https://www.reddit.com/api/v1/access_token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => Crypt::decryptString($credential->refresh_token),
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $credential->update([
                    'access_token' => Crypt::encryptString($data['access_token']),
                    'expires_in' => $data['expires_in'],
                    'token_expires_at' => now()->addSeconds($data['expires_in']),
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Token refresh error: '.$e->getMessage());

            return false;
        }
    }
}
