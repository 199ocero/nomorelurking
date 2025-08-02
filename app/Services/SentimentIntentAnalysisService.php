<?php

namespace App\Services;

use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Support\Facades\Log;

class SentimentIntentAnalysisService
{
    /**
     * Analyze sentiment, intent, and generate suggested reply using Gemini 2.0 Flash
     *
     * @return array ['sentiment' => string, 'sentiment_confidence' => float, 'intent' => string, 'intent_confidence' => float, 'suggested_reply' => string]
     */
    public function analyzeSentimentIntentAndReply(string $content, string $keyword): array
    {
        try {
            // Clean and prepare content
            $cleanContent = $this->cleanContent($content);

            if (empty($cleanContent)) {
                return $this->getDefaultAnalysisWithReply();
            }

            // Prepare the prompt for Gemini
            $prompt = $this->buildAnalysisPrompt($cleanContent, $keyword);

            $generationConfig = new GenerationConfig(
                temperature: 0.3,    // Slightly higher for more natural reply generation
                topP: 0.8,           // Increased for better reply variety
                topK: 40,            // Increased for more diverse responses
                maxOutputTokens: 200, // Increased for suggested reply
                responseMimeType: ResponseMimeType::APPLICATION_JSON
            );

            // Make API request using Laravel Gemini package with Gemini 2.0 Flash
            $result = Gemini::generativeModel('models/gemini-2.0-flash')
                ->withGenerationConfig($generationConfig)
                ->generateContent($prompt);

            $response = GenerateContentResponse::from($result->toArray());

            if (! $response || empty($response->text())) {
                Log::warning('Gemini API returned empty response');

                return $this->getDefaultAnalysisWithReply();
            }

            // Clean up the response text
            $resultText = trim(str_replace(['```text', '```', '```json'], '', $response->text()));
            $resultText = preg_replace('/,\s*([}\]])/', '$1', $resultText);

            $parsedResult = $this->parseGeminiResponse($resultText);

            return $parsedResult;
        } catch (\Exception $e) {
            Log::error('Sentiment, intent analysis and reply generation failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);

            return $this->getDefaultAnalysisWithReply();
        }
    }

    /**
     * Analyze sentiment, intent, and generate replies for multiple texts in batch
     */
    public function analyzeBatchSentimentIntentAndReply(array $contents, string $keyword): array
    {
        $results = [];

        foreach ($contents as $index => $content) {
            $results[$index] = $this->analyzeSentimentIntentAndReply($content, $keyword);

            // Add small delay to respect rate limits
            if (count($contents) > 1) {
                usleep(300000); // 0.3 second delay (increased due to longer processing)
            }
        }

        return $results;
    }

    /**
     * Build the comprehensive analysis prompt for Gemini
     */
    protected function buildAnalysisPrompt(string $content, string $keyword, string $subreddit = ''): string
    {
        return 'You are a world-class sentiment analysis, intent detection, and professional response generation model. Analyze the given text and provide a suggested reply tailored to the Reddit community context.

        Respond ONLY with this JSON format:
        {
          "sentiment": "positive",         // Only "positive", "negative", or "neutral"
          "sentiment_confidence": 0.91,    // Float between 0.0 and 1.0 showing sentiment certainty
          "intent": "lead",                // One of the intent types below
          "intent_confidence": 0.88,       // Float between 0.0 and 1.0 showing intent certainty
          "suggested_reply": "Thanks for sharing, that sounds interesting! ..."  // Reddit-style reply mimicking typical user tone
        }

        Intent types:
        - lead: Shows potential customer interest, seeking recommendations/solutions (e.g., "Any tools like this?").
        - competitor: Mentions competing products/services, comparisons (e.g., "This is good, but X is better").
        - brand_mention: References the brand/keyword without clear intent (e.g., "I saw this on [brand]’s page").
        - feedback: Reviews, complaints, experiences with products/services (e.g., "This broke after a week").
        - hiring_opportunity: Job postings, recruitment discussions (e.g., "Hiring devs for my startup").
        - irrelevant: Unrelated to business context or keyword (e.g., "Lol, this reminds me of a meme").

        Reply Guidelines Based on Intent:
        - lead: Friendly, helpful, and engaging; offer concise suggestions or ask clarifying questions (e.g., "Have you checked out [tool]? What features are you looking for?").
        - competitor: Acknowledge comparisons respectfully, highlight unique features casually without sounding salesy (e.g., "Yeah, X is solid, but [brand] has this cool feature too").
        - brand_mention: Thank the user warmly, share brief context or value related to the brand (e.g., "Glad you spotted that! [Brand]’s been doing some cool stuff lately").
        - feedback: Address concerns empathetically, offer practical help or solutions, avoid defensiveness (e.g., "Sorry to hear that happened, can you DM me details so we can help?").
        - hiring_opportunity: Express interest professionally but conversationally, highlight relevant skills briefly (e.g., "That sounds awesome! I’ve got experience in [skill], mind sharing more?").
        - irrelevant: Politely acknowledge or use light humor to steer back to topic if possible (e.g., "Haha, love the meme vibe, but any thoughts on [keyword]?").

        Reply Style Requirements:
        - Casual, conversational, and subreddit-appropriate—mimic typical Reddit user phrasing for the given community (e.g., technical for r/dataisbeautiful, supportive for r/socialskills).
        - Reddit-appropriate length (2-4 sentences, ~30-80 words).
        - Avoid corporate jargon; use contractions, colloquialisms, and simple language (e.g., "That’s awesome" instead of "That is highly commendable").
        - Use proper grammar but allow relaxed phrasing (e.g., "gonna" or "kinda" where natural).
        - Include tone indicators (e.g., /s, /j) sparingly when sarcasm or joking is used to avoid misinterpretation, especially in neurodivergent-friendly communities.
        - Incorporate mild humor, wit, or light sarcasm only if contextually appropriate and aligned with subreddit norms.
        - Include engagement prompts (e.g., questions, calls to action) to encourage discussion, tailored to the subreddit’s style.
        - Reflect internet culture nuances, such as subreddit-specific slang or humor, while keeping replies clear and accessible.

        Analysis Instructions:
        - Detect sarcasm, slang, and tone nuances typical in Reddit conversations, considering subreddit context (e.g., r/science vs. r/Showerthoughts).
        - Analyze tone holistically, accounting for mixed or ambiguous sentiment, and prioritize keyword relevance for intent.
        - Consider subreddit norms (e.g., analytical in r/science, empathetic in r/socialskills) when assessing tone and crafting replies.
        - Weight the provided keyword strongly in intent assessment but adapt tone to fit Reddit’s informal, community-driven style.
        - If sarcasm or tone is unclear, err on the side of neutral sentiment and clarify intent in the reply.
        - Use the subreddit context (if provided) to tailor the tone and content of the reply.

        Text to analyze: "'.addslashes($content).'"
        Keyword context: "'.addslashes($keyword).'"
        Subreddit context: "'.addslashes($subreddit).'"

        Return only the JSON object with no additional text or formatting.';
    }

    /**
     * Parse Gemini response and extract all analysis data
     */
    protected function parseGeminiResponse(string $responseText): array
    {
        try {
            // Additional cleanup for the response
            $text = trim($responseText);

            // Remove any code block markers that might remain
            $text = preg_replace('/```[a-z]*\s*/', '', $text);
            $text = preg_replace('/```\s*$/', '', $text);

            // Try to extract JSON if there's extra text
            if (preg_match('/\{[^}]*\}/', $text, $matches)) {
                $text = $matches[0];
            }

            $data = json_decode($text, true);

            if (! $data || ! isset($data['sentiment'], $data['sentiment_confidence'], $data['intent'], $data['intent_confidence'], $data['suggested_reply'])) {
                throw new \Exception('Invalid response format: '.$text);
            }

            // Validate sentiment value
            $validSentiments = ['positive', 'negative', 'neutral'];
            if (! in_array($data['sentiment'], $validSentiments)) {
                throw new \Exception('Invalid sentiment value: '.$data['sentiment']);
            }

            // Validate intent value
            $validIntents = ['lead', 'competitor', 'brand_mention', 'feedback', 'hiring_opportunity', 'irrelevant'];
            if (! in_array($data['intent'], $validIntents)) {
                throw new \Exception('Invalid intent value: '.$data['intent']);
            }

            // Validate confidence ranges
            $sentimentConfidence = floatval($data['sentiment_confidence']);
            if ($sentimentConfidence < 0.0 || $sentimentConfidence > 1.0) {
                throw new \Exception('Sentiment confidence out of range: '.$sentimentConfidence);
            }

            $intentConfidence = floatval($data['intent_confidence']);
            if ($intentConfidence < 0.0 || $intentConfidence > 1.0) {
                throw new \Exception('Intent confidence out of range: '.$intentConfidence);
            }

            // Validate suggested reply is not empty
            if (empty(trim($data['suggested_reply']))) {
                throw new \Exception('Suggested reply is empty');
            }

            return [
                'sentiment' => $data['sentiment'],
                'sentiment_confidence' => $sentimentConfidence,
                'intent' => $data['intent'],
                'intent_confidence' => $intentConfidence,
                'suggested_reply' => trim($data['suggested_reply']),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse Gemini response', [
                'error' => $e->getMessage(),
                'response_text' => $responseText,
            ]);

            return $this->getDefaultAnalysisWithReply();
        }
    }

