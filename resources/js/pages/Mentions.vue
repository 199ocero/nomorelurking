<script setup lang="ts">
import MultiSelectCombobox from '@/components/MultiSelectCombobox.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Switch from '@/components/ui/switch/Switch.vue';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Persona, PersonaSettings, RedditCredential, RedditKeyword, RedditMention, SubredditResult } from '@/types/mentions';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AtSign,
    Check,
    Copy,
    Edit,
    ExternalLink,
    Link,
    Loader2,
    MessageCircle,
    Play,
    Plus,
    PlusCircle,
    Radar,
    Search,
    Settings,
    Trash2,
    Unlink,
    UserRoundCheck,
    X,
    Info,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger
} from '@/components/ui/tooltip'

interface Props {
    keywords: RedditKeyword[];
    credentials: RedditCredential;
    mentions: RedditMention[];
    dispatch_at: string | null;
    last_fetched_at: string | null;
    personas: Persona[];
}

const props = defineProps<Props>();
const isLoading = ref(false);
const showDisconnectDialog = ref(false);
const showDeleteKeywordDialog = ref(false);
const showDeletePersonaDialog = ref(false);
const deleteKeywordId = ref<number | null>(null);
const deletePersonaId = ref<number | null>(null);
const showKeywords = ref(false);
const showPersonas = ref(false);
const showMentionDialog = ref(false);
const selectedMention = ref<RedditMention | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mentions',
        href: '/mentions',
    },
];

const redditCredential = computed(() => props.credentials);
const hasRedditConnection = computed(() => props.credentials.id != null);

const connectToReddit = () => {
    isLoading.value = true;
    window.location.href = '/auth/reddit';
};

const handleDisconnectReddit = () => {
    router.post(
        '/auth/reddit/disconnect',
        {},
        {
            onStart: () => (isLoading.value = true),
            onFinish: () => (isLoading.value = false),
            onSuccess: () => {
                router.reload();
            },
        },
    );
};

const isTokenExpired = computed(() => {
    if (!redditCredential.value?.token_expires_at) return false;
    return new Date(redditCredential.value.token_expires_at) < new Date();
});

const isModalOpen = ref(false);
const isPersonaModalOpen = ref(false);
const isEditing = ref(false);
const isEditingPersona = ref(false);
const processing = ref(false);
const subredditSearch = ref('');
const subredditResults = ref<SubredditResult[]>([]);
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

// Form data
const form = useForm({
    id: null as number | null,
    keyword: '',
    reddit_credential_id: props.credentials.id,
    subreddits: [] as string[],
    scan_comments: false as boolean,
    match_whole_word: false as boolean,
    case_sensitive: false as boolean,
    reddit_id: props.credentials.reddit_id,
    persona_id: null as number | null,
    alert_enabled: false as boolean,
    alert_methods: [] as string[],
    alert_sentiments: [] as string[],
    alert_intents: [] as string[],
});

const personaForm = useForm({
    id: null as number | null,
    name: '',
    user_type: '' as 'small_business' | 'marketing' | 'content_creator' | 'customer_support' | 'market_researcher' | 'freelancer' | 'pr_crisis',
    settings: {} as PersonaSettings,
});

// Methods
const openCreateModal = () => {
    form.reset();
    isEditing.value = false;
    isModalOpen.value = true;
    subredditSearch.value = '';
    subredditResults.value = [];
};

const openCreatePersonaModal = () => {
    personaForm.reset();
    isEditingPersona.value = false;
    isPersonaModalOpen.value = true;
};

const editKeyword = (keyword: RedditKeyword) => {
    form.id = keyword.id;
    form.keyword = keyword.keyword;
    form.reddit_credential_id = keyword.reddit_credential_id;
    form.subreddits = keyword.subreddits || [];
    form.scan_comments = keyword.scan_comments;
    form.match_whole_word = keyword.match_whole_word;
    form.case_sensitive = keyword.case_sensitive;
    form.alert_enabled = keyword.alert_enabled;
    form.alert_methods = keyword.alert_methods || [];
    form.alert_sentiments = keyword.alert_sentiments || [];
    form.alert_intents = keyword.alert_intents || [];
    form.persona_id = keyword.persona_id;

    selectedSentiments.value = Array.isArray(keyword.alert_sentiments) ? keyword.alert_sentiments : [];

    selectedIntents.value = Array.isArray(keyword.alert_intents) ? keyword.alert_intents : [];

    selectedMethods.value = Array.isArray(keyword.alert_methods) ? keyword.alert_methods : [];

    isEditing.value = true;
    isModalOpen.value = true;
};

watch(isModalOpen, (newValue) => {
    if (!newValue) {
        form.reset();
        form.clearErrors();
        subredditResults.value = [];
        subredditSearch.value = '';
        selectedIntents.value = [];
        selectedMethods.value = [];
        selectedSentiments.value = [];
    }
});

watch(isPersonaModalOpen, (newValue) => {
    if (!newValue) {
        personaForm.reset();
        personaForm.clearErrors();
    }
});

const closeModal = () => {
    isModalOpen.value = false;
};

const closePersonaModal = () => {
    isPersonaModalOpen.value = false;
};

