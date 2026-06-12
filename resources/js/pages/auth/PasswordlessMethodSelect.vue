<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { KeyRound, Mail, MessageSquare, ShieldCheck } from 'lucide-vue-next';
import type { Component } from 'vue';
import InputError from '@/components/InputError.vue';

defineOptions({
    layout: {
        title: 'Choose verification method',
        description: 'Select how you want to verify your identity.',
    },
});

defineProps<{
    methods: Array<{
        key: string;
        label: string;
    }>;
    csrfToken: string;
    errors?: Record<string, string[] | string>;
}>();

const methodIcon = (key: string): Component => {
    switch (key) {
        case 'totp':
            return ShieldCheck;
        case 'email':
            return Mail;
        case 'whatsapp':
            return MessageSquare;
        default:
            return KeyRound;
    }
};

const methodDescription = (key: string): string => {
    switch (key) {
        case 'totp':
            return 'Use your authenticator app to get a 6-digit code.';
        case 'email':
            return 'We will send a 6-digit code to your email.';
        case 'whatsapp':
            return 'We will send a 6-digit code to your WhatsApp.';
        default:
            return 'Verify your identity.';
    }
};
</script>

<template>
    <Head title="Choose verification method" />

    <div class="space-y-3">
        <form
            v-for="method in methods"
            :key="method.key"
            action="/login/passwordless/method"
            method="POST"
        >
            <input type="hidden" name="_token" :value="csrfToken" />
            <input type="hidden" name="method" :value="method.key" />

            <button
                type="submit"
                class="flex w-full items-center gap-4 rounded-lg border bg-card p-4 text-left transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component :is="methodIcon(method.key)" class="h-5 w-5" />
                </div>
                <div>
                    <p class="font-medium">{{ method.label }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ methodDescription(method.key) }}
                    </p>
                </div>
            </button>
        </form>

        <InputError :message="Array.isArray(errors?.method) ? errors.method[0] : errors?.method" />

        <div class="pt-2 text-center">
            <a
                href="/login"
                class="text-sm text-muted-foreground underline decoration-neutral-300 underline-offset-4 transition-colors hover:decoration-current"
            >
                ← Back to login
            </a>
        </div>
    </div>
</template>
