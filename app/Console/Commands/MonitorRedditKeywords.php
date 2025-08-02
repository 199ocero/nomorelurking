<?php

namespace App\Console\Commands;

use App\Jobs\MonitorRedditKeywords as MonitorRedditKeywordsJob;
use App\Models\LastFetch;
use App\Models\RedditCredential;
use App\Models\RedditKeyword;
use App\Models\User;
use Illuminate\Console\Command;

class MonitorRedditKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor-reddit-keywords {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Reddit keywords';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Monitoring Reddit keywords...');

        $email = $this->option('email');

        if ($email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->error("User with email '{$email}' not found.");

                return;
            }

            $this->processUser($user);
        } else {
            User::whereHas('redditCredential')->chunk(50, function ($users) {
                foreach ($users as $user) {
                    $this->processUser($user);
                }
            });
        }
    }

    protected function processUser(User $user)
    {
        $credential = RedditCredential::where('user_id', $user->id)->first();

        if (! $credential) {
            $this->warn("Reddit credential not found for user: {$user->email}");

            return;
        }

        $activeKeywordsCount = RedditKeyword::where('reddit_credential_id', $credential->id)->count();

        if ($activeKeywordsCount === 0) {
            $this->warn("No active keywords for user: {$user->email}");

            return;
        }

        try {
            MonitorRedditKeywordsJob::dispatch($credential->user_id, $credential->reddit_id)
                ->onQueue('reddit-monitoring');

            LastFetch::updateOrCreate([
                'user_id' => $credential->user_id,
                'reddit_credential_id' => $credential->id,
            ], [
                'dispatch_at' => now(),
            ]);

            $this->info("Monitoring started for: {$user->email}");
        } catch (\Exception $e) {
            $this->error("Error processing user {$user->email}: ".$e->getMessage());
        }
    }
}
