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
    reddit_credential_id: number;
    keyword: string;
    subreddits: string[];
    scan_comments: boolean;
    match_whole_word: boolean;
    case_sensitive: boolean;
    is_active: boolean;
    last_checked_at: string | null;
}

export interface RedditMention {
    id: number;
    reddit_keyword_id: number;
    reddit_post_id: string;
    reddit_comment_id: string;
    subreddit: string;
    author: string;
    content: string;
    url: string;
    mention_type: 'post' | 'comment';
    found_at: string;
}

export interface SubredditResult {
    name: string;
    subscribers: number;
    description: string;
}
