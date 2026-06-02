<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as rolesIndex } from '@/routes/clients/roles';
import { sync as syncRoles } from '@/routes/clients/roles/assignments';
import clients from '@/routes/clients';

type OAuthClient = { id: string; name: string };

type ClientRole = {
    id: number;
    name: string;
};

type UserWithRoles = {
    id: number;
    name: string;
    username: string;
    email: string;
    role_ids: number[];
};

const props = defineProps<{
    client: OAuthClient;
    roles: ClientRole[];
    users: UserWithRoles[];
}>();

defineOptions({
    layout: (pageProps: { client: OAuthClient }) => ({
        breadcrumbs: [
            { title: 'Clients', href: clients.index().url },
            { title: pageProps.client.name, href: clients.show(pageProps.client).url },
            { title: 'Roles & Permissions', href: rolesIndex(pageProps.client).url },
            { title: 'Assign Users' },
        ],
    }),
});

const search = ref('');

const filteredUsers = computed(() =>
    props.users.filter(
        (u) =>
            u.name.toLowerCase().includes(search.value.toLowerCase()) ||
            u.username.toLowerCase().includes(search.value.toLowerCase()) ||
            u.email.toLowerCase().includes(search.value.toLowerCase()),
    ),
);

// Per-user role assignment form.
// We keep a separate form instance per user via a map.
const saving = ref<Record<number, boolean>>({});

// Local copy of each user's selected role ids so the UI stays reactive
const localRoles = ref<Record<number, number[]>>(
    Object.fromEntries(props.users.map((u) => [u.id, [...u.role_ids]])),
);

function toggleRole(userId: number, roleId: number) {
    const current = localRoles.value[userId];
    const idx = current.indexOf(roleId);
    if (idx === -1) {
        current.push(roleId);
    } else {
        current.splice(idx, 1);
    }
}

function saveUserRoles(user: UserWithRoles) {
    saving.value[user.id] = true;
    useForm({ role_ids: localRoles.value[user.id] }).put(
        syncRoles({ client: props.client, user: user.id }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value[user.id] = false;
            },
        },
    );
}

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function isDirty(user: UserWithRoles): boolean {
    const local = [...localRoles.value[user.id]].sort((a, b) => a - b);
    const original = [...user.role_ids].sort((a, b) => a - b);
    return JSON.stringify(local) !== JSON.stringify(original);
}
</script>

<template>
    <Head :title="`${client.name} — Assign Roles`" />

    <div class="space-y-5 p-4">

        <!-- Header -->
        <div>
            <h2 class="text-lg font-semibold">Assign Roles to Users</h2>
            <p class="text-sm text-muted-foreground">
                Roles are scoped to <strong>{{ client.name }}</strong>.
                Only users assigned to this client are shown.
            </p>
        </div>

        <!-- No roles warning -->
        <div
            v-if="roles.length === 0"
            class="rounded-lg border border-amber-500/40 bg-amber-50 dark:bg-amber-950/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
        >
            No roles defined for this client yet. Create roles first before assigning them to users.
        </div>

        <!-- No users warning -->
        <div
            v-else-if="users.length === 0"
            class="rounded-lg border px-4 py-10 text-center text-sm text-muted-foreground"
        >
            No users are assigned to this client yet.
        </div>

        <template v-else>
            <!-- Search -->
            <div class="relative max-w-sm">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search users…" class="pl-8" />
            </div>

            <!-- Users table -->
            <div class="rounded-lg border overflow-hidden">
                <!-- Header row -->
                <div class="grid gap-4 bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wide"
                     :style="`grid-template-columns: 1fr repeat(${roles.length}, auto) auto`">
                    <span>User</span>
                    <span v-for="role in roles" :key="role.id" class="text-center">{{ role.name }}</span>
                    <span></span>
                </div>

                <div v-if="filteredUsers.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    No users match your search.
                </div>

                <!-- User rows -->
                <div
                    v-for="user in filteredUsers"
                    :key="user.id"
                    class="grid gap-4 items-center border-t px-4 py-3 hover:bg-muted/20 transition-colors"
                    :style="`grid-template-columns: 1fr repeat(${roles.length}, auto) auto`"
                >
                    <!-- User info -->
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-xs font-bold">
                            {{ getInitials(user.name) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium truncate">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ user.username }}</p>
                        </div>
                    </div>

                    <!-- Role checkboxes -->
                    <div v-for="role in roles" :key="role.id" class="flex justify-center">
                        <button
                            type="button"
                            class="flex h-6 w-6 items-center justify-center rounded border transition-colors"
                            :class="localRoles[user.id]?.includes(role.id)
                                ? 'bg-primary border-primary text-primary-foreground'
                                : 'border-input bg-background hover:border-primary/50'"
                            @click="toggleRole(user.id, role.id)"
                            :title="role.name"
                        >
                            <Check v-if="localRoles[user.id]?.includes(role.id)" class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Save button -->
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="!isDirty(user) || saving[user.id]"
                        :class="isDirty(user) ? 'border-primary text-primary hover:bg-primary/5' : ''"
                        @click="saveUserRoles(user)"
                    >
                        {{ saving[user.id] ? 'Saving…' : 'Save' }}
                    </Button>
                </div>
            </div>

            <!-- Role legend -->
            <div class="flex flex-wrap gap-2 text-xs text-muted-foreground">
                <span>Roles:</span>
                <Badge v-for="role in roles" :key="role.id" variant="secondary" class="text-xs">{{ role.name }}</Badge>
            </div>
        </template>
    </div>
</template>