    /**
     * Clean content before analysis
     */
    protected function cleanContent(string $content): string
    {
        // Remove Reddit markdown
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        $content = preg_replace('/\*(.*?)\*/', '$1', $content);
        $content = preg_replace('/~~(.*?)~~/', '$1', $content);
        $content = preg_replace('/\^(\w+)/', '$1', $content); // Remove superscript

        // Remove URLs but keep domain context
        $content = preg_replace('/(https?:\/\/[^\s]+)/', '[URL]', $content);

        // Keep Reddit-specific patterns but clean them
        $content = preg_replace('/\/u\/(\w+)/', 'user $1', $content);
        $content = preg_replace('/\/r\/(\w+)/', 'subreddit $1', $content);
        $content = preg_replace('/u\/(\w+)/', 'user $1', $content);
        $content = preg_replace('/r\/(\w+)/', 'subreddit $1', $content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Limit length to avoid API limits while preserving context
        if (strlen($content) > 1500) {
            $content = substr($content, 0, 1500).'...';
        }

        return $content;
    }

    /**
     * Get default analysis with reply when processing fails
     */
    protected function getDefaultAnalysisWithReply(): array
    {
        return [
            'sentiment' => 'neutral',
            'sentiment_confidence' => 0.0,
            'intent' => 'irrelevant',
            'intent_confidence' => 0.0,
            'suggested_reply' => 'Thank you for sharing. Feel free to reach out if you have any questions or need assistance.',
        ];
    }

    /**
     * Generate fallback reply based on intent type
     */
    public static function generateFallbackReply(string $intent): string
    {
        $fallbackReplies = [
            'lead' => 'Thank you for your interest. I\'d be happy to help you find the right solution. Could you share more details about your specific needs?',
            'competitor' => 'I appreciate you doing your research. Each solution has its strengths, and I\'d be glad to discuss how we might be able to help with your specific requirements.',
            'brand_mention' => 'Thank you for mentioning us. If you have any questions or would like to learn more, please feel free to ask.',
            'feedback' => 'Thank you for sharing your feedback. We value all input and would appreciate the opportunity to address any concerns you might have.',
            'hiring_opportunity' => 'This looks like an interesting opportunity. I\'d be happy to discuss how my background might be a good fit for your needs.',
            'irrelevant' => 'Thank you for sharing. Feel free to reach out if you have any questions or need assistance.',
        ];

        return $fallbackReplies[$intent] ?? $fallbackReplies['irrelevant'];
    }

    /**
     * Get sentiment label from confidence
     */
    public static function getSentimentFromConfidence(float $confidence, string $sentiment): string
    {
        // If confidence is low, default to neutral
        if ($confidence < 0.5) {
            return 'neutral';
        }

        return $sentiment;
    }

    /**
     * Format confidence for display
     */
    public static function formatConfidence(float $confidence): string
    {
        return number_format($confidence, 2);
    }

    /**
     * Get intent priority for business decision making
     */
    public static function getIntentPriority(string $intent): int
    {
        $priorities = [
            'lead' => 1,                    // Highest priority
            'hiring_opportunity' => 2,
            'feedback' => 3,
            'competitor' => 4,
            'brand_mention' => 5,
            'irrelevant' => 6,             // Lowest priority
        ];

        return $priorities[$intent] ?? 6;
    }

    /**
     * Check if intent is business-relevant
     */
    public static function isBusinessRelevant(string $intent): bool
    {
        return ! in_array($intent, ['irrelevant']);
    }

    /**
     * Get intent description for human readability
     */
    public static function getIntentDescription(string $intent): string
    {
        $descriptions = [
            'lead' => 'Potential customer showing interest or seeking solutions',
            'competitor' => 'Discussion about competing products or services',
            'brand_mention' => 'Reference to brand without clear business intent',
            'feedback' => 'Customer review, complaint, or experience sharing',
            'hiring_opportunity' => 'Job posting or recruitment discussion',
            'irrelevant' => 'Content unrelated to business context',
        ];

        return $descriptions[$intent] ?? 'Unknown intent';
    }

    /**
     * Validate suggested reply quality
     */
    public static function validateReplyQuality(string $reply): bool
    {
        // Basic quality checks
        if (strlen(trim($reply)) < 10) {
            return false;
        }

        if (strlen($reply) > 500) {
            return false;
        }

        // Check for emoji presence (should not contain emojis)
        if (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $reply)) {
            return false;
        }

        return true;
    }

    /**
     * Get reply tone based on sentiment and intent
     */
    public static function getReplyTone(string $sentiment, string $intent): string
    {
        if ($intent === 'lead') {
            return 'helpful_professional';
        }

        if ($intent === 'feedback' && $sentiment === 'negative') {
            return 'apologetic_solution_focused';
        }

        if ($intent === 'competitor') {
            return 'confident_respectful';
        }

        return 'professional_neutral';
    }
}