const submitForm = () => {
    processing.value = true;

    form.alert_sentiments = selectedSentiments.value;
    form.alert_intents = selectedIntents.value;
    form.alert_methods = selectedMethods.value;

    if (isEditing.value && form.id) {
        form.put(
            route('mentions.update-keyword', {
                keyword: form.id,
            }),
            {
                onSuccess: () => {
                    closeModal();
                },
                onError: () => {
                    processing.value = false;
                },
            },
        );
    } else {
        form.post(route('mentions.store-keyword'), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

// Reactive settings object
const personaSettings = reactive<PersonaSettings>({});

// Function to get default settings based on user type
const getDefaultSettings = (userType: string): PersonaSettings => {
    const defaults: Record<string, PersonaSettings> = {
        small_business: {
            business_name: '',
            industry_niche: '',
            business_description: '',
        },
        marketing: {
            brand_name: '',
            engagement_goal: 'brand_awareness',
            brand_description: '',
        },
        content_creator: {
            creator_niche: '',
            engagement_style: 'question_asking',
        },
        customer_support: {
            brand_name: '',
            product_service: '',
            support_contact: '',
            brand_description: '',
        },
        market_researcher: {
            research_focus: '',
            question_style: 'open_ended',
        },
        freelancer: {
            expertise_area: '',
            engagement_approach: 'offering_tips',
        },
        pr_crisis: {
            brand_name: '',
            brand_description: '',
            escalation_contact: '',
        },
    };

    return defaults[userType] || {};
};

// Reset settings when user type changes
const resetSettings = (newUserType: string) => {
    const defaultSettings = getDefaultSettings(newUserType);
    Object.keys(personaSettings).forEach((key) => {
        delete personaSettings[key as keyof PersonaSettings];
    });
    Object.assign(personaSettings, defaultSettings);
};

watch(
    personaSettings,
    (newSettings: PersonaSettings) => {
        personaForm.settings = { ...newSettings };
    },
    { deep: true },
);

// Function to populate form when editing
const editPersona = (persona: Persona) => {
    personaForm.id = persona.id;
    personaForm.name = persona.name;
    personaForm.user_type = persona.user_type;

    // Reset and populate settings
    Object.keys(personaSettings).forEach((key) => {
        delete personaSettings[key as keyof PersonaSettings];
    });
    Object.assign(personaSettings, persona.settings || getDefaultSettings(persona.user_type));

    isEditingPersona.value = true;
    isPersonaModalOpen.value = true;
};

// Submit form function
const submitPersonaForm = () => {
    // Ensure settings are updated
    personaForm.settings = { ...personaSettings };

    if (personaForm.id) {
        personaForm.put(route('personas.update', personaForm.id), {
            onSuccess: () => {
                closePersonaModal();
            },
        });
    } else {
        personaForm.post(route('personas.store'), {
            onSuccess: () => {
                closePersonaModal();
            },
        });
    }
};

const showConfirmDeleteKeywordDialog = (id: number) => {
    showDeleteKeywordDialog.value = true;
    deleteKeywordId.value = id;
};

const showConfirmDeletePersonaDialog = (id: number) => {
    showDeletePersonaDialog.value = true;
    deletePersonaId.value = id;
};

const deleteKeyword = () => {
    if (deleteKeywordId.value) {
        router.delete(route('mentions.destroy-keyword', deleteKeywordId.value));
        showDeleteKeywordDialog.value = false;
        deleteKeywordId.value = null;
    }
};

const deletePersona = () => {
    if (deletePersonaId.value) {
        router.delete(route('personas.destroy', deletePersonaId.value));
        showDeletePersonaDialog.value = false;
        deletePersonaId.value = null;
    }
};

const isSearching = ref(false);

const searchSubreddits = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    if (!subredditSearch.value || !form.reddit_credential_id) {
        subredditResults.value = [];
        isSearching.value = false;
        return;
    }

    searchTimeout.value = setTimeout(async () => {
        try {
            isSearching.value = true;

            const response = await fetch(route('mentions.search-subreddits'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 'null',
                },
                body: JSON.stringify({
                    query: subredditSearch.value,
                    credential_id: form.reddit_credential_id,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                subredditResults.value = (data.subreddits || []).sort((a: SubredditResult, b: SubredditResult) => {
                    return b.subscribers - a.subscribers;
                });
            }
        } catch (error) {
            console.error('Error searching subreddits:', error);
        } finally {
            isSearching.value = false;
        }
    }, 500);
};

// Combined function
const toggleSubreddit = (subredditName: string) => {
    const index = form.subreddits.indexOf(subredditName);
    if (index > -1) {
        form.subreddits.splice(index, 1);
    } else {
        form.subreddits.push(subredditName);
    }

    if (form.subreddits.length >= 3) {
        subredditSearch.value = '';
        subredditResults.value = [];
    }
};

const startMonitoring = async () => {
    try {
        const response = await fetch(route('mentions.start-monitoring'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 'null',
            },
        });

        if (response.ok) {
            router.reload();
        } else {
            console.error('Failed to start monitoring:', await response.text());
        }
    } catch (error) {
        console.error('Error starting monitoring:', error);
    }
};

const visitRedditPost = (url?: string) => {
    if (!url) {
        return;
    }

    window.open(url, '_blank');
};

// Enhanced sentiment styling
const getSentimentBadgeClass = (sentiment: RedditMention['sentiment'] | null | undefined) => {
    switch (sentiment) {
        case 'positive':
            return 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30';
        case 'negative':
            return 'bg-red-500/10 text-red-700 border-red-500/20 dark:text-red-400 dark:border-red-500/30';
        default:
            return 'bg-amber-500/10 text-amber-700 border-amber-500/20 dark:text-amber-400 dark:border-amber-500/30';
    }
};

const getSentimentDotClass = (sentiment: RedditMention['sentiment'] | null | undefined) => {
    switch (sentiment) {
        case 'positive':
            return 'bg-emerald-500 animate-pulse';
        case 'negative':
            return 'bg-red-500 animate-pulse';
        default:
            return 'bg-amber-500 animate-pulse';
    }
};

// Intent styling
const getIntentBadgeClass = (intent: RedditMention['intent'] | null | undefined) => {
    switch (intent) {
        case 'lead':
            return 'bg-blue-500/10 text-blue-700 border-blue-500/20 dark:text-blue-300 dark:border-blue-500/30';
        case 'competitor':
            return 'bg-purple-500/10 text-purple-700 border-purple-500/20 dark:text-purple-300 dark:border-purple-500/30';
        case 'brand_mention':
            return 'bg-indigo-500/10 text-indigo-700 border-indigo-500/20 dark:text-indigo-300 dark:border-indigo-500/30';
        case 'feedback':
            return 'bg-cyan-500/10 text-cyan-700 border-cyan-500/20 dark:text-cyan-300 dark:border-cyan-500/30';
        case 'hiring_opportunity':
            return 'bg-teal-500/10 text-teal-700 border-teal-500/20 dark:text-teal-300 dark:border-teal-500/30';
        case 'irrelevant':
            return 'bg-gray-500/10 text-gray-700 border-gray-500/20 dark:text-gray-300 dark:border-gray-500/30';
        default:
            return 'bg-gray-500/10 text-gray-700 border-gray-500/20 dark:text-gray-300 dark:border-gray-500/30';
    }
};

const getIntentDotClass = (intent: RedditMention['intent'] | null | undefined) => {
    switch (intent) {
        case 'lead':
            return 'bg-blue-500 animate-pulse';
        case 'competitor':
            return 'bg-purple-500 animate-pulse';
        case 'brand_mention':
            return 'bg-indigo-500 animate-pulse';
        case 'feedback':
            return 'bg-cyan-500 animate-pulse';
        case 'hiring_opportunity':
            return 'bg-teal-500 animate-pulse';
        case 'irrelevant':
            return 'bg-gray-500 animate-pulse';
        default:
            return 'bg-gray-500 animate-pulse';
    }
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));

    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;
    if (diffInHours < 48) return 'Yesterday';
    return date.toLocaleDateString();
};

const formatDetailedDate = (dateString: string | null | undefined) => {
    if (!dateString) return 'Unknown date';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Copied!', {
            description: 'Suggested reply copied to clipboard',
        });
    } catch (err) {
        console.error('Failed to copy text: ', err);
        toast('Failed to copy!', {
            description: 'Failed to copy suggested reply to clipboard',
        });
    }
};

function getError(key: string) {
    return (personaForm.errors as Record<string, string>)[key];
}

const sentimentOptions = [
    { value: 'positive', label: 'Positive' },
    { value: 'negative', label: 'Negative' },
    { value: 'neutral', label: 'Neutral' },
];

const intentOptions = [
    { value: 'lead', label: 'Lead' },
    { value: 'competitor', label: 'Competitor' },
    { value: 'brand_mention', label: 'Brand Mention' },
    { value: 'feedback', label: 'Feedback' },
    { value: 'hiring_opportunity', label: 'Hiring Opportunity' },
    { value: 'irrelevant', label: 'Irrelevant' },
];

const methodOptions = [
    { value: 'email', label: 'Email' },
    { value: 'slack', label: 'Slack' },
    { value: 'discord', label: 'Discord' },
];

const selectedSentiments = ref<string[]>([]);
const selectedIntents = ref<string[]>([]);
const selectedMethods = ref<string[]>([]);
</script>

