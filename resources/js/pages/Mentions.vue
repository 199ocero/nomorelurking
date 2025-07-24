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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AtSign, Play, Settings, Unlink } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface RedditCredential {
    id: number;
    reddit_id: string;
    username: string;
    token_expires_at: string | null;
}

interface User {
    id: number;
    name: string;
    email: string;
    reddit_credential: RedditCredential | null;
}

interface Props {
    auth: {
        user: User;
    };
}

const props = defineProps<Props>();
const isLoading = ref(false);
const showDisconnectDialog = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mentions',
        href: '/mentions',
    },
];

const redditCredential = computed(() => props.auth.user.reddit_credential);
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
                    <Card v-if="isTokenExpired" class="mb-4 border-destructive">
                        <CardContent class="pt-6">
                            <div class="flex items-center gap-2">
                                <Badge variant="destructive">Token Expired</Badge>
                                <p class="text-sm text-muted-foreground">Your Reddit access token has expired. Please reconnect your account.</p>
                                <Button @click="connectToReddit" size="sm" variant="outline"> Reconnect </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-none !py-0 shadow-sm">
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
                                                Configure
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>
                                                <div class="flex items-start gap-1 p-1">
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
                                            <DropdownMenuItem>
                                                <AtSign class="mr-2 h-4 w-4" />
                                                Keyword
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click.prevent="showDisconnectDialog = true">
                                                <Unlink class="mr-2 h-4 w-4" />
                                                Disconnect
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
    </AppLayout>
</template>
