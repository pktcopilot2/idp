<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowUpDown, ChevronDown, ChevronUp, Globe, Lock, LockOpen, Monitor, MonitorX, MoreHorizontal, Pencil, Search, Smartphone, UserCheck, UserX, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { edit as editRoute, index, show as showRoute } from '@/routes/users';

type UserToken = {
    name: string; // session ID
    client: { id: number; name: string } | null;
};

type UserSession = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
};

type OAuthClient = {
    id: string;
    name: string;
};

type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    email_mfa_enabled: boolean;
    is_need_password_reset: boolean;
    active: boolean;
    locked_at: string | null;
    failed_login_attempts: number;
    active_sessions_count: number;
    sessions: UserSession[];
    tokens: UserToken[];
    assigned_clients: { id: string }[];
};

type PaginatedUsers = {
    data: User[];
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
    users: PaginatedUsers;
    clients: OAuthClient[];
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: index(),
            },
        ],
    },
});

const search = ref(props.filters.search);
const perPage = ref(props.filters.per_page);
const sortBy = ref(props.filters.sort);
const sortDir = ref<'asc' | 'desc'>(props.filters.direction);
const selectedUser = ref<User | null>(null);

// Assign clients sheet
const assignUser = ref<User | null>(null);
const assignClientIds = ref<string[]>([]);
const assignProcessing = ref(false);

function openAssignClients(user: User) {
    assignUser.value = user;
    assignClientIds.value = user.assigned_clients.map((c) => c.id);
}

function toggleClient(clientId: string) {
    const idx = assignClientIds.value.indexOf(clientId);
    if (idx === -1) {
        assignClientIds.value.push(clientId);
    } else {
        assignClientIds.value.splice(idx, 1);
    }
}

function submitAssignClients() {
    if (!assignUser.value) return;
    assignProcessing.value = true;
    router.put(
        UserController.syncClients.url(assignUser.value),
        { client_ids: assignClientIds.value },
        {
            preserveScroll: true,
            onFinish: () => { assignProcessing.value = false; },
            onSuccess: () => { assignUser.value = null; },
        },
    );
}

