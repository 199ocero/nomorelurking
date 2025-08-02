<?php

namespace App\Services;

use App\Models\RedditMention;
use App\Notifications\RedditMentionAlert;

class RedditAlertService
{
    public function checkAndSendAlerts(RedditMention $mention)
    {
        $keyword = $mention->keyword;

        // Skip if alerts are disabled
        if (! $keyword->alert_enabled) {
            return;
        }

        // Check upvote threshold
        if ($keyword->alert_min_upvotes && $mention->upvotes < $keyword->alert_min_upvotes) {
            return;
        }

        // Check sentiment (if implemented later)
        // if ($keyword->alert_sentiment && $mention->sentiment !== $keyword->alert_sentiment) {
        //     return;
        // }

        // Send alerts via configured methods
        foreach ($keyword->alert_methods as $method) {
            $this->sendAlert($mention, $method);
        }
    }

    // protected function sendAlert(RedditMention $mention, string $method)
    // {
    //     $user = $mention->user;

    //     switch ($method) {
    //         case 'email':
    //             $user->notify(new RedditMentionAlert($mention));
    //             break;
    //         case 'slack':
    //             // Integrate with Slack webhook
    //             break;
    //     }

    //     // Log the alert (optional)
    //     RedditAlert::create([
    //         'user_id' => $user->id,
    //         'reddit_keyword_id' => $mention->reddit_keyword_id,
    //         'reddit_mention_id' => $mention->id,
    //         'alert_method' => $method,
    //         'sent_at' => now(),
    //     ]);
    // }
}
