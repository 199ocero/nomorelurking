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
    public function analyzeSentimentIntentAndReply(string $content, string $keyword, array $settings, string $userType): array
    {
        try {
            // Clean and prepare content
            $cleanContent = $this->cleanContent($content);

            if (empty($cleanContent)) {
                return $this->getDefaultAnalysisWithReply();
            }

            // Prepare the prompt for Gemini
            $prompt = $this->buildAnalysisPrompt($cleanContent, $keyword, $settings, $userType);

            $generationConfig = new GenerationConfig(
                temperature: 0.8,    // Increased for creativity
                topP: 0.95,          // Wider probability sampling
                topK: 50,            // More token options
                maxOutputTokens: 300, // Longer replies
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
     * Build the comprehensive analysis prompt for Gemini
     */
    protected function buildAnalysisPrompt(string $content, string $keyword, array $settings, string $userType): string
    {
        $basePrompt = 'You are a world-class sentiment analysis, intent detection, and professional response generation model. Analyze the given text and provide a suggested reply tailored to the Reddit community context.';

        $basePrompt .= $this->buildPersonalizationContext($settings, $userType);

        return $basePrompt.'

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
        - brand_mention: References the brand/keyword without clear intent (e.g., "I saw this on [brand]\'s page").
        - feedback: Reviews, complaints, experiences with products/services (e.g., "This broke after a week").
        - hiring_opportunity: Job postings, recruitment discussions (e.g., "Hiring devs for my startup").
        - irrelevant: Unrelated to business context or keyword (e.g., "Lol, this reminds me of a meme").

        CRITICAL REPLY REQUIREMENTS:
        
        1. NEVER sound corporate, promotional, or salesy
        2. When mentioning brands/tools, do it casually like a satisfied user would ("tried X recently and its been pretty solid")
        3. Use mostly casual typing with some lowercase, but don\'t force it everywhere
        4. Include some contractions and informal language, but keep it readable (you/ur, really/rly mix is fine)
        5. Sound like a regular Reddit user, not a brand representative
        6. Be helpful but in a peer-to-peer way, not customer service way
        7. Sound conversational but readable - mix of casual and proper grammar
        8. Don\'t overuse slang - sprinkle it in naturally, not every sentence
        9. NOT use the dashes in the sentence
        10. Start with standard capitalization then mix in lowercase naturally - like "Yeah that sucks. i ran into the same issue last month. The free tier is usually trash but..."

        MANDATORY Reply Style Examples - YOUR REPLY MUST MATCH THESE PATTERNS:

        For LEADS (when someone asks for recommendations):
        ❌ BAD: "As [Brand], we offer great features for this use case. Check us out!"
        ✅ GOOD: "Finding good tools for this is tough. I\'ve tried a bunch and most are either too expensive or don\'t work well. Been using Aplymee recently and it\'s been pretty solid - decent free tier and actually does what it says. Worth checking out if you\'re still looking"

        For COMPLAINTS/FEEDBACK:
        ❌ BAD: "We apologize for your experience. Please contact our support team."
        ✅ GOOD: "Oh that sucks, I\'ve had similar issues with other tools too. It\'s frustrating when stuff just breaks. If you\'re using Aplymee, try clearing your cache first - sometimes that fixes weird bugs. Their support is actually pretty responsive if nothing else works"

        For COMPETITOR MENTIONS:
        ❌ BAD: "While [Competitor] is good, our solution offers better features."
        ✅ GOOD: "Yeah [competitor] is decent but gets pricey pretty quick. I\'ve been trying different options lately and some of the newer ones are actually solid. Really depends what you\'re looking for though - what features do you need most?"

        For GENERAL BRAND MENTIONS:
        ❌ BAD: "Thank you for mentioning our brand! We appreciate the feedback."
        ✅ GOOD: "Nice! Yeah I\'ve been seeing that one pop up more lately. How do you like it so far? Been thinking about trying it myself but haven\'t had time to test it out properly"

        Style Matching Requirements:
        - Use patterns from the 10 reply styles provided above
        - Match the casual, conversational tone exactly
        - Use PROPER CAPITALIZATION at the start of sentences (like "Yeah that\'s annoying" not "yeah thats annoying")
        - Mix casual elements naturally - some contractions (you\'re/your, it\'s/its) but don\'t force every word
        - Sound like a helpful community member, not a business
        - Be subtly helpful without being pushy or promotional

        CAPITALIZATION RULE: Start sentences with capital letters. Use normal punctuation. Only go lowercase for emphasis or very casual moments, not as the default.

        Analysis Instructions:
        - Detect sarcasm, slang, and tone nuances typical in Reddit conversations, considering subreddit context (e.g., r/science vs. r/Showerthoughts).
        - Analyze tone holistically, accounting for mixed or ambiguous sentiment, and prioritize keyword relevance for intent.
        - Consider subreddit norms (e.g., analytical in r/science, empathetic in r/socialskills) when assessing tone and crafting replies.
        - Weight the provided keyword strongly in intent assessment but adapt tone to fit Reddit\'s informal, community-driven style.
        - If sarcasm or tone is unclear, err on the side of neutral sentiment and clarify intent in the reply.

        FINAL REMINDER: Your suggested_reply should sound natural and conversational - like a helpful person on Reddit, not overly slangy or forced. Use normal capitalization and punctuation most of the time, with casual elements sprinkled in naturally.

        Text to analyze: "'.addslashes($content).'"
        Keyword context: "'.addslashes($keyword).'"

        Return only the JSON object with no additional text or formatting.';
    }

    /**
     * Build personalization context based on user settings and user type
     */
    private function buildPersonalizationContext(array $settings, string $userType): string
    {
        $context = "\n\nPersonalization Context:\n";
        $context .= "- User Type: {$userType}\n";

        // Handle settings based on user type
        switch ($userType) {
            case 'small_business':
                if (isset($settings['business_name'])) {
                    $context .= "- You are familiar with '{$settings['business_name']}'".
                        (isset($settings['business_description']) ? " ({$settings['business_description']})" : '').
                        " and can casually mention it as a helpful option when relevant.\n";
                }
                if (isset($settings['industry_niche'])) {
                    $context .= "- You have experience in {$settings['industry_niche']} industry.\n";
                }
                $context .= "- When appropriate, naturally suggest this business as someone who has used/heard good things about it.\n";
                break;

            case 'marketing':
                if (isset($settings['brand_name'])) {
                    $context .= "- You are a satisfied user/fan of '{$settings['brand_name']}'".
                        (isset($settings['brand_description']) ? " ({$settings['brand_description']})" : '').
                        " and can recommend it naturally when it fits.\n";
                }
                if (isset($settings['engagement_goal'])) {
                    $goalMap = [
                        'brand_awareness' => 'casually mention the brand when it genuinely helps the conversation',
                        'reputation_management' => 'share positive experiences with the brand when relevant',
                        'market_research' => 'ask follow-up questions to understand user needs better',
                    ];
                    $context .= "- Approach: {$goalMap[$settings['engagement_goal']]}\n";
                }
                break;

            case 'content_creator':
                if (isset($settings['creator_niche'])) {
                    $context .= "- You create content in the {$settings['creator_niche']} space.\n";
                }
                if (isset($settings['engagement_style'])) {
                    $styleMap = [
                        'storytelling' => 'share relevant experiences and stories from your content creation journey',
                        'question_asking' => 'ask engaging questions to understand what content would help',
                        'sharing_tips' => 'offer practical tips based on your content creation experience',
                    ];
                    $context .= "- Your style: {$styleMap[$settings['engagement_style']]}\n";
                }
                break;

            case 'customer_support':
                if (isset($settings['brand_name'])) {
                    $context .= "- You are a power user of '{$settings['brand_name']}'".
                        (isset($settings['brand_description']) ? " ({$settings['brand_description']})" : '').
                        " and know it really well.\n";
                }
                if (isset($settings['product_service'])) {
                    $context .= "- You have extensive experience with {$settings['product_service']}.\n";
                }
                if (isset($settings['support_contact'])) {
                    $context .= "- For serious issues, you can casually mention contacting {$settings['support_contact']}.\n";
                }
                $context .= "- When the tool/service could help, naturally recommend it based on your positive experience.\n";
                break;

            case 'market_researcher':
                if (isset($settings['research_focus'])) {
                    $context .= "- You are researching {$settings['research_focus']} trends.\n";
                }
                if (isset($settings['question_style'])) {
                    $styleMap = [
                        'open_ended' => 'ask broad questions to understand the bigger picture',
                        'specific' => 'ask specific follow-up questions about their exact needs',
                    ];
                    $context .= "- Your approach: {$styleMap[$settings['question_style']]}\n";
                }
                break;

            case 'freelancer':
                if (isset($settings['expertise_area'])) {
                    $context .= "- You freelance in {$settings['expertise_area']} and have practical experience.\n";
                }
                if (isset($settings['engagement_approach'])) {
                    $approachMap = [
                        'offering_tips' => 'share practical tips from your freelancing experience',
                        'answering_questions' => 'provide detailed answers based on your professional experience',
                        'sharing_experiences' => 'relate your own freelancing stories and lessons learned',
                    ];
                    $context .= "- Your natural way: {$approachMap[$settings['engagement_approach']]}\n";
                }
                break;

            case 'pr_crisis':
                if (isset($settings['brand_name'])) {
                    $context .= "- You are familiar with '{$settings['brand_name']}'".
                        (isset($settings['brand_description']) ? " ({$settings['brand_description']})" : '').
                        " and want to help with any concerns.\n";
                }
                if (isset($settings['escalation_contact'])) {
                    $context .= "- For serious issues, you know they can reach out to {$settings['escalation_contact']}.\n";
                }
                $context .= "- When helpful, casually mention the brand's efforts to address issues.\n";
                break;
        }

        $context .= "\nIMPORTANT: Weave this context naturally into your reply. Don't force it, but when the conversation genuinely calls for it, draw on this background knowledge in a casual, helpful way.\n";

        return $context;
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
