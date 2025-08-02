<?php

namespace App\Downloaders;

use Illuminate\Support\Facades\Log;
use RoachPHP\Downloader\Middleware\RequestMiddlewareInterface;
use RoachPHP\Downloader\Middleware\ResponseMiddlewareInterface;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Support\Configurable;
use Spatie\Browsershot\Browsershot;

class BrowsershotDownloadMiddleware implements RequestMiddlewareInterface, ResponseMiddlewareInterface
{
    use Configurable;

    private function defaultOptions(): array
    {
        return [
            'window_width' => 1920,
            'window_height' => 1080,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'rotate_user_agents' => true,
            'user_agents_pool' => [],
            'timeout' => 300,
            'protocol_timeout' => 300,
            'node_path' => config('browsershot.node_path', '/usr/bin/node'),
            'npm_path' => config('browsershot.npm_path', '/usr/bin/npm'),
            'wait_until_network_idle' => true,
            'enable_scrolling' => true,
            'max_scrolls' => 10,
            'scroll_delay' => 3000,
            'initial_delay' => 3000,
        ];
    }

    public function handleRequest(Request $request): Request
    {
        return $request->withMeta('use_browsershot', true);
    }

    public function handleResponse(Response $response): Response
    {
        $request = $response->getRequest();

        if (! $request->getMeta('use_browsershot', false)) {
            return $response;
        }

        $url = $request->getUri();

        try {
            $html = $this->getBrowsershotContent($url);

            if (empty($html)) {
                return $response;
            }

            $newResponse = $response->withBody($html);

            return $newResponse;
        } catch (\Exception $e) {
            Log::error("BrowsershotMiddleware failed for {$url}: ".$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return $response;
        }
    }

    private function getBrowsershotContent(string $url): string
    {
        $userAgent = $this->getUserAgent();

        try {
            $browsershot = Browsershot::url($url)
                ->windowSize($this->option('window_width'), $this->option('window_height'))
                ->userAgent($userAgent)
                ->timeout($this->option('timeout'))
                ->protocolTimeout($this->option('protocol_timeout'))
                ->setNodeBinary($this->option('node_path'))
                ->setNpmBinary($this->option('npm_path'))
                ->dismissDialogs()
                ->ignoreHttpsErrors()
                ->disableCaptureURLs()
                ->noSandbox();
            if ($this->option('wait_until_network_idle')) {
                $browsershot->waitUntilNetworkIdle();
            }

            if ($this->option('enable_scrolling')) {
                return $browsershot->evaluate($this->getScrollScript());
            }

            return $browsershot->bodyHtml();
        } catch (\Exception $e) {
            Log::error('Browsershot execution failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function getScrollScript(): string
    {
        return "
        (async function() {
            const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

            try {
                // Initial delay to let page load
                await delay({$this->option('initial_delay')});

                // Get initial page height
                let lastHeight = document.body.scrollHeight;
                let scrollCount = 0;

                // Scroll down multiple times or until no new content loads
                while (scrollCount < {$this->option('max_scrolls')}) {
                    window.scrollTo(0, document.body.scrollHeight);
                    await delay({$this->option('scroll_delay')});

                    let newHeight = document.body.scrollHeight;
                    if (newHeight === lastHeight) {
                        // No new content loaded, break early
                        break;
                    }

                    lastHeight = newHeight;
                    scrollCount++;
                }

                // Scroll back to top
                window.scrollTo(0, 0);
                await delay(1000);

                // Return the HTML content
                return document.body.innerHTML;

            } catch (error) {
                console.error('Scroll script error:', error);
                return document.body.innerHTML;
            }
        })();
        ";
    }

    /**
     * Get user agent - either static or rotating
     */
    private function getUserAgent(): string
    {
        if (! $this->option('rotate_user_agents')) {
            return $this->option('user_agent');
        }

        $userAgents = ! empty($this->option('user_agents_pool'))
            ? $this->option('user_agents_pool')
            : $this->getDefaultUserAgents();

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Get a realistic pool of user agents
     */
    private function getDefaultUserAgents(): array
    {
        return [
            // Chrome on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',

            // Chrome on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',

            // Firefox on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:119.0) Gecko/20100101 Firefox/119.0',

            // Firefox on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',

            // Safari on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Safari/605.1.15',

            // Edge on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',

            // Chrome on Linux
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        ];
    }
}
