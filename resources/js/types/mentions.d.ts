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
    keyword: string;
    subreddits: string[];
    scan_comments: boolean;
    match_whole_word: boolean;
    case_sensitive: boolean;
    alert_enabled: boolean;
    alert_methods: string[];
    alert_sentiment: string[];
    alert_intent: string[];
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
