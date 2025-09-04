<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage<{
    auth: {
        user: any | null;
    };
}>();
</script>

<template>
    <Head title="ChatVia - Modern Chat Application" />

    <div class="min-h-screen bg-background">
        <!-- Hero Section with Chat Preview -->
        <div class="relative overflow-hidden">
            <!-- Navigation -->
            <nav class="relative z-10 border-b bg-background/95 backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center">
                            <h1 class="text-2xl font-bold">ChatVia</h1>
                        </div>
                        <div class="flex items-center gap-4">
                            <a v-if="page.props.auth.user" :href="route('dashboard')" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Dashboard
                            </a>
                <template v-else>
                                <a :href="route('login')" class="rounded-md px-4 py-2 text-sm font-medium text-foreground hover:bg-accent">
                        Log in
                                </a>
                                <a :href="route('register')" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                                    Get Started
                                </a>
                </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Hero Content -->
            <div class="relative">
                <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                    <div class="grid gap-12 lg:grid-cols-2 lg:gap-8">
                        <!-- Left Content -->
                        <div class="flex flex-col justify-center">
                            <h2 class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                                Create your own, <br>
                                <span class="text-primary">Seamlessly</span>
                            </h2>
                            <p class="mt-6 text-lg text-muted-foreground">
                                Experience the power of advanced AI conversations with a beautiful, intuitive interface. Get instant responses, creative assistance, and intelligent insights.
                            </p>
                            <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                                <a :href="route('register')" class="inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-base font-medium text-primary-foreground hover:bg-primary/90">
                                    Start Chatting Free
                                </a>
                                <a href="#features" class="inline-flex items-center justify-center rounded-md border border-border px-6 py-3 text-base font-medium hover:bg-accent">
                                    Learn More
                                </a>
                            </div>
                        </div>

                        <!-- Right: Chat Interface Preview -->
                        <div class="relative">
                            <div class="relative mx-auto max-w-md overflow-hidden rounded-lg border bg-background shadow-2xl">
                                <!-- Chat Header -->
                                <div class="flex items-center gap-3 border-b bg-background/95 p-4">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold">AI Assistant</h3>
                                        <p class="text-xs text-muted-foreground">Always online</p>
                                    </div>
                                    <button class="rounded-md p-2 hover:bg-accent">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Chat Messages -->
                                <div class="h-96 overflow-y-auto bg-background p-4 space-y-4">
                                    <!-- User ChatMessage -->
                                    <div class="flex justify-end">
                                        <div class="max-w-[70%]">
                                            <div class="rounded-lg bg-primary px-4 py-2 text-primary-foreground">
                                                <p class="text-sm">Can you help me write a professional email?</p>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground text-right">2:34 PM</p>
                                        </div>
                                    </div>

                                    <!-- AI ChatMessage -->
                                    <div class="flex justify-start gap-2">
                                        <div class="h-8 w-8 rounded-full bg-muted flex items-center justify-center flex-shrink-0">
                                            <svg class="h-5 w-5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="max-w-[70%]">
                                            <div class="rounded-lg bg-muted px-4 py-2">
                                                <p class="text-sm">Of course! I'd be happy to help you write a professional email. To get started, could you tell me:</p>
                                                <ul class="mt-2 list-disc list-inside text-sm space-y-1">
                                                    <li>Who is the recipient?</li>
                                                    <li>What's the purpose of the email?</li>
                                                    <li>Any key points to include?</li>
                    </ul>
                </div>
                                            <p class="mt-1 text-xs text-muted-foreground">2:34 PM</p>
                                        </div>
                                    </div>

                                    <!-- User ChatMessage -->
                                    <div class="flex justify-end">
                                        <div class="max-w-[70%]">
                                            <div class="rounded-lg bg-primary px-4 py-2 text-primary-foreground">
                                                <p class="text-sm">It's for my manager about requesting time off next month</p>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground text-right">2:35 PM</p>
                                        </div>
                                    </div>

                                    <!-- AI Typing Indicator -->
                                    <div class="flex justify-start gap-2">
                                        <div class="h-8 w-8 rounded-full bg-muted flex items-center justify-center flex-shrink-0">
                                            <svg class="h-5 w-5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                                        </div>
                                        <div class="max-w-[70%]">
                                            <div class="rounded-lg bg-muted px-4 py-3">
                                                <div class="flex space-x-1">
                                                    <div class="h-2 w-2 rounded-full bg-muted-foreground/60 animate-bounce" style="animation-delay: 0ms"></div>
                                                    <div class="h-2 w-2 rounded-full bg-muted-foreground/60 animate-bounce" style="animation-delay: 150ms"></div>
                                                    <div class="h-2 w-2 rounded-full bg-muted-foreground/60 animate-bounce" style="animation-delay: 300ms"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chat Input -->
                                <div class="border-t bg-background p-4">
                                    <div class="flex items-center gap-2">
                                        <button class="rounded-md p-2 hover:bg-accent">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                                        </button>
                                        <input type="text" placeholder="Type a message..." class="flex-1 rounded-md border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                                        <button class="rounded-md bg-primary p-2 text-primary-foreground hover:bg-primary/90">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Decorative Elements -->
                            <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-primary/10 blur-2xl"></div>
                            <div class="absolute -bottom-4 -left-4 h-32 w-32 rounded-full bg-primary/10 blur-2xl"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <section id="features" class="border-t bg-muted/30 py-16 lg:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Everything you need for great conversations</h2>
                    <p class="mt-4 text-lg text-muted-foreground">Powerful features designed to enhance your chat experience</p>
                </div>

                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Feature 1 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Lightning Fast</h3>
                        <p class="mt-2 text-muted-foreground">Get instant responses powered by advanced AI technology</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Secure & Private</h3>
                        <p class="mt-2 text-muted-foreground">Your conversations are encrypted and never shared</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Smart Context</h3>
                        <p class="mt-2 text-muted-foreground">AI remembers context for more meaningful conversations</p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Beautiful Interface</h3>
                        <p class="mt-2 text-muted-foreground">Clean, modern design that's a joy to use</p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Customizable</h3>
                        <p class="mt-2 text-muted-foreground">Tailor the AI to your specific needs and preferences</p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="rounded-lg border bg-card p-6 shadow-sm">
                        <div class="h-12 w-12 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">24/7 Available</h3>
                        <p class="mt-2 text-muted-foreground">Always ready to help, whenever you need it</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-16 lg:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-primary px-8 py-16 text-center sm:px-16">
                    <h2 class="text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl">
                        Ready to start chatting?
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                        Join thousands of users who are already experiencing the future of AI conversations.
                    </p>
                    <div class="mt-8">
                        <a :href="route('register')" class="inline-flex items-center justify-center rounded-md bg-background px-8 py-3 text-base font-medium text-foreground hover:bg-background/90">
                            Get Started Free
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t bg-background">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">© 2024 ChatVia. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* Animation for typing indicator */
@keyframes bounce {
    0%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-0.375rem);
    }
}
</style>
