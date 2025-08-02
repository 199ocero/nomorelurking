<?php

namespace App\Spiders;

use App\Downloaders\BrowsershotDownloadMiddleware;
use Generator;
use Illuminate\Support\Facades\Log;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;

class RedditSearchSpider extends BasicSpider
{
    public int $concurrency = 1;

    public int $requestDelay = 2;

    public array $downloaderMiddleware = [
        BrowsershotDownloadMiddleware::class,
    ];

    public function parse(Response $response): Generator
    {
        $url = $response->getUri();

        try {
            $html = $response->getBody();
            $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
            $posts = $this->extractPostsFromHtml($crawler);
        } catch (\Exception $e) {
            Log::error("RedditSearchSpider: Failed to parse {$url}: ".$e->getMessage());

            return;
        }

        foreach ($posts as $post) {
            yield $this->item($post);
        }
    }

    private function extractPostsFromHtml(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        $posts = [];

        $telemetryElements = $crawler->filter('search-telemetry-tracker[data-testid="search-sdui-post"]');

        if ($telemetryElements->count() === 0) {
            $telemetryElements = $crawler->filter('search-telemetry-tracker[data-faceplate-tracking-context*="\"type\":\"post\""]');
        }

        if ($telemetryElements->count() === 0) {
            return [];
        }

        $telemetryElements->each(function ($tracker) use (&$posts) {
            $jsonData = $tracker->attr('data-faceplate-tracking-context');

            if (! $jsonData) {
                return;
            }

            $decodedJson = html_entity_decode($jsonData);
            $trackingData = json_decode($decodedJson, true);

            if (! $trackingData) {
                return;
            }

            $actionInfo = $trackingData['action_info'] ?? [];
            if (isset($actionInfo['type']) && $actionInfo['type'] !== 'post') {
                return;
            }

            if (isset($trackingData['post']) && isset($trackingData['subreddit'])) {
                $postData = $trackingData['post'];
                $subredditData = $trackingData['subreddit'];

                $postId = $postData['id'] ?? '';

                foreach ($posts as $existingPost) {
                    if ($existingPost['post_id'] === $postId) {
                        return;
                    }
                }

                $data = [
                    'title' => $postData['title'] ?? 'No title',
                    'post_id' => $postId,
                    'subreddit' => $subredditData['name'] ?? null,
                    'subreddit_id' => $subredditData['id'] ?? null,
                    'url' => $postData['permalink'] ?? null,
                    'score' => $postData['score'] ?? 0,
                    'num_comments' => $postData['num_comments'] ?? 0,
                    'created_utc' => $postData['created_utc'] ?? null,
                    'author' => $postData['author'] ?? null,
                ];

                $posts[] = $data;
            }
        });

        return $posts;
    }
}
