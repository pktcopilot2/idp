<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: '',
        description: '',
    },
});

defineProps<{
    csrfToken: string;
    post_logout_redirect_uri: string;
    cancel_redirect_uri: string;
}>();
</script>

<template>
    <Head title="Logout Confirmation" />

    <!-- confirmasi apakah anda ingin logout -->
    <div class="space-y-5">
        <div class="flex items-start gap-3 rounded-lg border border-amber-300/40 bg-amber-50 p-4 text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="text-sm font-medium">Are you sure you want to log out?</p>
                <p class="mt-1 text-sm opacity-90">
                    This will revoke all your active sessions and tokens.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button as-child>
                <a :href="cancel_redirect_uri">Cancel</a>
            </Button>
            <form method="POST" :action="`/logout?post_logout_redirect_uri=${post_logout_redirect_uri}`">
                <input type="hidden" name="_token" :value="csrfToken" />
                <Button type="submit" variant="outline">Log Out</Button>
            </form>
        </div>
    </div>
</template>
