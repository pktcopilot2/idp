<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserX } from 'lucide-vue-next';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import impersonate from '@/routes/impersonate';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const impersonator = computed(() => auth.value.impersonator);
const impersonatedUser = computed(() => auth.value.user);

function stopImpersonating() {
    router.post(impersonate.stop.url());
}
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <!-- Impersonation Banner -->
            <div
                v-if="impersonator"
                class="flex items-center justify-between gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
            >
                <span>
                    Acting as <strong>{{ impersonatedUser?.name }}</strong>
                    ({{ impersonatedUser?.email }}).
                    Your original account: <strong>{{ impersonator.name }}</strong>.
                </span>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="stopImpersonating"
                >
                    <UserX class="mr-1.5 h-3.5 w-3.5" />
                    Stop impersonating
                </Button>
            </div>

            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
