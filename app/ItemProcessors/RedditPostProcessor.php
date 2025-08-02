<?php

namespace App\ItemProcessors;

use App\Jobs\ProcessSingleRedditPost;
use Illuminate\Support\Facades\Log;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

class RedditPostProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        $data = $item->all();

        $cleanedData = $this->cleanData($data);

        if ($this->isValidPost($cleanedData)) {
            $this->dispatchPostProcessingJob($cleanedData);
        }

        return $item;
    }

    private function cleanData(array $data): array
    {
        $cleaned = [];

        $necessaryFields = ['title', 'post_id', 'subreddit', 'subreddit_id'];

        foreach ($necessaryFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];

                if (is_string($value)) {
                    $value = trim(preg_replace('/\s+/', ' ', $value));
                }

                if (! empty($value)) {
                    $cleaned[$field] = $value;
                }
            }
        }

        $cleaned['scraped_at'] = now()->toISOString();

        return $cleaned;
    }

    private function isValidPost(array $data): bool
    {
        return ! empty($data['title']) && ! empty($data['post_id']);
    }

    private function dispatchPostProcessingJob(array $data): void
    {
        $userId = $this->option('user_id');
        $credentialId = $this->option('credential_id');
        $keywordId = $this->option('keyword_id');

        if (! $credentialId || ! $keywordId) {
            Log::error('Missing credential_id or keyword_id for Reddit post processing', [
                'post_id' => $data['post_id'] ?? 'unknown',
                'user_id' => $userId,
                'credential_id' => $credentialId,
                'keyword_id' => $keywordId,
            ]);

            return;
        }

        // Dispatch the job with the post ID and subreddit info
        ProcessSingleRedditPost::dispatch(
            $credentialId,
            $keywordId,
            $data['post_id'],
            $data['subreddit'] ?? null
        )
            ->onQueue('reddit-post-processing')
            ->delay(now()->addSeconds(rand(1, 3)));
    }

    /**
     * Default configuration options for the processor.
     */
    private function defaultOptions(): array
    {
        return [
            'user_id' => null,
            'credential_id' => null,
            'keyword_id' => null,
        ];
    }
}
