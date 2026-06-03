<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { AppWindow, ArrowUpDown, Ban, ChevronDown, ChevronUp, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create as createRoute, edit as editRoute, index, show as showRoute } from '@/routes/clients';

type OAuthClient = {
    id: string;
    name: string;
    secret: string | null;
    provider: string | null;
    redirect_uris: string[] | string;
    grant_types: string[] | string;
    login_uri: string | null;
    revoked: boolean;
    assigned_users_count: number;
    created_at: string;
    updated_at: string;
};

type PaginatedClients = {
    data: OAuthClient[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
};

type Filters = {
    search: string;
    per_page: string;
    sort: string;
    direction: 'asc' | 'desc';
};

const props = defineProps<{
    clients: PaginatedClients;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clients',
                href: index(),
            },
        ],
    },
});

const search = ref(props.filters.search);
const perPage = ref(props.filters.per_page);
const sortBy = ref(props.filters.sort);
const sortDir = ref<'asc' | 'desc'>(props.filters.direction);

function navigate() {
    router.get(
        index(),
        {
            search: search.value || undefined,
            per_page: perPage.value !== '20' ? perPage.value : undefined,
            sort: sortBy.value !== 'name' ? sortBy.value : undefined,
            direction: sortDir.value !== 'asc' ? sortDir.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

const debouncedNavigate = useDebounceFn(navigate, 400);

watch(search, () => debouncedNavigate());
watch(perPage, () => navigate());

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'asc';
    }
    navigate();
}

function parseArray(value: string[] | string): string[] {
    if (Array.isArray(value)) return value;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return value ? [value] : [];
    }
}

function grantTypeLabel(grant: string): string {
    const labels: Record<string, string> = {
        authorization_code: 'Authorization Code',
        client_credentials: 'Client Credentials',
        password: 'Password',
        implicit: 'Implicit',
        refresh_token: 'Refresh Token',
        urn_ietf_params_oauth_grant_type_device_code: 'Device Code',
    };
    return labels[grant] ?? grant;
}

function deleteClient(client: OAuthClient) {
    if (!confirm(`Delete client "${client.name}"? This action cannot be undone.`)) return;

    router.delete(`/clients/${client.id}`, {
        preserveScroll: true,
    });
}

function revokeClient(client: OAuthClient) {
    if (client.revoked) return;
    if (!confirm(`Revoke client "${client.name}"? Users will no longer be able to authenticate with this client.`)) return;

    router.patch(`/clients/${client.id}/revoke`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Clients" />

    <div class="space-y-4 p-4">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-48 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input
                    v-model="search"
                    placeholder="Search by name…"
                    class="pl-9"
                />
            </div>
            <Select v-model="perPage">
                <SelectTrigger class="w-32">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="10">10 / page</SelectItem>
                    <SelectItem value="20">20 / page</SelectItem>
                    <SelectItem value="50">50 / page</SelectItem>
                    <SelectItem value="100">100 / page</SelectItem>
                    <SelectItem value="all">Show all</SelectItem>
                </SelectContent>
            </Select>
            <Button as="a" :href="createRoute().url" class="ml-auto">
                <Plus class="mr-1.5 h-4 w-4" />
                New Client
            </Button>
        </div>

        <div class="rounded-md border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                            <button
                                type="button"
                                class="flex items-center gap-1 hover:text-foreground transition-colors"
                                @click="toggleSort('name')"
                            >
                                Name
                                <ChevronUp v-if="sortBy === 'name' && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDown v-else-if="sortBy === 'name' && sortDir === 'desc'" class="h-3.5 w-3.5" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                        </th>
                        <th class="hidden md:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            Grant Types
                        </th>
                        <th class="hidden lg:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            Redirect URIs
                        </th>
                        <th class="hidden lg:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            Login URL
                        </th>
                        <th class="hidden sm:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            Users
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                            Status
                        </th>
                        <th class="hidden xl:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            <button
                                type="button"
                                class="flex items-center gap-1 hover:text-foreground transition-colors"
                                @click="toggleSort('created_at')"
                            >
                                Created
                                <ChevronUp v-if="sortBy === 'created_at' && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDown v-else-if="sortBy === 'created_at' && sortDir === 'desc'" class="h-3.5 w-3.5" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="client in clients.data"
                        :key="client.id"
                        class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <a :href="showRoute({ client: client.id }).url" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                                <div class="shrink-0 rounded-md bg-muted p-1.5">
                                    <AppWindow class="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div>
                                    <div class="font-medium">{{ client.name }}</div>
                                    <div class="text-xs text-muted-foreground font-mono">
                                        {{ client.id }}
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td class="hidden md:table-cell px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="grant in parseArray(client.grant_types)"
                                    :key="grant"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ grantTypeLabel(grant) }}
                                </Badge>
                                <span v-if="parseArray(client.grant_types).length === 0" class="text-muted-foreground text-xs">—</span>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-4 py-3">
                            <div class="space-y-0.5">
                                <div
                                    v-for="uri in parseArray(client.redirect_uris)"
                                    :key="uri"
                                    class="text-xs text-muted-foreground font-mono truncate max-w-xs"
                                    :title="uri"
                                >
                                    {{ uri }}
                                </div>
                                <span v-if="parseArray(client.redirect_uris).length === 0" class="text-muted-foreground text-xs">—</span>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-4 py-3">
                            <a
                                v-if="client.login_uri"
                                :href="client.login_uri"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-xs text-primary underline-offset-2 hover:underline truncate max-w-xs block font-mono"
                                :title="client.login_uri"
                            >
                                {{ client.login_uri }}
                            </a>
                            <span v-else class="text-muted-foreground text-xs">—</span>
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-muted-foreground">
                            {{ client.assigned_users_count }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                v-if="client.revoked"
                                variant="destructive"
                            >
                                Revoked
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="text-green-600 border-green-600/30 bg-green-50 dark:bg-green-950/20"
                            >
                                Active
                            </Badge>
                        </td>
                        <td class="hidden xl:table-cell px-4 py-3 text-xs text-muted-foreground">
                            {{ new Date(client.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as="a"
                                    :href="editRoute({ client: client.id }).url"
                                    title="Edit"
                                >
                                    <Pencil class="h-4 w-4 text-muted-foreground" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :disabled="client.revoked"
                                    title="Revoke"
                                    @click="revokeClient(client)"
                                >
                                    <Ban class="h-4 w-4 text-muted-foreground" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive"
                                    title="Delete"
                                    @click="deleteClient(client)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="clients.data.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No clients found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-1">
            <p class="text-sm text-muted-foreground text-center sm:text-left">
                Showing {{ clients.from ?? 0 }}–{{ clients.to ?? 0 }} of
                {{ clients.total }} clients
            </p>
            <div v-if="clients.links.length > 3" class="flex flex-wrap justify-center sm:justify-end gap-1">
                <a
                    v-for="link in clients.links"
                    :key="link.label"
                    v-bind="link.url ? { href: link.url } : {}"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                        !link.url ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                    ]"
                    @click.prevent="link.url && router.visit(link.url, { preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
