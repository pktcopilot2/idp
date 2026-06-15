<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome — SSO Portal" />

    <div class="relative flex min-h-screen flex-col overflow-hidden bg-slate-50 dark:bg-slate-950">

        <!-- ── Background decoration ─────────────────────────────── -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-48 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-violet-400/20 via-indigo-400/15 to-sky-400/10 blur-3xl dark:from-violet-600/15 dark:via-indigo-600/10 dark:to-sky-600/5" />
            <div class="absolute top-1/3 -right-32 h-72 w-72 rounded-full bg-indigo-300/15 blur-2xl dark:bg-indigo-700/10" />
            <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-violet-300/10 blur-2xl dark:bg-violet-700/5" />
        </div>

        <!-- ── Header ─────────────────────────────────────────────── -->
        <header class="relative z-10 flex items-center justify-between px-6 py-5 sm:px-10">
            <!-- Logo -->
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 shadow-sm">
                    <AppLogoIcon class="size-5 text-white" />
                </span>
                <span class="text-sm font-semibold tracking-wide text-slate-800 dark:text-white">SSO Portal</span>
            </div>

            <!-- Nav -->
            <nav class="flex items-center gap-2">
                <template v-if="$page.props.auth?.user">
                    <Link
                        :href="dashboard().url"
                        class="inline-flex h-9 items-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login().url"
                        class="inline-flex h-9 items-center rounded-lg px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Sign in
                    </Link>
                </template>
            </nav>
        </header>

        <!-- ── Hero ───────────────────────────────────────────────── -->
        <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-20 text-center sm:px-10">

            <!-- Badge -->
            <span class="mb-6 inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3.5 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                <span class="size-1.5 rounded-full bg-indigo-500" />
                Identity Provider &middot; OAuth 2.0 / OpenID Connect
            </span>

            <!-- Headline -->
            <h1 class="mx-auto max-w-2xl text-4xl font-extrabold leading-tight tracking-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                One account.<br />
                <span class="bg-gradient-to-r from-violet-600 via-indigo-600 to-sky-500 bg-clip-text text-transparent">
                    Unlimited access.
                </span>
            </h1>

            <!-- Sub -->
            <p class="mx-auto mt-5 max-w-md text-base text-slate-500 dark:text-slate-400 sm:text-lg">
                Secure single sign-on for all your applications.
                One identity, seamless access everywhere.
            </p>

            <!-- CTA -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <Link
                    :href="login().url"
                    class="inline-flex h-11 items-center rounded-xl bg-indigo-600 px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                >
                    Sign in to your account
                </Link>
            </div>

            <!-- Feature cards -->
            <div class="mt-20 grid w-full max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                <div
                    v-for="feature in [
                        {
                            icon: '🔐',
                            title: 'Secure',
                            desc: 'Industry-standard OAuth 2.0 & OpenID Connect with PKCE support.',
                        },
                        {
                            icon: '⚡',
                            title: 'Fast',
                            desc: 'Sub-second authentication with token caching and session reuse.',
                        },
                        {
                            icon: '🔗',
                            title: 'Unified',
                            desc: 'One account across every connected app — no password juggling.',
                        },
                    ]"
                    :key="feature.title"
                    class="rounded-2xl border border-slate-200 bg-white/70 p-6 text-left backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/60"
                >
                    <span class="text-2xl leading-none">{{ feature.icon }}</span>
                    <h3 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ feature.title }}
                    </h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ feature.desc }}
                    </p>
                </div>
            </div>
        </main>

        <!-- ── Footer ─────────────────────────────────────────────── -->
        <footer class="relative z-10 py-6 text-center text-xs text-slate-400 dark:text-slate-600">
            &copy; {{ new Date().getFullYear() }} SSO Portal. All rights reserved.
        </footer>
    </div>
</template>
