<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Globe, Lock, LockOpen, Monitor, MonitorX, Pencil, Smartphone, UserCheck, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editRoute, index } from '@/routes/users';

type UserToken = {
    name: string;
    client: { id: number; name: string } | null;
};

type UserSession = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
};

type AssignedClient = {
    id: string;
    name: string;
    login_uri: string | null;
};

type UserDetail = {
    id: number;
    name: string;
    username: string;
    email: string;
    active: boolean;
    email_mfa_enabled: boolean;
    is_need_password_reset: boolean;
    locked_at: string | null;
    failed_login_attempts: number;
    created_at: string;
    sessions: UserSession[];
    tokens: UserToken[];
    assigned_clients: AssignedClient[];
};

const props = defineProps<{
    user: UserDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'User Detail' },
        ],
    },
});

type Tab = 'sessions' | 'clients';
const activeTab = ref<Tab>('sessions');

const initials = computed(() => {
    const parts = props.user.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const lastActive = computed(() => {
    if (!props.user.sessions.length) return null;
    return Math.max(...props.user.sessions.map((s) => s.last_activity));
});

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
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
    const names = props.user.tokens
        .filter((t) => t.name === session.id && t.client)
        .map((t) => t.client!.name);
    return [...new Set(names)];
}
</script>

<template>
    <Head :title="user.name" />

    <div class="space-y-6 p-4">
        <!-- Profile header card -->
        <div class="rounded-lg border bg-card overflow-hidden">
            <!-- Top bar with actions -->
            <div class="flex items-center justify-end gap-2 border-b px-6 py-3">
                <Button variant="outline" size="sm" as="a" :href="editRoute(user).url">
                    <Pencil class="mr-1.5 h-3.5 w-3.5" />
                    Edit user
                </Button>

                <Form
                    v-if="user.locked_at"
                    v-bind="UserController.unlock.form(user)"
                >
                    <Button type="submit" variant="outline" size="sm">
                        <LockOpen class="mr-1.5 h-3.5 w-3.5" />
                        Unlock
                    </Button>
                </Form>

                <Form v-bind="UserController.toggleActive.form(user)">
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :class="user.active ? 'text-destructive hover:text-destructive' : ''"
                    >
                        <UserX v-if="user.active" class="mr-1.5 h-3.5 w-3.5" />
                        <UserCheck v-else class="mr-1.5 h-3.5 w-3.5" />
                        {{ user.active ? 'Deactivate' : 'Activate' }}
                    </Button>
                </Form>
            </div>

            <!-- Profile body -->
            <div class="flex flex-col sm:flex-row gap-8 p-6">
                <!-- Left: avatar + name -->
                <div class="flex flex-col items-center text-center sm:items-start sm:text-left gap-3 sm:w-48 shrink-0">
                    <Avatar class="size-24 text-2xl font-semibold">
                        <AvatarFallback class="size-24 bg-primary text-primary-foreground text-2xl font-bold">
                            {{ initials }}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <h2 class="text-lg font-semibold leading-tight">{{ user.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ user.username }}</p>
                    </div>
                    <div class="flex flex-wrap gap-1 justify-center sm:justify-start">
                        <Badge v-if="!user.active" variant="outline" class="text-muted-foreground">
                            Inactive
                        </Badge>
                        <Badge v-if="user.locked_at" variant="destructive">
                            <Lock class="mr-1 h-3 w-3" />
                            Locked
                        </Badge>
                        <Badge v-if="user.email_mfa_enabled" variant="secondary">
                            Email MFA
                        </Badge>
                        <Badge v-if="user.is_need_password_reset" variant="destructive">
                            Password reset required
                        </Badge>
                        <Badge
                            v-if="user.active && !user.locked_at"
                            variant="outline"
                            class="text-green-600 border-green-600/30 bg-green-50 dark:bg-green-950/20"
                        >
                            Active
                        </Badge>
                    </div>
                </div>

                <!-- Right: info grid -->
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-5">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Email</p>
                        <p class="mt-0.5 text-sm break-all">{{ user.email }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">User ID</p>
                        <p class="mt-0.5 text-sm font-mono text-muted-foreground">{{ user.id }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm">{{ formatDate(user.created_at) }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Last active</p>
                        <p class="mt-0.5 text-sm">
                            <span v-if="lastActive">{{ formatLastActivity(lastActive) }}</span>
                            <span v-else class="text-muted-foreground italic">No active sessions</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Active sessions</p>
                        <p class="mt-0.5 text-sm">{{ user.sessions.length }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Failed login attempts</p>
                        <p class="mt-0.5 text-sm" :class="user.failed_login_attempts > 0 ? 'text-destructive font-medium' : ''">
                            {{ user.failed_login_attempts }}
                        </p>
                    </div>

                    <div v-if="user.locked_at">
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Locked at</p>
                        <p class="mt-0.5 text-sm text-destructive">{{ formatDate(user.locked_at) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div>
            <!-- Tab nav -->
            <div class="flex border-b gap-0">
                <button
                    v-for="tab in ([
                        { key: 'sessions', label: 'Sessions', count: user.sessions.length },
                        { key: 'clients', label: 'Assigned Clients', count: user.assigned_clients.length },
                    ] as const)"
                    :key="tab.key"
                    type="button"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span
                        class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs"
                        :class="activeTab === tab.key ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                    >{{ tab.count }}</span>
                </button>
            </div>

            <!-- Sessions tab -->
            <div v-if="activeTab === 'sessions'" class="mt-4 space-y-3">
                <div
                    v-if="user.sessions.length === 0"
                    class="rounded-lg border px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No active sessions.
                </div>

                <div
                    v-for="session in user.sessions"
                    :key="session.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0 rounded-md bg-muted p-2">
                                <Smartphone
                                    v-if="parseDevice(session.user_agent) === 'mobile'"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <Monitor v-else class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="space-y-1 min-w-0">
                                <p class="font-medium text-sm">{{ parseBrowser(session.user_agent) }}</p>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Globe class="h-3 w-3 shrink-0" />
                                    <span>{{ session.ip_address ?? 'Unknown IP' }}</span>
                                    <span class="text-border">·</span>
                                    <span>{{ formatLastActivity(session.last_activity) }}</span>
                                </div>
                                <div v-if="getSessionClients(session).length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="client in getSessionClients(session)"
                                        :key="client"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                    >
                                        <Globe class="h-2.5 w-2.5 shrink-0" />
                                        {{ client }}
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground/70 break-all leading-relaxed">
                                    {{ session.user_agent ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <Form
                    v-if="user.sessions.length > 0"
                    v-bind="UserController.destroySessions.form(user)"
                    class="pt-2"
                >
                    <Button type="submit" variant="destructive" size="sm">
                        <MonitorX class="mr-2 h-4 w-4" />
                        Revoke all sessions
                    </Button>
                </Form>
            </div>

            <!-- Clients tab -->
            <div v-if="activeTab === 'clients'" class="mt-4">
                <div
                    v-if="user.assigned_clients.length === 0"
                    class="rounded-lg border px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No clients assigned to this user.
                </div>

                <div v-else class="rounded-md border divide-y">
                    <div
                        v-for="client in user.assigned_clients"
                        :key="client.id"
                        class="flex items-center justify-between px-4 py-3 gap-4"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-sm">{{ client.name }}</p>
                            <p class="text-xs font-mono text-muted-foreground">{{ client.id }}</p>
                        </div>
                        <a
                            v-if="client.login_uri"
                            :href="client.login_uri"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs text-primary underline-offset-2 hover:underline shrink-0"
                        >
                            Open
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
