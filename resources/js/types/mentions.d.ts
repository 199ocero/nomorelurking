export interface RedditCredential {
    id: number;
    reddit_id: string;
    username: string;
    token_expires_at: string | null;
}

export interface User {
    id: number;
    name: string;
    email: string;
    reddit_credential: RedditCredential | null;
}

export interface RedditKeyword {
    id: number;
    user_id: number;
    reddit_credential_id: number;
    reddit_id: string;
    persona_id: number;
    keyword: string;
    subreddits: string[];
    scan_comments: boolean;
    match_whole_word: boolean;
    case_sensitive: boolean;
    alert_enabled: boolean;
    alert_methods: string[];
    alert_sentiments: string[];
    alert_intents: string[];
    last_checked_at: string | null;
}

export interface RedditMention {
    id: number;
    user_id: number;
    reddit_keyword_id: number;
    reddit_post_id: string;
    reddit_comment_id: string;
    keyword: string;
    subreddit: string;
    author: string;
    title: string | null;
    content: string;
    url: string;
    mention_type: 'post' | 'comment';
    upvotes: number;
    downvotes: number;
    comment_count: number;
    is_stickied: boolean;
    is_locked: boolean;
    sentiment: string;
    sentiment_confidence: number;
    intent: string;
    intent_confidence: number;
    suggested_reply: string | null;
    reddit_created_at: string;
    found_at: string;
    persona: [];
}

export interface SubredditResult {
    name: string;
    subscribers: number;
    description: string;
}

export interface LastFetch {
    id: number;
    user_id: number;
    reddit_credential_id: number;
    dispatch_at: string | null;
    last_fetched_at: string | null;
}

// Updated interface for dynamic settings
export interface PersonaSettings {
    [key: string]: string | undefined;
    
    // Small Business
    business_name?: string;
    industry_niche?: string;
    business_description?: string;
    
    // Marketing & Customer Support & PR Crisis
    brand_name?: string;
    brand_description?: string;
    
    // Marketing specific
    engagement_goal?: 'brand_awareness' | 'reputation_management' | 'market_research';
    
    // Content Creator
    creator_niche?: string;
    engagement_style?: 'storytelling' | 'question_asking' | 'sharing_tips';
    
    // Customer Support
    product_service?: string;
    support_contact?: string;
    
    // Market Researcher
    research_focus?: string;
    question_style?: 'open_ended' | 'specific';
    
    // Freelancer
    expertise_area?: string;
    engagement_approach?: 'offering_tips' | 'answering_questions' | 'sharing_experiences';
    
    // PR Crisis
    escalation_contact?: string;
    
    // Common tone settings
    preferred_tone?: string;
}

export interface Persona {
    id: number;
    name: string;
    user_id: number;
    user_type: 'small_business' | 'marketing' | 'content_creator' | 'customer_support' | 'market_researcher' | 'freelancer' | 'pr_crisis';
    settings: PersonaSettings;
}
