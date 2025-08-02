<?php

namespace App\Services;

use App\Models\RedditCredential;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedditTokenService
{
    /**
     * Check if the access token needs to be refreshed.
     */
    public function tokenNeedsRefresh(RedditCredential $credential): bool
    {
        return $credential->token_expires_at &&
            $credential->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Refresh the access token for a Reddit credential.
     */
    public function refreshAccessToken(RedditCredential $credential): bool
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.reddit.client_id'),
                    config('services.reddit.client_secret')
                )
                ->withHeaders([
                    'User-Agent' => config('services.reddit.user_agent', config('app.name').'/1.0'),
                ])
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

                Log::info("Successfully refreshed Reddit token for user {$credential->user_id}");

                return true;
            }

            Log::error("Failed to refresh Reddit token: HTTP {$response->status()}, Response: {$response->body()}");

            return false;
        } catch (Exception $e) {
            Log::error("Token refresh error for user {$credential->user_id}: ".$e->getMessage(), [
                'user_id' => $credential->user_id,
                'reddit_id' => $credential->reddit_id,
                'exception' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    public function getValidAccessToken(RedditCredential $credential): ?string
    {
        if ($this->tokenNeedsRefresh($credential)) {
            if (! $this->refreshAccessToken($credential)) {
                return null;
            }
            // Reload the credential to get the updated token
            $credential->refresh();
        }

        try {
            return Crypt::decryptString($credential->access_token);
        } catch (Exception $e) {
            Log::error("Failed to decrypt access token for user {$credential->user_id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Check if the credential has valid tokens.
     */
    public function hasValidTokens(RedditCredential $credential): bool
    {
        return ! empty($credential->access_token) &&
            ! empty($credential->refresh_token) &&
            $credential->token_expires_at;
    }

    /**
     * Revoke the access token (logout).
     */
    public function revokeToken(RedditCredential $credential): bool
    {
        try {
            $accessToken = Crypt::decryptString($credential->access_token);

            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.reddit.client_id'),
                    config('services.reddit.client_secret')
                )
                ->withHeaders([
                    'User-Agent' => config('services.reddit.user_agent', config('app.name').'/1.0'),
                ])
                ->post('https://www.reddit.com/api/v1/revoke_token', [
                    'token' => $accessToken,
                    'token_type_hint' => 'access_token',
                ]);

            if ($response->successful()) {
                // Clear the tokens from the database
                $credential->update([
                    'access_token' => null,
                    'refresh_token' => null,
                    'expires_in' => null,
                    'token_expires_at' => null,
                ]);

                Log::info("Successfully revoked Reddit token for user {$credential->user_id}");

                return true;
            }

            Log::warning("Failed to revoke Reddit token: HTTP {$response->status()}");

            return false;
        } catch (Exception $e) {
            Log::error("Token revocation error for user {$credential->user_id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Validate that the access token is still valid by making a test API call.
     */
    public function validateToken(RedditCredential $credential): bool
    {
        try {
            $accessToken = $this->getValidAccessToken($credential);

            if (! $accessToken) {
                return false;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'User-Agent' => config('services.reddit.user_agent', config('app.name').'/1.0'),
            ])->get('https://oauth.reddit.com/api/v1/me');

            return $response->successful();
        } catch (Exception $e) {
            Log::error("Token validation error for user {$credential->user_id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Get the authenticated user's Reddit information.
     */
    public function getAuthenticatedUser(RedditCredential $credential): ?array
    {
        try {
            $accessToken = $this->getValidAccessToken($credential);

            if (! $accessToken) {
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'User-Agent' => config('services.reddit.user_agent', config('app.name').'/1.0'),
            ])->get('https://oauth.reddit.com/api/v1/me');

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error("Failed to get authenticated user for user {$credential->user_id}: ".$e->getMessage());

            return null;
        }
    }
}
