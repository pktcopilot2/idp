<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink, LayoutGrid } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type AssignedClient = {
    id: string;
    name: string;
    url: string | null;
};

const props = defineProps<{
    clients: AssignedClient[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h2 class="text-lg font-semibold">My Applications</h2>
            <p class="text-sm text-muted-foreground">Applications you have access to</p>
        </div>

        <div v-if="props.clients.length > 0" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <a
                v-for="client in props.clients"
                :key="client.id"
                :href="client.url ?? '#'"
                target="_blank"
                rel="noopener noreferrer"
                class="group"
            >
                <Card class="h-full cursor-pointer transition-shadow hover:shadow-md">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <LayoutGrid class="size-5" />
                            </div>
                            <ExternalLink class="size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <CardTitle class="text-base">{{ client.name }}</CardTitle>
                        <p v-if="client.url" class="mt-1 truncate text-xs text-muted-foreground">{{ client.url }}</p>
                    </CardContent>
                </Card>
            </a>
        </div>

        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border"
        >
            <LayoutGrid class="size-10 text-muted-foreground/50" />
            <p class="text-sm text-muted-foreground">No applications assigned to you yet.</p>
        </div>
    </div>
</template>
