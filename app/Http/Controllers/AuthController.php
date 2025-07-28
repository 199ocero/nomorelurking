<?php

namespace App\Http\Controllers;

use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends Controller
{
    /**
     * Redirect to Reddit for authentication
     */
    public function redirectToReddit(Request $request)
    {
        try {
            return Socialite::driver('reddit')
                ->scopes(['identity', 'read'])
                ->with(['duration' => 'permanent'])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Reddit OAuth Redirect Error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return redirect(route('mentions'))->with('error', 'Failed to initiate Reddit authentication.');
        }
    }

    /**
     * Handle Reddit callback
     */
    public function handleRedditCallback(Request $request)
    {
        try {
            if (! Auth::check()) {
                return redirect(route('login'))->with('error', 'You must be logged in to connect your Reddit account.');
            }

            $user = Auth::user();

            if ($request->has('error')) {
                $errorDescription = $request->get('error_description', $request->get('error'));
                throw new \Exception('Reddit authentication failed: '.$errorDescription);
            }

            if (! $request->has('code')) {
                throw new \Exception('No authorization code received from Reddit');
            }

            try {
                $redditUser = Socialite::driver('reddit')->user();
            } catch (InvalidStateException $e) {
                Log::error('Invalid state exception during Reddit user fetch', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
                throw new \Exception('Authentication state error. Please try again.');
            } catch (ClientException $e) {
                Log::error('HTTP client error during Reddit user fetch', [
                    'error' => $e->getMessage(),
                    'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null,
                    'user_id' => $user->id,
                ]);
                throw new \Exception('Failed to retrieve user data from Reddit. Please try again.');
            } catch (\Exception $e) {
                Log::error('General error during Reddit user fetch', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
                throw new \Exception('Unexpected error retrieving Reddit user data.');
            }

            if (! $redditUser) {
                throw new \Exception('No user data received from Reddit');
            }

            $redditId = $redditUser->getId();
            $redditName = $redditUser->getName() ?? $redditUser->getNickname();

            if (! $redditId) {
                throw new \Exception('No Reddit user ID received');
            }

            if (! $redditName) {
                throw new \Exception('No Reddit username received');
            }

            if (! $redditUser->token) {
                throw new \Exception('No access token received from Reddit');
            }

            $existingCredential = RedditCredential::where('reddit_id', $redditId)
                ->where('user_id', '!=', $user->id)
                ->first();

            if ($existingCredential) {
                throw new \Exception('This Reddit account is already connected to another user.');
            }

            $tokenExpiresAt = null;
            if ($redditUser->expiresIn) {
                $tokenExpiresAt = now()->addSeconds($redditUser->expiresIn);
            }

            $redditCredential = RedditCredential::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'reddit_id' => $redditId,
                ],
                [
                    'username' => $redditName,
                    'access_token' => Crypt::encryptString($redditUser->token),
                    'refresh_token' => $redditUser->refreshToken ? Crypt::encryptString($redditUser->refreshToken) : null,
                    'expires_in' => $redditUser->expiresIn,
                    'token_expires_at' => $tokenExpiresAt,
                    'connected_at' => now(),
                ]
            );

            if ($redditCredential->reddit_id) {
                $redditKeyword = RedditKeyword::where('reddit_id', $redditCredential->reddit_id)->exists();

                if ($redditKeyword) {
                    RedditKeyword::where('reddit_id', $redditCredential->reddit_id)->update([
                        'user_id' => $user->id,
                        'reddit_credential_id' => $redditCredential->id,
                    ]);
                }
            }

            return redirect(route('mentions'))->with('success', 'Reddit account connected successfully!');
        } catch (\Exception $e) {
            Log::error('Reddit OAuth Error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $userMessage = $e->getMessage();
            if (empty($userMessage) || str_contains($userMessage, 'cURL error')) {
                $userMessage = 'Network error occurred while connecting to Reddit. Please check your internet connection and try again.';
            }

            return redirect(route('mentions'))->with('error', 'Failed to connect Reddit account: '.$userMessage);
        }
    }

    /**
     * Disconnect Reddit account (without revoking on Reddit's side)
     */
    public function disconnectReddit()
    {
        try {
            $user = Auth::user();

            if ($user && $user->redditCredential) {
                $user->redditCredential->delete();
            }

            return redirect(route('mentions'))->with('success', 'Reddit account disconnected successfully.');
        } catch (\Exception $e) {
            Log::error('Disconnect Reddit Failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to disconnect Reddit account.');
        }
    }
}
