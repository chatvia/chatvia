<script setup lang="ts">
// OAuth sign-in buttons (Google and Slack always; Microsoft and GitHub on
// request). Each is a full-page redirect so the provider can take over.
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

interface Props {
    /** Optional override for the Google OAuth-start URL. Defaults to `auth.google`. */
    googleUrl?: string;
    /** Optional override for the Slack OAuth-start URL. Defaults to `auth.slack`. */
    slackUrl?: string;
    /** Optional override for the Microsoft OAuth-start URL. Defaults to `auth.microsoft`. */
    microsoftUrl?: string;
    /** Optional override for the GitHub OAuth-start URL. Defaults to `auth.github`. */
    githubUrl?: string;
    /** Hide Microsoft (only Google + Slack rendered, matching the login page). */
    showMicrosoft?: boolean;
    /** Show GitHub (off by default, like Microsoft). */
    showGithub?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    googleUrl: '',
    slackUrl: '',
    microsoftUrl: '',
    githubUrl: '',
    showMicrosoft: false,
    showGithub: false,
});

const googleHref = computed(() => props.googleUrl || route('auth.google'));
const slackHref = computed(() => props.slackUrl || route('auth.slack'));
const microsoftHref = computed(() => props.microsoftUrl || route('auth.microsoft'));
const githubHref = computed(() => props.githubUrl || route('auth.github'));

const go = (url: string) => {
    window.location.href = url;
};
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <Button type="button" variant="outline" class="h-12 w-full text-base font-medium" @click="go(googleHref)">
            <svg class="me-2 h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4"
                />
                <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853"
                />
                <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                    fill="#FBBC05"
                />
                <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    fill="#EA4335"
                />
            </svg>
            Google
        </Button>

        <Button type="button" variant="outline" class="h-12 w-full text-base font-medium" @click="go(slackHref)">
            <svg class="me-2 h-5 w-5" viewBox="0 0 127 127" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M27.2 80c0 7.3-5.9 13.2-13.2 13.2C6.7 93.2.8 87.3.8 80c0-7.3 5.9-13.2 13.2-13.2h13.2V80zm6.6 0c0-7.3 5.9-13.2 13.2-13.2 7.3 0 13.2 5.9 13.2 13.2v33c0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V80z"
                    fill="#E01E5A"
                />
                <path
                    d="M47 27c-7.3 0-13.2-5.9-13.2-13.2C33.8 6.5 39.7.6 47 .6c7.3 0 13.2 5.9 13.2 13.2V27H47zm0 6.7c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H13.9C6.6 60.1.7 54.2.7 46.9c0-7.3 5.9-13.2 13.2-13.2H47z"
                    fill="#36C5F0"
                />
                <path
                    d="M99.9 46.9c0-7.3 5.9-13.2 13.2-13.2 7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H99.9V46.9zm-6.6 0c0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V13.8C66.9 6.5 72.8.6 80.1.6c7.3 0 13.2 5.9 13.2 13.2v33.1z"
                    fill="#2EB67D"
                />
                <path
                    d="M80.1 99.8c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V99.8h13.2zm0-6.6c-7.3 0-13.2-5.9-13.2-13.2 0-7.3 5.9-13.2 13.2-13.2h33.1c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H80.1z"
                    fill="#ECB22E"
                />
            </svg>
            Slack
        </Button>

        <Button v-if="showMicrosoft" type="button" variant="outline" class="h-12 w-full text-base font-medium" @click="go(microsoftHref)">
            <svg class="me-2 h-5 w-5" viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg">
                <path fill="#f25022" d="M1 1h10v10H1z" />
                <path fill="#7fba00" d="M12 1h10v10H12z" />
                <path fill="#00a4ef" d="M1 12h10v10H1z" />
                <path fill="#ffb900" d="M12 12h10v10H12z" />
            </svg>
            Microsoft
        </Button>

        <Button v-if="showGithub" type="button" variant="outline" class="h-12 w-full text-base font-medium" @click="go(githubHref)">
            <svg class="me-2 h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.580.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"
                />
            </svg>
            GitHub
        </Button>
    </div>
</template>
