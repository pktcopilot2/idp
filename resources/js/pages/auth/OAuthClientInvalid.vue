<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: 'Client is unavailable',
        description: 'The application you are trying to access cannot be authenticated.',
    },
});

defineProps<{
    error: string;
    message: string;
    client_id: string;
}>();
</script>

<template>
    <Head title="Client Unavailable" />

    <div class="space-y-5">
        <div class="flex items-start gap-3 rounded-lg border border-amber-300/40 bg-amber-50 p-4 text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="text-sm font-medium">Access to this application is blocked</p>
                <p class="mt-1 text-sm opacity-90">
                    {{ message }}
                </p>
            </div>
        </div>

        <div class="rounded-lg border bg-card p-4 text-sm space-y-2">
            <p class="font-medium">Error detail</p>
            <p class="text-muted-foreground">
                OAuth error: <span class="font-mono">{{ error }}</span>
            </p>
            <p v-if="client_id" class="text-muted-foreground break-all">
                Client ID: <span class="font-mono">{{ client_id }}</span>
            </p>
            <p class="text-muted-foreground">
                Contact your administrator if you need this client re-enabled.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Button as-child>
                <Link href="/">Back to Home</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link href="/login">Go to Login</Link>
            </Button>
        </div>
    </div>
</template>
