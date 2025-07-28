<script setup lang="ts">
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
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { RedditCredential, RedditKeyword, SubredditResult } from '@/types/mentions';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AtSign, Check, Edit, Link, Loader2, Play, Plus, Search, Settings, Trash2, Unlink, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    keywords: RedditKeyword[];
    credentials: RedditCredential;
}

const props = defineProps<Props>();
const isLoading = ref(false);
const showDisconnectDialog = ref(false);
const showDeleteKeywordDialog = ref(false);
const deleteKeywordId = ref<number | null>(null);
const showKeywords = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mentions',
        href: '/mentions',
    },
];

const redditCredential = computed(() => props.credentials);
const hasRedditConnection = computed(() => !!redditCredential.value);

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
const isEditing = ref(false);
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
    is_active: true as boolean,
});

// Methods
const openCreateModal = () => {
    form.reset();
    isEditing.value = false;
    isModalOpen.value = true;
    subredditSearch.value = '';
    subredditResults.value = [];
};

const editKeyword = (keyword: RedditKeyword) => {
    form.id = keyword.id;
    form.keyword = keyword.keyword;
    form.reddit_credential_id = keyword.reddit_credential_id;
    form.subreddits = keyword.subreddits || [];
    form.scan_comments = keyword.scan_comments;
    form.match_whole_word = keyword.match_whole_word;
    form.case_sensitive = keyword.case_sensitive;
    form.is_active = keyword.is_active;

    isEditing.value = true;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    subredditResults.value = [];
    subredditSearch.value = '';
};

const submitForm = () => {
    processing.value = true;

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

const showConfirmDeleteKeywordDialog = (id: number) => {
    showDeleteKeywordDialog.value = true;
    deleteKeywordId.value = id;
};

const deleteKeyword = () => {
    if (deleteKeywordId.value) {
        router.delete(route('mentions.destroy-keyword', deleteKeywordId.value));
        showDeleteKeywordDialog.value = false;
        deleteKeywordId.value = null;
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
</script>

<template>
    <Head title="Mentions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <!-- Main Content Area -->
            <div class="flex-1">
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

                    <Card>
                        <CardHeader>
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
                        <CardContent class="pt-0">
                            <div class="rounded-lg border border-dashed p-6 text-center">
                                <AtSign class="mx-auto h-10 w-10 text-muted-foreground" />
                                <h3 class="mt-4 text-sm font-medium">No mentions yet</h3>
                                <p class="mt-1 mb-4 text-sm text-muted-foreground">We'll show your Reddit mentions here once monitoring begins.</p>
                                <Button>
                                    <Play class="h-4 w-4" />
                                    Start Monitoring
                                </Button>
                                <p class="mt-3 text-xs text-muted-foreground">You can adjust monitoring settings anytime</p>
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
            <DialogContent class="max-h-[90vh] w-full overflow-y-auto md:min-w-3xl lg:min-w-4xl">
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
                        <Plus class="mr-2 h-4 w-4" />
                        Add keyword
                    </Button>
                </div>

                <!-- Keywords List -->
                <div class="scroll max-h-96 space-y-4 overflow-y-auto px-2">
                    <div v-if="props.keywords.length === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">No keywords configured yet. Click "Add Keyword" to get started.</p>
                        <Button @click="openCreateModal" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Keyword
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
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-2 shrink-0 rounded-full" :class="keyword.is_active ? 'bg-green-500' : 'bg-gray-400'" />
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

                                <!-- Details Row -->
                                <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <template v-if="keyword.subreddits.length > 0">
                                            {{ keyword.subreddits.join(', ') }}
                                        </template>
                                        <template v-else> All subreddits </template>
                                        <Check class="h-4 w-4 text-green-600 dark:text-green-400" />
                                    </span>

                                    <Separator orientation="vertical" class="!h-6" />

                                    <span class="flex items-center gap-1">
                                        Comments
                                        <Check v-if="keyword.scan_comments" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <X v-else class="h-4 w-4 text-red-600 dark:text-red-400" />
                                    </span>

                                    <Separator orientation="vertical" class="!h-6" />
                                    <span class="flex items-center gap-1">
                                        Whole word
                                        <Check v-if="keyword.match_whole_word" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <X v-else class="h-4 w-4 text-red-600 dark:text-red-400" />
                                    </span>

                                    <Separator orientation="vertical" class="!h-6" />
                                    <span class="flex items-center gap-1">
                                        Case sensitive
                                        <Check v-if="keyword.case_sensitive" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <X v-else class="h-4 w-4 text-red-600 dark:text-red-400" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Add/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="max-h-[90vh] w-full overflow-y-auto md:min-w-3xl lg:min-w-4xl">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit' : 'Add' }} Keyword</DialogTitle>
                    <DialogDescription> Configure your Reddit keyword monitoring settings. </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-6">
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
                                            'cursor-not-allowed opacity-50': form.subreddits.length >= 3 && !form.subreddits.includes(subreddit.name),
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

                    <!-- Settings -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-2">
                            <Checkbox id="scan_comments" v-model="form.scan_comments" />
                            <Label for="scan_comments" class="text-sm">
                                Scan comments to post (only if the keyword appears in the post title or content)
                            </Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="match_whole_word" v-model="form.match_whole_word" />
                            <Label for="match_whole_word" class="text-sm"> Match exact word only (not parts of other words) </Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="case_sensitive" v-model="form.case_sensitive" />
                            <Label for="case_sensitive" class="text-sm"> Match case exactly (e.g., "Word" ≠ "word") </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeModal"> Cancel </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isEditing ? 'Update' : 'Add' }} Keyword
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Solution 2: Use Teleport to render outside the parent dialog -->
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
    </AppLayout>
</template>