function navigate() {
    router.get(index(), {
        search: search.value || undefined,
        per_page: perPage.value !== '20' ? perPage.value : undefined,
        sort: sortBy.value !== 'name' ? sortBy.value : undefined,
        direction: sortDir.value !== 'asc' ? sortDir.value : undefined,
    }, { preserveState: true, replace: true });
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

function openSessions(user: User) {
    selectedUser.value = user;
}

function formatLastActivity(timestamp: number): string {
    const diffMs = Date.now() - timestamp * 1000;
    const diffMins = Math.floor(diffMs / 60_000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${Math.floor(diffHours / 24)}d ago`;
}

function parseBrowser(ua: string | null): string {
    if (!ua) return 'Unknown';
    if (ua.includes('Edg/')) return 'Microsoft Edge';
    if (ua.includes('OPR/') || ua.includes('Opera/')) return 'Opera';
    if (ua.includes('Chrome/')) return 'Chrome';
    if (ua.includes('Firefox/')) return 'Firefox';
    if (ua.includes('Safari/')) return 'Safari';
    if (ua.includes('curl/')) return 'curl';
    return 'Unknown browser';
}

function parseDevice(ua: string | null): 'mobile' | 'desktop' {
    if (!ua) return 'desktop';
    return /Android|iPhone|iPad|iPod|Mobile/i.test(ua) ? 'mobile' : 'desktop';
}

function getSessionClients(session: UserSession): string[] {
    if (!selectedUser.value) return [];
    const names = selectedUser.value.tokens
        .filter((t) => t.name === session.id && t.client)
        .map((t) => t.client!.name);
    return [...new Set(names)];
}
</script>

<template>
    <Head title="Users" />

    <div class="space-y-4 p-4">
        <!-- Toolbar: search + per-page -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-48 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input
                    v-model="search"
                    placeholder="Search by name, email, or username…"
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
        </div>

        <div class="rounded-md border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                            <button type="button" class="flex items-center gap-1 hover:text-foreground transition-colors" @click="toggleSort('name')">
                                User
                                <ChevronUp v-if="sortBy === 'name' && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDown v-else-if="sortBy === 'name' && sortDir === 'desc'" class="h-3.5 w-3.5" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                        </th>
                        <th class="hidden sm:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            <button type="button" class="flex items-center gap-1 hover:text-foreground transition-colors" @click="toggleSort('username')">
                                Username
                                <ChevronUp v-if="sortBy === 'username' && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDown v-else-if="sortBy === 'username' && sortDir === 'desc'" class="h-3.5 w-3.5" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                        </th>
                        <th class="hidden md:table-cell px-4 py-3 text-left font-medium text-muted-foreground">
                            Status
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                            <button type="button" class="flex items-center gap-1 hover:text-foreground transition-colors" @click="toggleSort('active_sessions_count')">
                                Active sessions
                                <ChevronUp v-if="sortBy === 'active_sessions_count' && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDown v-else-if="sortBy === 'active_sessions_count' && sortDir === 'desc'" class="h-3.5 w-3.5" />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <a :href="showRoute(user).url" class="hover:opacity-80 transition-opacity">
                                <div class="font-medium">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                            </a>
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-muted-foreground">
                            {{ user.username }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-if="!user.active"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    Inactive
                                </Badge>
                                <Badge
                                    v-if="user.locked_at"
                                    variant="destructive"
                                >
                                    <Lock class="mr-1 h-3 w-3" />
                                    Locked
                                </Badge>
                                <Badge
                                    v-if="user.email_mfa_enabled"
                                    variant="secondary"
                                >
                                    Email MFA
                                </Badge>
                                <Badge
                                    v-if="user.is_need_password_reset"
                                    variant="destructive"
                                >
                                    Password reset required
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors',
                                    user.active_sessions_count > 0
                                        ? 'bg-primary text-primary-foreground hover:bg-primary/80 cursor-pointer'
                                        : 'border border-border bg-transparent text-foreground cursor-default',
                                ]"
                                :disabled="user.active_sessions_count === 0"
                                @click="
                                    user.active_sessions_count > 0 &&
                                        openSessions(user)
                                "
                            >
                                {{ user.active_sessions_count }}
                                {{
                                    user.active_sessions_count === 1
                                        ? 'session'
                                        : 'sessions'
                                }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                    <DropdownMenuSeparator />

                                    <!-- Edit user -->
                                    <DropdownMenuItem
                                        as="a"
                                        :href="editRoute(user).url"
                                        class="cursor-pointer"
                                    >
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit user
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator />

                                    <!-- Revoke sessions -->
                                    <Form
                                        v-bind="UserController.destroySessions.form(user)"
                                        #default="{ processing }"
                                    >
                                        <DropdownMenuItem
                                            as="button"
                                            type="submit"
                                            :disabled="processing || user.active_sessions_count === 0"
                                            class="w-full text-destructive focus:text-destructive cursor-pointer"
                                        >
                                            <MonitorX class="mr-2 h-4 w-4" />
                                            Revoke all sessions
                                        </DropdownMenuItem>
                                    </Form>

                                    <DropdownMenuSeparator />

                                    <!-- Unlock -->
                                    <Form
                                        v-if="user.locked_at"
                                        v-bind="UserController.unlock.form(user)"
                                        #default="{ processing }"
                                    >
                                        <DropdownMenuItem
                                            as="button"
                                            type="submit"
                                            :disabled="processing"
                                            class="w-full cursor-pointer"
                                        >
                                            <LockOpen class="mr-2 h-4 w-4" />
                                            Unlock account
                                        </DropdownMenuItem>
                                    </Form>

                                    <!-- Toggle active -->
                                    <Form
                                        v-bind="UserController.toggleActive.form(user)"
                                        #default="{ processing }"
                                    >
                                        <DropdownMenuItem
                                            as="button"
                                            type="submit"
                                            :disabled="processing"
                                            class="w-full cursor-pointer"
                                        >
                                            <UserCheck v-if="!user.active" class="mr-2 h-4 w-4" />
                                            <UserX v-else class="mr-2 h-4 w-4" />
                                            {{ user.active ? 'Deactivate' : 'Activate' }}
                                        </DropdownMenuItem>
                                    </Form>

                                    <DropdownMenuSeparator />

                                    <!-- Assign clients -->
                                    <DropdownMenuItem
                                        class="cursor-pointer"
                                        @click="openAssignClients(user)"
                                    >
                                        <Users class="mr-2 h-4 w-4" />
                                        Assign clients
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No users found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-1">
            <p class="text-sm text-muted-foreground text-center sm:text-left">
                Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of
                {{ users.total }} users
            </p>
            <div v-if="users.links.length > 3" class="flex flex-wrap justify-center sm:justify-end gap-1">
                <a
                    v-for="link in users.links"
                    :key="link.label"
                    v-bind="link.url ? { href: link.url } : {}"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                        !link.url
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer',
                    ]"
                    @click.prevent="link.url && router.visit(link.url, { preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>

    <!-- Session detail sheet -->
    <Sheet
        :open="selectedUser !== null"
        @update:open="(v) => !v && (selectedUser = null)"
    >
        <SheetContent class="w-full sm:max-w-lg overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Active sessions</SheetTitle>
                <SheetDescription v-if="selectedUser">
                    {{ selectedUser.name }} &middot;
                    {{ selectedUser.email }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="selectedUser" class="mt-6 space-y-3">
                <div
                    v-if="selectedUser.sessions.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No active sessions.
                </div>

                <div
                    v-for="session in selectedUser.sessions"
                    :key="session.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex-shrink-0 rounded-md bg-muted p-2"
                            >
                                <Smartphone
                                    v-if="
                                        parseDevice(session.user_agent) ===
                                        'mobile'
                                    "
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <Monitor
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </div>

                            <div class="space-y-1 min-w-0">
                                <div class="font-medium text-sm">
                                    {{ parseBrowser(session.user_agent) }}
                                </div>

                                <div
                                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <Globe class="h-3 w-3 flex-shrink-0" />
                                    <span>{{
                                        session.ip_address ?? 'Unknown IP'
                                    }}</span>
                                    <span class="text-border">·</span>
                                    <span>{{
                                        formatLastActivity(session.last_activity)
                                    }}</span>
                                </div>

                                <div
                                    v-if="getSessionClients(session).length > 0"
                                    class="flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="client in getSessionClients(session)"
                                        :key="client"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                    >
                                        <Globe class="h-2.5 w-2.5 flex-shrink-0" />
                                        {{ client }}
                                    </span>
                                </div>

                                <p
                                    class="text-xs text-muted-foreground/70 break-all leading-relaxed"
                                >
                                    {{ session.user_agent ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <Form
                    v-if="selectedUser.sessions.length > 0"
                    v-bind="UserController.destroySessions.form(selectedUser)"
                    @success="selectedUser = null"
                    class="pt-2"
                    #default="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        class="w-full"
                        :disabled="processing"
                    >
                        <MonitorX class="mr-2 h-4 w-4" />
                        Revoke all sessions
                    </Button>
                </Form>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Assign clients sheet -->
    <Sheet
        :open="assignUser !== null"
        @update:open="(v) => !v && (assignUser = null)"
    >
        <SheetContent class="w-full sm:max-w-md flex flex-col">
            <SheetHeader>
                <SheetTitle>Assign clients</SheetTitle>
                <SheetDescription v-if="assignUser">
                    {{ assignUser.name }} &middot; {{ assignUser.email }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="assignUser" class="flex flex-col flex-1 min-h-0 mt-6 gap-4">
                <!-- Client list -->
                <div class="flex-1 overflow-y-auto space-y-1 pr-1">
                    <p v-if="clients.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No clients available.
                    </p>

                    <div
                        v-for="client in clients"
                        :key="client.id"
                        class="flex items-center gap-3 rounded-md px-3 py-2.5 hover:bg-muted/50 transition-colors cursor-pointer select-none"
                        @click="toggleClient(client.id)"
                    >
                        <Checkbox
                            :model-value="assignClientIds.includes(client.id)"
                            @update:model-value="toggleClient(client.id)"
                            @click.stop
                        />
                        <span class="flex-1 text-sm">{{ client.name }}</span>
                    </div>
                </div>

                <Separator />

                <!-- Footer actions -->
                <div class="flex items-center justify-between gap-2 shrink-0">
                    <p class="text-xs text-muted-foreground">
                        {{ assignClientIds.length }} of {{ clients.length }} selected
                    </p>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="assignUser = null">
                            Cancel
                        </Button>
                        <Button :disabled="assignProcessing" @click="submitAssignClients">
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