<template>
    <Head title="Mentions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto px-4 pt-4">
            <!-- Main Content Area -->
            <div>
                <!-- No Reddit Connection State -->
                <Card v-if="!hasRedditConnection" class="mx-auto max-w-md">
                    <CardHeader class="text-center">
                        <div class="mx-auto mb-2 flex h-14 w-14 items-center justify-center rounded-full bg-orange-50">
                            <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                <g id="SVGRepo_iconCarrier">
                                    <path
                                        d="M16 2C8.27812 2 2 8.27812 2 16C2 23.7219 8.27812 30 16 30C23.7219 30 30 23.7219 30 16C30 8.27812 23.7219 2 16 2Z"
                                        fill="#FC471E"
                                    ></path>
                                    <path
                                        fill-rule="evenodd"
                                        clip-rule="evenodd"
                                        d="M20.0193 8.90951C20.0066 8.98984 20 9.07226 20 9.15626C20 10.0043 20.6716 10.6918 21.5 10.6918C22.3284 10.6918 23 10.0043 23 9.15626C23 8.30819 22.3284 7.6207 21.5 7.6207C21.1309 7.6207 20.7929 7.7572 20.5315 7.98359L16.6362 7L15.2283 12.7651C13.3554 12.8913 11.671 13.4719 10.4003 14.3485C10.0395 13.9863 9.54524 13.7629 9 13.7629C7.89543 13.7629 7 14.6796 7 15.8103C7 16.5973 7.43366 17.2805 8.06967 17.6232C8.02372 17.8674 8 18.1166 8 18.3696C8 21.4792 11.5817 24 16 24C20.4183 24 24 21.4792 24 18.3696C24 18.1166 23.9763 17.8674 23.9303 17.6232C24.5663 17.2805 25 16.5973 25 15.8103C25 14.6796 24.1046 13.7629 23 13.7629C22.4548 13.7629 21.9605 13.9863 21.5997 14.3485C20.2153 13.3935 18.3399 12.7897 16.2647 12.7423L17.3638 8.24143L20.0193 8.90951ZM12.5 18.8815C13.3284 18.8815 14 18.194 14 17.3459C14 16.4978 13.3284 15.8103 12.5 15.8103C11.6716 15.8103 11 16.4978 11 17.3459C11 18.194 11.6716 18.8815 12.5 18.8815ZM19.5 18.8815C20.3284 18.8815 21 18.194 21 17.3459C21 16.4978 20.3284 15.8103 19.5 15.8103C18.6716 15.8103 18 16.4978 18 17.3459C18 18.194 18.6716 18.8815 19.5 18.8815ZM12.7773 20.503C12.5476 20.3462 12.2372 20.4097 12.084 20.6449C11.9308 20.8802 11.9929 21.198 12.2226 21.3548C13.3107 22.0973 14.6554 22.4686 16 22.4686C17.3446 22.4686 18.6893 22.0973 19.7773 21.3548C20.0071 21.198 20.0692 20.8802 19.916 20.6449C19.7628 20.4097 19.4524 20.3462 19.2226 20.503C18.3025 21.1309 17.1513 21.4449 16 21.4449C15.3173 21.4449 14.6345 21.3345 14 21.1137C13.5646 20.9621 13.1518 20.7585 12.7773 20.503Z"
                                        fill="white"
                                    ></path>
                                </g>
                            </svg>
                        </div>
                        <CardTitle>Connect Your Reddit Account</CardTitle>
                        <CardDescription> Connect your Reddit account to start monitoring keyword mentions. </CardDescription>
                    </CardHeader>
                    <CardContent class="text-center">
                        <Button @click="connectToReddit" :disabled="isLoading" class="w-full">
                            {{ isLoading ? 'Connecting...' : 'Connect Account' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Connected State - Your existing mentions content goes here -->
                <div v-else>
                    <!-- Token Expired Warning -->
                    <Card v-if="isTokenExpired" class="mb-4 border-destructive bg-destructive/5 dark:bg-destructive/10">
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Badge variant="destructive">Token Expired</Badge>
                                <p className="text-sm text-muted-foreground">
                                    Looks like your Reddit connection has expired. We usually fix this automatically, but sometimes it needs a little
                                    help. If things aren’t updating, try reconnecting your account under <strong>Configure</strong> →
                                    <strong>Reconnect</strong>. While your connection is inactive, keyword monitoring will be paused.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="rounded-br-none rounded-bl-none border-b-0 pb-0">
                        <CardHeader class="sticky top-0 z-10 flex-shrink-0 border-b bg-card">
                            <div class="flex flex-col items-start justify-start gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <CardTitle class="text-lg font-medium"> Reddit Mentions </CardTitle>
                                    <CardDescription> Track when your brand or keyword is mentioned on Reddit </CardDescription>
                                </div>
                                <div v-if="hasRedditConnection" class="flex items-center gap-2">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="outline" size="sm" :disabled="isLoading">
                                                <Settings class="h-4 w-4" />
                                                Settings
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>
                                                <div class="flex items-start gap-1">
                                                    <svg
                                                        viewBox="0 0 32 32"
                                                        class="!h-5 !w-5 flex-shrink-0"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                                        <g id="SVGRepo_iconCarrier">
                                                            <path
                                                                d="M16 2C8.27812 2 2 8.27812 2 16C2 23.7219 8.27812 30 16 30C23.7219 30 30 23.7219 30 16C30 8.27812 23.7219 2 16 2Z"
                                                                fill="#FC471E"
                                                            ></path>
                                                            <path
                                                                fill-rule="evenodd"
                                                                clip-rule="evenodd"
                                                                d="M20.0193 8.90951C20.0066 8.98984 20 9.07226 20 9.15626C20 10.0043 20.6716 10.6918 21.5 10.6918C22.3284 10.6918 23 10.0043 23 9.15626C23 8.30819 22.3284 7.6207 21.5 7.6207C21.1309 7.6207 20.7929 7.7572 20.5315 7.98359L16.6362 7L15.2283 12.7651C13.3554 12.8913 11.671 13.4719 10.4003 14.3485C10.0395 13.9863 9.54524 13.7629 9 13.7629C7.89543 13.7629 7 14.6796 7 15.8103C7 16.5973 7.43366 17.2805 8.06967 17.6232C8.02372 17.8674 8 18.1166 8 18.3696C8 21.4792 11.5817 24 16 24C20.4183 24 24 21.4792 24 18.3696C24 18.1166 23.9763 17.8674 23.9303 17.6232C24.5663 17.2805 25 16.5973 25 15.8103C25 14.6796 24.1046 13.7629 23 13.7629C22.4548 13.7629 21.9605 13.9863 21.5997 14.3485C20.2153 13.3935 18.3399 12.7897 16.2647 12.7423L17.3638 8.24143L20.0193 8.90951ZM12.5 18.8815C13.3284 18.8815 14 18.194 14 17.3459C14 16.4978 13.3284 15.8103 12.5 15.8103C11.6716 15.8103 11 16.4978 11 17.3459C11 18.194 11.6716 18.8815 12.5 18.8815ZM19.5 18.8815C20.3284 18.8815 21 18.194 21 17.3459C21 16.4978 20.3284 15.8103 19.5 15.8103C18.6716 15.8103 18 16.4978 18 17.3459C18 18.194 18.6716 18.8815 19.5 18.8815ZM12.7773 20.503C12.5476 20.3462 12.2372 20.4097 12.084 20.6449C11.9308 20.8802 11.9929 21.198 12.2226 21.3548C13.3107 22.0973 14.6554 22.4686 16 22.4686C17.3446 22.4686 18.6893 22.0973 19.7773 21.3548C20.0071 21.198 20.0692 20.8802 19.916 20.6449C19.7628 20.4097 19.4524 20.3462 19.2226 20.503C18.3025 21.1309 17.1513 21.4449 16 21.4449C15.3173 21.4449 14.6345 21.3345 14 21.1137C13.5646 20.9621 13.1518 20.7585 12.7773 20.503Z"
                                                                fill="white"
                                                            ></path>
                                                        </g>
                                                    </svg>
                                                    <div class="flex flex-col gap-1">
                                                        <p class="flex gap-2 text-sm text-muted-foreground">u/{{ redditCredential?.username }}</p>
                                                        <p class="text-xs text-muted-foreground">Reddit Account</p>
                                                        <Badge v-if="isTokenExpired" variant="destructive" class="text-xs"> Token Expired </Badge>
                                                    </div>
                                                </div>
                                            </DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click.prevent="showPersonas = true">
                                                <UserRoundCheck class="h-4 w-4" />
                                                Persona
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click.prevent="showKeywords = true">
                                                <AtSign class="h-4 w-4" />
                                                Keywords
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click.prevent="showDisconnectDialog = true"
                                                v-if="hasRedditConnection && !isTokenExpired"
                                            >
                                                <Unlink class="h-4 w-4" />
                                                Disconnect
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click.prevent="connectToReddit" v-if="hasRedditConnection && isTokenExpired">
                                                <Link class="h-4 w-4" />
                                                Reconnect
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="scroll h-[calc(100vh-19rem)] overflow-y-auto pt-0 sm:h-[calc(100vh-17rem)] md:h-[calc(100vh-14rem)]">
                            <div
                                class="rounded-lg border border-dashed p-6 text-center"
                                v-if="props.keywords.length === 0 && props.mentions.length === 0"
                            >
                                <AtSign class="mx-auto h-10 w-10 text-muted-foreground" />
                                <h3 class="mt-4 text-sm font-medium">Let's get you set up!</h3>
                                <p class="mt-1 mb-4 text-sm text-muted-foreground">
                                    To start tracking Reddit mentions, first tell us what keywords or phrases you'd like to monitor.
                                </p>
                                <Button @click.prevent="showKeywords = true">
                                    <PlusCircle class="h-4 w-4" />
                                    Add Your First Keyword
                                </Button>
                                <p class="mt-3 text-xs text-muted-foreground">Pro tip: Add brand names, products, or topics you care about</p>
                            </div>
                            <div
                                class="rounded-lg border border-dashed p-6 text-center"
                                v-if="props.keywords.length === 0 && props.mentions.length > 0"
                            >
                                <AtSign class="mx-auto h-10 w-10 text-muted-foreground" />
                                <h3 class="mt-4 text-sm font-medium">Your keyword monitoring is paused</h3>
                                <p class="mt-1 mb-4 text-sm text-muted-foreground">
                                    You currently don't have any active keywords to monitor, but you can still view all your past monitoring results.
                                </p>
                                <Button @click.prevent="showKeywords = true">
                                    <PlusCircle class="h-4 w-4" />
                                    Add Keywords
                                </Button>
                                <p class="mt-3 text-xs text-muted-foreground">Pro tip: Add brand names, products, or topics you care about</p>
                            </div>
                            <div
                                class="rounded-lg border border-dashed p-6 text-center"
                                v-else-if="props.mentions.length === 0 && !props.dispatch_at && props.keywords.length > 0"
                            >
                                <AtSign class="mx-auto h-10 w-10 text-muted-foreground" />
                                <h3 class="mt-4 text-sm font-medium">Ready to start monitoring</h3>
                                <p class="mt-1 mb-4 text-sm text-muted-foreground">
                                    You have <span class="font-medium">{{ props.keywords.length }}</span>
                                    {{ props.keywords.length === 1 ? 'keyword' : 'keywords' }} set up. Click below to begin tracking Reddit mentions.
                                </p>
                                <Button @click="startMonitoring">
                                    <Play class="h-4 w-4" />
                                    Start Monitoring
                                </Button>
                                <p class="mt-3 text-xs text-muted-foreground">We'll notify you as soon as we find matching mentions</p>
                            </div>
                            <div class="rounded-lg border border-dashed p-6 text-center" v-else-if="props.mentions.length === 0 && props.dispatch_at">
                                <Radar class="mx-auto h-10 w-10 animate-pulse text-muted-foreground" />
                                <h3 class="mt-4 text-sm font-medium">We’re on it</h3>
                                <p class="mt-1 mb-4 text-sm text-muted-foreground">
                                    Monitoring <span class="font-medium">{{ props.keywords.length }}</span>
                                    {{ props.keywords.length === 1 ? 'keyword' : 'keywords' }} on Reddit.
                                </p>
                                <p class="mt-3 text-xs text-muted-foreground">It may take 5–10 minutes to see results on your first run.</p>
                            </div>
                            <div class="mt-3 space-y-4" v-if="props.mentions.length > 0">
                                <div
                                    v-for="mention in props.mentions"
                                    :key="mention.id"
                                    class="group relative cursor-pointer overflow-hidden rounded-xl border border-border/40 bg-gradient-to-br from-card/50 to-card/80 p-5 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-lg hover:shadow-primary/5"
                                    @click="
                                        selectedMention = mention;
                                        showMentionDialog = true;
                                    "
                                >
                                    <!-- Hover glow effect -->
                                    <div
                                        class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-accent/5 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    ></div>

                                    <!-- Content container -->
                                    <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start">
                                        <!-- Left section - Badges -->
                                        <div
                                            class="flex flex-row flex-wrap gap-2 lg:flex-col lg:items-start lg:space-y-1 lg:border-r lg:border-secondary lg:pr-3"
                                        >
                                            <div class="grid grid-cols-1 gap-1 text-start">
                                                <p class="text-xs text-muted-foreground">Sentiment:</p>
                                                <!-- Sentiment badge with enhanced styling -->
                                                <Badge :class="getSentimentBadgeClass(mention.sentiment)">
                                                    <span class="flex items-center gap-1.5 text-xs">
                                                        <div :class="getSentimentDotClass(mention.sentiment)" class="h-1.5 w-1.5 rounded-full"></div>
                                                        {{ mention.sentiment }} ({{ (mention.sentiment_confidence * 100).toFixed(0) }}%)
                                                    </span>
                                                </Badge>
                                            </div>

                                            <div class="grid grid-cols-1 gap-1 text-start">
                                                <p class="text-xs text-muted-foreground">Intent:</p>
                                                <Badge :class="getIntentBadgeClass(mention.intent)">
                                                    <span class="flex items-center gap-1.5 text-xs">
                                                        <div :class="getIntentDotClass(mention.intent)" class="h-1.5 w-1.5 rounded-full"></div>
                                                        {{ mention.intent }} ({{ (mention.intent_confidence * 100).toFixed(0) }}%)
                                                    </span>
                                                </Badge>
                                            </div>
                                        </div>

                                        <!-- Main content section -->
                                        <div class="min-w-0 flex-1 space-y-3">
                                            <!-- Meta information with improved styling -->
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md bg-orange-500/10 px-2 py-1 text-orange-600 dark:text-orange-400"
                                                >
                                                    <span class="font-medium">r/{{ mention.subreddit }}</span>
                                                </span>

                                                <span class="text-muted-foreground/60">•</span>

                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md bg-blue-500/10 px-2 py-1 text-blue-600 dark:text-blue-400"
                                                >
                                                    <span class="font-medium">u/{{ mention.author }}</span>
                                                </span>

                                                <span class="text-muted-foreground/60">•</span>

                                                <span class="text-muted-foreground">
                                                    {{ formatDate(mention.reddit_created_at) }}
                                                </span>

                                                <span class="text-muted-foreground/60">•</span>

                                                <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                    {{ mention.upvotes.toLocaleString() }}
                                                </span>

                                                <span
                                                    v-if="mention.comment_count > 0"
                                                    class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                                        />
                                                    </svg>
                                                    {{ mention.comment_count }}
                                                </span>
                                                <span class="text-muted-foreground/60">•</span>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md bg-pink-500/10 px-2 py-1 text-pink-600 dark:text-pink-400"
                                                >
                                                    <span class="font-medium">{{ mention.keyword }}</span>
                                                </span>
                                            </div>

                                            <!-- Content with enhanced typography -->
                                            <div class="space-y-2">
                                                <p
                                                    class="line-clamp-3 text-sm leading-relaxed text-foreground/90 transition-colors group-hover:text-foreground"
                                                    :title="mention.content"
                                                >
                                                    {{ mention.content }}
                                                </p>

                                                <!-- Read more indicator -->
                                                <div
                                                    class="flex items-center text-xs text-primary/70 opacity-0 transition-all duration-200 group-hover:opacity-100"
                                                >
                                                    <span>Click to read more</span>
                                                    <svg
                                                        class="ml-1 h-3 w-3 transition-transform group-hover:translate-x-0.5"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- External link button with enhanced styling -->
                                        <div class="flex lg:flex-col lg:items-end">
                                            <a
                                                :href="mention.url"
                                                target="_blank"
                                                @click.stop
                                                class="group/link flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border/40 bg-background/50 text-muted-foreground backdrop-blur-sm transition-all duration-200 hover:border-primary/40 hover:bg-primary/5 hover:text-primary hover:shadow-md"
                                            >
                                                <ExternalLink class="h-4 w-4 transition-transform group-hover/link:scale-110" />
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Bottom gradient line -->
                                    <div
                                        class="absolute right-0 bottom-0 left-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
        <AlertDialog v-model:open="showDisconnectDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Disconnect Reddit Account</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to disconnect your Reddit account? This will stop all mention monitoring and you'll need to reconnect to
                        resume tracking.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDisconnectDialog = false">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="handleDisconnectReddit" :disabled="isLoading">
                        {{ isLoading ? 'Disconnecting...' : 'Disconnect' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <Dialog v-model:open="showKeywords">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reddit Keywords</DialogTitle>
                    <DialogDescription> Manage your Reddit keyword monitoring settings. </DialogDescription>
                </DialogHeader>

                <!-- Add Button -->
                <div class="px-2">
                    <Button
                        v-if="props.keywords.length > 0"
                        @click="openCreateModal"
                        variant="ghost"
                        class="h-12 w-full border border-dashed border-border text-muted-foreground hover:border-foreground/20 hover:text-foreground"
                    >
                        <Plus class="h-4 w-4" />
                        Add Keyword
                    </Button>
                </div>

                <!-- Keywords List -->
                <div class="scroll max-h-96 space-y-4 overflow-y-auto px-2">
                    <div v-if="props.keywords.length === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">No keywords configured yet. Click "Add Keywords" to get started.</p>
                        <Button @click="openCreateModal" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Keywords
                        </Button>
                    </div>

                    <template v-else>
                        <!-- Minimalistic Keyword Cards -->
                        <div class="space-y-3">
                            <!-- Keyword Cards -->
                            <div
                                v-for="keyword in props.keywords"
                                :key="keyword.id"
                                class="group rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
                            >
                                <!-- Header Row -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-2 shrink-0 rounded-full bg-green-500" />
                                        <h3 class="font-medium text-foreground">{{ keyword.keyword }}</h3>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <Button @click="editKeyword(keyword)" variant="outline" size="sm">
                                            <Edit class="h-0.5 w-0.5" />
                                        </Button>
                                        <Button @click="showConfirmDeleteKeywordDialog(keyword.id)" variant="outline" size="sm">
                                            <Trash2 class="h-1 w-0.5 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showPersonas">
            <DialogContent class="scroll max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Persona</DialogTitle>
                    <DialogDescription> Define your persona to shape how the AI suggests replies—tailored to your style. </DialogDescription>
                </DialogHeader>

                <!-- Add Button -->
                <div class="px-2">
                    <Button
                        v-if="props.personas.length > 0"
                        @click="openCreatePersonaModal"
                        variant="ghost"
                        class="h-12 w-full border border-dashed border-border text-muted-foreground hover:border-foreground/20 hover:text-foreground"
                    >
                        <Plus class="h-4 w-4" />
                        Add Persona
                    </Button>
                </div>

                <!-- Keywords List -->
                <div class="scroll max-h-96 space-y-4 overflow-y-auto px-2">
                    <div v-if="props.personas.length === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">No personas configured yet. Click "Add Personas" to get started.</p>
                        <Button @click="openCreatePersonaModal" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Personas
                        </Button>
                    </div>

                    <template v-else>
                        <!-- Minimalistic Keyword Cards -->
                        <div class="space-y-3">
                            <!-- Keyword Cards -->
                            <div
                                v-for="persona in props.personas"
                                :key="persona.id"
                                class="group rounded-lg border border-border p-4 transition-colors hover:bg-muted/30"
                            >
                                <!-- Header Row -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-2 shrink-0 rounded-full bg-green-500" />
                                        <div class="flex gap-1 items-center">
                                            <h3 class="font-medium text-foreground">
                                                {{ persona.name }}
                                            </h3>
                                            <p class="text-xs text-muted-foreground">
                                                - {{ persona.user_type }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <Button @click="editPersona(persona)" variant="outline" size="sm">
                                            <Edit class="h-0.5 w-0.5" />
                                        </Button>
                                        <Button @click="showConfirmDeletePersonaDialog(persona.id)" variant="outline" size="sm">
                                            <Trash2 class="h-1 w-0.5 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Add/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="w-full md:min-w-3xl lg:min-w-4xl">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit' : 'Add' }} Keyword</DialogTitle>
                    <DialogDescription> Configure your Reddit keyword monitoring settings. </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm">
                    <div class="scroll max-h-[60vh] w-full space-y-6 overflow-y-auto p-3">
                        <!-- Keyword Input -->
                        <div class="space-y-2">
                            <Label for="keyword">Keyword</Label>
                            <Input id="keyword" v-model="form.keyword" placeholder="Enter keyword to monitor" />
                            <p v-if="form.errors.keyword" class="text-sm text-red-500">{{ form.errors.keyword }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Subreddits (Optional)</Label>
                            <div class="space-y-3">
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <Input
                                            v-model="subredditSearch"
                                            placeholder="Search for subreddits..."
                                            @input="searchSubreddits"
                                            :disabled="isSearching || form.subreddits.length >= 3"
                                        />
                                        <Search
                                            v-if="!isSearching"
                                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground"
                                        />
                                        <Loader2
                                            v-else
                                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 transform animate-spin text-muted-foreground"
                                        />
                                    </div>
                                </div>

                                <!-- Subreddit Search Results -->
                                <div
                                    v-if="subredditResults.length > 0 && form.subreddits.length < 3"
                                    class="scroll max-h-48 overflow-y-auto rounded-md border p-3"
                                >
                                    <div class="space-y-1">
                                        <div
                                            v-for="subreddit in subredditResults"
                                            :key="subreddit.name"
                                            class="flex cursor-pointer items-center justify-between rounded p-2 hover:bg-muted"
                                            :class="{
                                                'cursor-not-allowed opacity-50':
                                                    form.subreddits.length >= 3 && !form.subreddits.includes(subreddit.name),
                                            }"
                                            @click="toggleSubreddit(subreddit.name)"
                                        >
                                            <div>
                                                <div class="font-medium">r/{{ subreddit.name }}</div>
                                                <div class="text-xs text-muted-foreground">{{ subreddit.subscribers.toLocaleString() }} members</div>
                                            </div>
                                            <Check v-if="form.subreddits.includes(subreddit.name)" class="h-3 w-3" />
                                            <Plus v-else-if="form.subreddits.length < 3" class="h-3 w-3" />
                                            <span v-else class="text-xs text-muted-foreground">Max reached</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Max limit message -->
                                <div v-if="form.subreddits.length >= 3" class="rounded-md border border-amber-200 bg-amber-50 p-3">
                                    <p class="text-sm text-amber-800">You've reached the maximum of 3 subreddits. Remove one to add another.</p>
                                </div>

                                <!-- Selected Subreddits -->
                                <div v-if="form.subreddits.length > 0" class="space-y-2">
                                    <div class="text-sm font-medium">Selected Subreddits:</div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="subreddit in form.subreddits" :key="subreddit" variant="secondary" class="gap-1 !pr-0.5">
                                            r/{{ subreddit }}
                                            <Button type="button" @click="toggleSubreddit(subreddit)" size="icon" variant="ghost" class="h-5 w-5">
                                                <X class="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground">You can only add up to 3 subreddits to a keyword</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="persona">Persona</Label>
                            <Select v-model="form.persona_id" id="persona">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select persona">
                                        {{
                                            (() => {
                                                const selectedPersona = personas.find((p) => p.id.toString() === form.persona_id?.toString());
                                                return selectedPersona ? `${selectedPersona.name} - ${selectedPersona.user_type}` : 'Select persona';
                                            })()
                                        }}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="persona in personas" :key="persona.id" :value="persona.id.toString()">
                                        {{ persona.name }} - {{ persona.user_type }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">
                                Your persona helps us understand your preferences and give more context about you. This lets us generate replies that are more relevant, helpful, and tailored to your needs. You can create or update your persona anytime in Configure → Persona.
                            </p>
                            <p v-if="form.errors.persona_id" class="text-sm text-red-500">
                                {{ form.errors.persona_id }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Matching Rules</h3>

                            <div class="flex items-center space-x-2">
                                <Checkbox id="match_whole_word" v-model="form.match_whole_word" />
                                <Label for="match_whole_word" class="text-sm"> Match exact word only (not parts of other words) </Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Checkbox id="case_sensitive" v-model="form.case_sensitive" />
                                <Label for="case_sensitive" class="text-sm"> Match case exactly (e.g., "Word" ≠ "word") </Label>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Alert Settings</h3>

                            <div class="flex items-center space-x-2">
                                <Switch id="alert-enabled" v-model="form.alert_enabled" />
                                <Label for="airplane-mode">Enabled</Label>
                            </div>

                            <div class="w-full space-y-2">
                                <Label for="alert_sentiment">Sentiment</Label>
                                <div class="w-full">
                                    <MultiSelectCombobox
                                        id="alert_sentiment"
                                        v-model="selectedSentiments"
                                        :items="sentimentOptions"
                                        placeholder="Choose sentiments..."
                                        class-name="gap-2 w-full"
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Select the sentiment types you want to monitor. You'll only get alerts for mentions that match your selected
                                    sentiments.
                                </p>
                                <p v-if="form.errors.alert_sentiments" class="text-sm text-red-500">
                                    {{ form.errors.alert_sentiments }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="alert_intent">Intent</Label>
                                <MultiSelectCombobox
                                    id="alert_intent"
                                    v-model="selectedIntents"
                                    :items="intentOptions"
                                    placeholder="Choose intents..."
                                    class-name="px-3 gap-2 w-full"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Select the intent types you want to monitor. You'll only get alerts for mentions that match your selected intents.
                                </p>
                                <p v-if="form.errors.alert_intents" class="text-sm text-red-500">
                                    {{ form.errors.alert_intents }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="alert_channel">Channel</Label>
                                <MultiSelectCombobox
                                    id="alert_channel"
                                    v-model="selectedMethods"
                                    :items="methodOptions"
                                    placeholder="Choose channels..."
                                    class-name="px-3 gap-2 w-full"
                                />
                                <p class="text-sm text-muted-foreground">Select the channels you want to receive alerts on.</p>
                                <p v-if="form.errors.alert_methods" class="text-sm text-red-500">
                                    {{ form.errors.alert_methods }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="bg-background pt-6">
                        <Button type="button" variant="outline" @click="closeModal"> Cancel </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isEditing ? 'Update' : 'Add' }} Keyword
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Add/Edit Modal -->
        <Dialog v-model:open="isPersonaModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ isEditingPersona ? 'Edit' : 'Add' }} Persona</DialogTitle>
                    <DialogDescription>Configure your persona settings.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitPersonaForm" class="scroll grid max-h-[90vh] w-full gap-6 overflow-y-auto">
                    <!-- Persona Name -->
                    <div class="space-y-2">
                        <Label for="persona">Persona Name</Label>
                        <Input id="persona" v-model="personaForm.name" placeholder="Enter name of persona" />
                        <p v-if="personaForm.errors.name" class="text-sm text-red-500">
                            {{ personaForm.errors.name }}
                        </p>
                    </div>

                    <!-- User Type Selection -->
                    <div class="w-full space-y-2">
                        <Label for="user_type">User Type</Label>
                        <Select v-model="personaForm.user_type" @update:modelValue="resetSettings as (value: typeof personaForm.user_type) => void">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select user type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="small_business">Small Business</SelectItem>
                                <SelectItem value="marketing">Marketing</SelectItem>
                                <SelectItem value="content_creator">Content Creator</SelectItem>
                                <SelectItem value="customer_support">Customer Support</SelectItem>
                                <SelectItem value="market_researcher">Market Researcher</SelectItem>
                                <SelectItem value="freelancer">Freelancer</SelectItem>
                                <SelectItem value="pr_crisis">PR Crisis</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="personaForm.errors.user_type" class="text-sm text-red-500">
                            {{ personaForm.errors.user_type }}
                        </p>
                    </div>

                    <!-- Dynamic Settings Based on User Type -->
                    <div v-if="personaForm.user_type" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium">Settings</h3>
                            <p v-if="personaForm.errors.settings" class="text-sm text-red-500">
                                {{ personaForm.errors.settings }}
                            </p>
                        </div>

                        <!-- Small Business Settings -->
                        <template v-if="personaForm.user_type === 'small_business'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="business_name">Business Name</Label>
                                    <Input id="business_name" v-model="personaSettings.business_name" placeholder="e.g., ResumePro" />
                                    <p v-if="getError('business_name')" class="text-sm text-red-500">
                                        {{ getError('business_name') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="business_description">Business Description</Label>
                                    <Textarea
                                        id="business_description"
                                        v-model="personaSettings.business_description"
                                        placeholder="Describe your business..."
                                        rows="3"
                                    />
                                    <p v-if="getError('business_description')" class="text-sm text-red-500">
                                        {{ getError('business_description') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="industry_niche">Industry Niche</Label>
                                    <Input id="industry_niche" v-model="personaSettings.industry_niche" placeholder="e.g., HR tech, e-commerce" />
                                    <p v-if="getError('industry_niche')" class="text-sm text-red-500">
                                        {{ getError('industry_niche') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Marketing Settings -->
                        <template v-if="personaForm.user_type === 'marketing'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="brand_name">Brand Name</Label>
                                    <Input id="brand_name" v-model="personaSettings.brand_name" placeholder="e.g., BrandX" />
                                    <p v-if="getError('brand_name')" class="text-sm text-red-500">
                                        {{ getError('brand_name') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="brand_description">Brand Description</Label>
                                    <Textarea
                                        id="brand_description"
                                        v-model="personaSettings.brand_description"
                                        placeholder="Describe your brand..."
                                        rows="3"
                                    />
                                    <p v-if="getError('brand_description')" class="text-sm text-red-500">
                                        {{ getError('brand_description') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="engagement_goal">Engagement Goal</Label>
                                    <Select v-model="personaSettings.engagement_goal">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select goal" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="brand_awareness">Brand Awareness</SelectItem>
                                            <SelectItem value="reputation_management">Reputation Management</SelectItem>
                                            <SelectItem value="market_research">Market Research</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="getError('engagement_goal')" class="text-sm text-red-500">
                                        {{ getError('engagement_goal') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Content Creator Settings -->
                        <template v-if="personaForm.user_type === 'content_creator'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="creator_niche">Creator Niche</Label>
                                    <Input id="creator_niche" v-model="personaSettings.creator_niche" placeholder="e.g., gaming, fitness" />
                                    <p v-if="getError('creator_niche')" class="text-sm text-red-500">
                                        {{ getError('creator_niche') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="engagement_style">Engagement Style</Label>
                                    <Select v-model="personaSettings.engagement_style">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select style" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="storytelling">Storytelling</SelectItem>
                                            <SelectItem value="question_asking">Question Asking</SelectItem>
                                            <SelectItem value="sharing_tips">Sharing Tips</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="getError('engagement_style')" class="text-sm text-red-500">
                                        {{ getError('engagement_style') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Customer Support Settings -->
                        <template v-if="personaForm.user_type === 'customer_support'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="brand_name">Brand Name</Label>
                                    <Input id="brand_name" v-model="personaSettings.brand_name" placeholder="e.g., TechCorp" />
                                    <p v-if="getError('brand_name')" class="text-sm text-red-500">
                                        {{ getError('brand_name') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="brand_description">Brand Description</Label>
                                    <Textarea
                                        id="brand_description"
                                        v-model="personaSettings.brand_description"
                                        placeholder="Describe your brand..."
                                        rows="3"
                                    />
                                    <p v-if="getError('brand_description')" class="text-sm text-red-500">
                                        {{ getError('brand_description') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="product_service">Product/Service</Label>
                                    <Input id="product_service" v-model="personaSettings.product_service" placeholder="e.g., ATS software" />
                                    <p v-if="getError('product_service')" class="text-sm text-red-500">
                                        {{ getError('product_service') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="support_contact">Support Contact</Label>
                                    <Input
                                        id="support_contact"
                                        v-model="personaSettings.support_contact"
                                        placeholder="e.g., DM us, support@techcorp.com"
                                    />
                                    <p v-if="getError('support_contact')" class="text-sm text-red-500">
                                        {{ getError('support_contact') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Market Researcher Settings -->
                        <template v-if="personaForm.user_type === 'market_researcher'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="research_focus">Research Focus</Label>
                                    <Input id="research_focus" v-model="personaSettings.research_focus" placeholder="e.g., electric vehicles" />
                                    <p v-if="getError('research_focus')" class="text-sm text-red-500">
                                        {{ getError('research_focus') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="question_style">Question Style</Label>
                                    <Select v-model="personaSettings.question_style">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select style" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="open_ended">Open Ended</SelectItem>
                                            <SelectItem value="specific">Specific</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="getError('question_style')" class="text-sm text-red-500">
                                        {{ getError('question_style') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Freelancer Settings -->
                        <template v-if="personaForm.user_type === 'freelancer'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="expertise_area">Expertise Area</Label>
                                    <Input id="expertise_area" v-model="personaSettings.expertise_area" placeholder="e.g., web design" />
                                    <p v-if="getError('expertise_area')" class="text-sm text-red-500">
                                        {{ getError('expertise_area') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="engagement_approach">Engagement Approach</Label>
                                    <Select v-model="personaSettings.engagement_approach">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select approach" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="offering_tips">Offering Tips</SelectItem>
                                            <SelectItem value="answering_questions">Answering Questions</SelectItem>
                                            <SelectItem value="sharing_experiences">Sharing Experiences</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="getError('engagement_approach')" class="text-sm text-red-500">
                                        {{ getError('engagement_approach') }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- PR Crisis Settings -->
                        <template v-if="personaForm.user_type === 'pr_crisis'">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="brand_name">Brand Name</Label>
                                    <Input id="brand_name" v-model="personaSettings.brand_name" placeholder="e.g., BrandX" />
                                    <p v-if="getError('brand_name')" class="text-sm text-red-500">
                                        {{ getError('brand_name') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="brand_description">Brand Description</Label>
                                    <Textarea
                                        id="brand_description"
                                        v-model="personaSettings.brand_description"
                                        placeholder="Describe your brand..."
                                        rows="3"
                                    />
                                    <p v-if="getError('brand_description')" class="text-sm text-red-500">
                                        {{ getError('brand_description') }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="escalation_contact">Escalation Contact</Label>
                                    <Input id="escalation_contact" v-model="personaSettings.escalation_contact" placeholder="e.g., pr@brandx.com" />
                                    <p v-if="getError('escalation_contact')" class="text-sm text-red-500">
                                        {{ getError('escalation_contact') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closePersonaModal"> Cancel </Button>
                        <Button type="submit" :disabled="personaForm.processing">
                            <Loader2 v-if="personaForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isEditingPersona ? 'Update' : 'Add' }} Persona
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Teleport to="body">
            <AlertDialog v-model:open="showDeleteKeywordDialog" class="!z-50">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Keyword</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this keyword? This will remove it from your list of monitored keywords.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="showDeleteKeywordDialog = false">Cancel</AlertDialogCancel>
                        <AlertDialogAction @click="deleteKeyword" :disabled="isLoading">
                            {{ isLoading ? 'Deleting...' : 'Delete' }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Teleport>

        <Teleport to="body">
            <AlertDialog v-model:open="showDeletePersonaDialog" class="!z-50">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Persona</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this persona? This will remove it from your list of personas.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="showDeleteKeywordDialog = false">Cancel</AlertDialogCancel>
                        <AlertDialogAction @click="deletePersona" :disabled="isLoading">
                            {{ isLoading ? 'Deleting...' : 'Delete' }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Teleport>

        <!-- Mention Detail Dialog -->
        <Dialog :open="showMentionDialog" @update:open="showMentionDialog = $event">
            <DialogContent class="max-h-[95vh] w-full overflow-hidden border-0 p-0 backdrop-blur-xl md:min-w-3xl lg:min-w-4xl [&>button]:hidden">
                <!-- Header with gradient background -->
                <div class="relative overflow-hidden bg-gradient-to-r from-primary/5 via-accent/5 to-primary/5 px-6 py-5">
                    <!-- Background pattern -->
                    <div class="bg-grid-pattern absolute inset-0 opacity-5"></div>

                    <DialogHeader class="relative space-y-3">
                        <DialogDescription class="sr-only"> Mention details for {{ selectedMention?.title }} </DialogDescription>
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <DialogTitle class="flex flex-wrap items-center gap-2 text-lg font-semibold">
                                <!-- Subreddit with icon -->
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500/10 px-3 py-1 text-orange-600 dark:text-orange-400"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                        <g id="SVGRepo_iconCarrier">
                                            <path
                                                d="M16 2C8.27812 2 2 8.27812 2 16C2 23.7219 8.27812 30 16 30C23.7219 30 30 23.7219 30 16C30 8.27812 23.7219 2 16 2Z"
                                                fill="#FC471E"
                                            ></path>
                                            <path
                                                fill-rule="evenodd"
                                                clip-rule="evenodd"
                                                d="M20.0193 8.90951C20.0066 8.98984 20 9.07226 20 9.15626C20 10.0043 20.6716 10.6918 21.5 10.6918C22.3284 10.6918 23 10.0043 23 9.15626C23 8.30819 22.3284 7.6207 21.5 7.6207C21.1309 7.6207 20.7929 7.7572 20.5315 7.98359L16.6362 7L15.2283 12.7651C13.3554 12.8913 11.671 13.4719 10.4003 14.3485C10.0395 13.9863 9.54524 13.7629 9 13.7629C7.89543 13.7629 7 14.6796 7 15.8103C7 16.5973 7.43366 17.2805 8.06967 17.6232C8.02372 17.8674 8 18.1166 8 18.3696C8 21.4792 11.5817 24 16 24C20.4183 24 24 21.4792 24 18.3696C24 18.1166 23.9763 17.8674 23.9303 17.6232C24.5663 17.2805 25 16.5973 25 15.8103C25 14.6796 24.1046 13.7629 23 13.7629C22.4548 13.7629 21.9605 13.9863 21.5997 14.3485C20.2153 13.3935 18.3399 12.7897 16.2647 12.7423L17.3638 8.24143L20.0193 8.90951ZM12.5 18.8815C13.3284 18.8815 14 18.194 14 17.3459C14 16.4978 13.3284 15.8103 12.5 15.8103C11.6716 15.8103 11 16.4978 11 17.3459C11 18.194 11.6716 18.8815 12.5 18.8815ZM19.5 18.8815C20.3284 18.8815 21 18.194 21 17.3459C21 16.4978 20.3284 15.8103 19.5 15.8103C18.6716 15.8103 18 16.4978 18 17.3459C18 18.194 18.6716 18.8815 19.5 18.8815ZM12.7773 20.503C12.5476 20.3462 12.2372 20.4097 12.084 20.6449C11.9308 20.8802 11.9929 21.198 12.2226 21.3548C13.3107 22.0973 14.6554 22.4686 16 22.4686C17.3446 22.4686 18.6893 22.0973 19.7773 21.3548C20.0071 21.198 20.0692 20.8802 19.916 20.6449C19.7628 20.4097 19.4524 20.3462 19.2226 20.503C18.3025 21.1309 17.1513 21.4449 16 21.4449C15.3173 21.4449 14.6345 21.3345 14 21.1137C13.5646 20.9621 13.1518 20.7585 12.7773 20.503Z"
                                                fill="white"
                                            ></path>
                                        </g>
                                    </svg>
                                    r/{{ selectedMention?.subreddit }}
                                </span>
                            </DialogTitle>

                            <div class="flex flex-wrap gap-2">
                                <!-- Sentiment badge in header -->
                                <div class="grid grid-cols-1 gap-1 text-start">
                                    <p class="text-xs text-muted-foreground">Sentiment:</p>
                                    <!-- Sentiment badge with enhanced styling -->
                                    <Badge :class="getSentimentBadgeClass(selectedMention?.sentiment)">
                                        <span class="flex items-center gap-1.5 text-xs">
                                            <div :class="getSentimentDotClass(selectedMention?.sentiment)" class="h-1.5 w-1.5 rounded-full"></div>
                                            {{ selectedMention?.sentiment }} ({{ ((selectedMention?.sentiment_confidence ?? 0) * 100).toFixed(0) }}%)
                                        </span>
                                    </Badge>
                                </div>

                                <div class="grid grid-cols-1 gap-1 text-start">
                                    <p class="text-xs text-muted-foreground">Intent:</p>
                                    <Badge :class="getIntentBadgeClass(selectedMention?.intent)">
                                        <span class="flex items-center gap-1.5 text-xs">
                                            <div :class="getIntentDotClass(selectedMention?.intent)" class="h-1.5 w-1.5 rounded-full"></div>
                                            {{ selectedMention?.intent }} ({{ ((selectedMention?.intent_confidence ?? 0) * 100).toFixed(0) }}%)
                                        </span>
                                    </Badge>
                                </div>

                                <div class="grid grid-cols-1 gap-1 text-start">
                                    <p class="text-xs text-muted-foreground">Keyword:</p>
                                    <Badge class="border-pink-500/20 bg-pink-500/10 text-pink-700 dark:border-pink-500/30 dark:text-pink-300">
                                        <span class="flex items-center gap-1.5 text-xs">
                                            <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-pink-500"></div>
                                            {{ selectedMention?.keyword }}
                                        </span>
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- Author and date info -->
                        <div class="flex flex-wrap items-center gap-4 text-start text-sm">
                            <div class="inline-flex items-center gap-2 rounded-lg bg-blue-500/10 px-3 py-1.5 text-blue-600 dark:text-blue-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                                <span class="font-medium">u/{{ selectedMention?.author }}</span>
                            </div>

                            <div class="flex items-center gap-2 text-muted-foreground">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <span>{{ formatDetailedDate(selectedMention?.reddit_created_at) }}</span>
                            </div>

                            <!-- Stats -->
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="font-medium">{{ selectedMention?.upvotes?.toLocaleString() }}</span>
                                </div>

                                <div
                                    v-if="selectedMention && selectedMention?.comment_count > 0"
                                    class="flex items-center gap-1 text-purple-600 dark:text-purple-400"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                        />
                                    </svg>
                                    <span class="font-medium">{{ selectedMention?.comment_count }}</span>
                                </div>
                            </div>
                        </div>

                        <p v-if="selectedMention?.title" class="text-start text-sm font-bold text-foreground">
                            {{ selectedMention?.title }}
                        </p>
                    </DialogHeader>
                </div>

                <!-- Content area with custom scrollbar -->
                <div class="flex-1 overflow-hidden px-6 py-4">
                    <div class="scroll max-h-[50vh] space-y-4 overflow-y-auto pr-2">
                        <!-- Content card -->
                        <div class="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10 p-6 backdrop-blur-sm">
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                <p class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                                    {{ selectedMention?.content }}
                                </p>
                            </div>
                        </div>

                        <!-- Suggested Reply card -->
                        <div
                            v-if="selectedMention?.suggested_reply"
                            class="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10 p-6 backdrop-blur-sm"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="mb-3 flex items-start gap-2">
                                        <MessageCircle class="w-12 text-primary" />
                                        <div class="flex flex-col gap-2">
                                            <div class="flex items-center gap-1">
                                                <h3 class="text-sm font-semibold text-foreground">
                                                    AI Suggested Reply
                                                </h3>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span tabindex="-1"> <!-- Makes it unfocusable -->
                                                                <Info class="w-4 h-4 text-muted-foreground" />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent class="max-w-[300px] p-3 z-[100]">
                                                            <div class="space-y-2">
                                                                <template v-if="selectedMention?.persona">
                                                                    <p class="text-lg mb-2 font-bold">
                                                                        Persona
                                                                    </p>
                                                                    <div 
                                                                        v-for="(value, key) in selectedMention.persona" 
                                                                        :key="key"
                                                                        class="break-words"
                                                                    >
                                                                        <span class="font-semibold capitalize text-sm">
                                                                        {{ String(key).replace(/_/g, ' ') }}:
                                                                        </span>
                                                                        <p class="text-sm mt-0.5">
                                                                        {{ typeof value === 'object' ? JSON.stringify(value) : value }}
                                                                        </p>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                            <p class="text-xs text-muted-foreground">
                                                This reply was generated by AI based on the post content and your selected persona. It’s crafted to feel like a real Reddit comment — with the tone, slang, casual phrasing, and even capitalization quirks you'd expect from actual users. Still, give it a quick review to make sure it fits how you want to sound.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="my-2 h-[0.5px] w-full rounded bg-primary/10"></div>
                                    <div class="prose prose-sm dark:prose-invert max-w-none">
                                        <p class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/80">
                                            {{ selectedMention?.suggested_reply }}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="copyToClipboard(selectedMention?.suggested_reply)"
                                    class="h-8 w-8 shrink-0 p-0"
                                >
                                    <Copy class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced footer with gradient -->
                <div class="border-t border-border/40 bg-gradient-to-r from-muted/20 to-muted/10 px-6 py-4 backdrop-blur-sm">
                    <!-- Action buttons -->
                    <div class="flex justify-end gap-2 sm:order-2">
                        <Button variant="outline" @click="showMentionDialog = false" class="transition-all hover:bg-muted/50"> Close </Button>
                        <Button
                            @click="visitRedditPost(selectedMention?.url)"
                            class="group bg-gradient-to-r from-primary to-primary/80 transition-all hover:from-primary/90 hover:to-primary/70 hover:shadow-lg"
                        >
                            <ExternalLink class="h-4 w-4 transition-transform group-hover:scale-110" />
                            View on Reddit
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
