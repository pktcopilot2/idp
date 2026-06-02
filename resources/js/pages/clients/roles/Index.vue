<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Shield, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as clientsIndex, show as clientsShow } from '@/routes/clients';
import {
    assignments as assignmentsRoute,
    destroy as destroyRole,
    index as rolesIndex,
    store as storeRole,
    update as updateRole,
} from '@/routes/clients/roles';
import {
    destroy as destroyPermission,
    store as storePermission,
} from '@/routes/clients/permissions';

type OAuthClient = { id: string; name: string };

type ClientRole = {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
};

type ClientPermission = {
    id: number;
    name: string;
};

const props = defineProps<{
    client: OAuthClient;
    roles: ClientRole[];
    all_permissions: ClientPermission[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles & Permissions' },
        ],
    },
});

// ── Permission management ─────────────────────────────────────────────────────

const showAddPermDialog = ref(false);
const addPermForm = useForm({ name: '' });

function submitAddPermission() {
    addPermForm.post(storePermission(props.client).url, {
        preserveScroll: true,
        onSuccess: () => {
            addPermForm.reset();
            showAddPermDialog.value = false;
        },
    });
}

function deletePermission(id: number, name: string) {
    if (!confirm(`Delete permission "${name}"? It will be removed from all roles.`)) return;
    useForm({}).delete(`/clients/${props.client.id}/permissions/${id}`, { preserveScroll: true });
}

// ── Role CRUD ─────────────────────────────────────────────────────────────────

type RoleDialogMode = 'create' | 'edit';

const roleDialog = ref(false);
const roleDialogMode = ref<RoleDialogMode>('create');
const editingRoleId = ref<number | null>(null);

const roleForm = useForm({
    name: '',
    permissions: [] as string[],
});

function openCreateRole() {
    roleDialogMode.value = 'create';
    editingRoleId.value = null;
    roleForm.reset();
    roleDialog.value = true;
}

function openEditRole(role: ClientRole) {
    roleDialogMode.value = 'edit';
    editingRoleId.value = role.id;
    roleForm.name = role.name;
    roleForm.permissions = [...role.permissions];
    roleDialog.value = true;
}

function togglePermission(perm: string) {
    const idx = roleForm.permissions.indexOf(perm);
    if (idx === -1) {
        roleForm.permissions.push(perm);
    } else {
        roleForm.permissions.splice(idx, 1);
    }
}

function submitRole() {
    if (roleDialogMode.value === 'create') {
        roleForm.post(storeRole(props.client).url, {
            preserveScroll: true,
            onSuccess: () => {
                roleForm.reset();
                roleDialog.value = false;
            },
        });
    } else {
        roleForm.put(updateRole({ client: props.client, role: editingRoleId.value! }).url, {
            preserveScroll: true,
            onSuccess: () => {
                roleDialog.value = false;
            },
        });
    }
}

function deleteRole(role: ClientRole) {
    if (!confirm(`Delete role "${role.name}"? It will be removed from ${role.users_count} user(s).`)) return;
    useForm({}).delete(destroyRole({ client: props.client, role: role.id }).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${client.name} — Roles & Permissions`" />

    <div class="space-y-6 p-4">

        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Roles & Permissions</h2>
                <p class="text-sm text-muted-foreground">Manage roles and permissions scoped to {{ client.name }}.</p>
            </div>
            <Button size="sm" :href="assignmentsRoute(client).url" as="a">
                <Shield class="mr-1.5 h-3.5 w-3.5" />
                Assign Users
            </Button>
        </div>

        <!-- Permissions panel -->
        <div class="rounded-lg border">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <h3 class="text-sm font-medium flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                    Permissions
                    <Badge variant="secondary">{{ all_permissions.length }}</Badge>
                </h3>
                <Button variant="outline" size="sm" @click="showAddPermDialog = true">
                    <Plus class="mr-1.5 h-3.5 w-3.5" />
                    Add
                </Button>
            </div>

            <div v-if="all_permissions.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                No permissions yet. Add some permissions to use in roles.
            </div>

            <div v-else class="flex flex-wrap gap-2 p-4">
                <div
                    v-for="perm in all_permissions"
                    :key="perm.id"
                    class="group flex items-center gap-1.5 rounded-full border bg-muted/50 pl-3 pr-1.5 py-1 text-xs font-mono"
                >
                    {{ perm.name }}
                    <button
                        type="button"
                        class="rounded-full p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors opacity-0 group-hover:opacity-100"
                        @click="deletePermission(perm.id, perm.name)"
                        title="Delete permission"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Roles panel -->
        <div class="rounded-lg border">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <h3 class="text-sm font-medium flex items-center gap-2">
                    <Shield class="h-4 w-4 text-muted-foreground" />
                    Roles
                    <Badge variant="secondary">{{ roles.length }}</Badge>
                </h3>
                <Button size="sm" @click="openCreateRole">
                    <Plus class="mr-1.5 h-3.5 w-3.5" />
                    New Role
                </Button>
            </div>

            <div v-if="roles.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                No roles yet. Create a role to assign permissions to users.
            </div>

            <div v-else class="divide-y">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="flex items-start justify-between gap-4 px-4 py-3"
                >
                    <div class="space-y-1.5 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-sm">{{ role.name }}</p>
                            <Badge variant="outline" class="text-xs">
                                {{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}
                            </Badge>
                        </div>
                        <div v-if="role.permissions.length > 0" class="flex flex-wrap gap-1">
                            <Badge
                                v-for="perm in role.permissions"
                                :key="perm"
                                variant="secondary"
                                class="text-xs font-mono"
                            >
                                {{ perm }}
                            </Badge>
                        </div>
                        <p v-else class="text-xs text-muted-foreground italic">No permissions assigned</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEditRole(role)" title="Edit">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive hover:text-destructive" @click="deleteRole(role)" title="Delete">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Permission Dialog -->
    <Dialog v-model:open="showAddPermDialog">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Add Permission</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitAddPermission" class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="perm-name">Permission name</Label>
                    <Input
                        id="perm-name"
                        v-model="addPermForm.name"
                        placeholder="e.g. posts.create"
                        class="font-mono"
                        autofocus
                    />
                    <p v-if="addPermForm.errors.name" class="text-xs text-destructive">{{ addPermForm.errors.name }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showAddPermDialog = false">Cancel</Button>
                    <Button type="submit" :disabled="addPermForm.processing">Add</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Create / Edit Role Dialog -->
    <Dialog v-model:open="roleDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ roleDialogMode === 'create' ? 'New Role' : 'Edit Role' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitRole" class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="role-name">Role name</Label>
                    <Input
                        id="role-name"
                        v-model="roleForm.name"
                        placeholder="e.g. Editor"
                        autofocus
                    />
                    <p v-if="roleForm.errors.name" class="text-xs text-destructive">{{ roleForm.errors.name }}</p>
                </div>

                <div v-if="all_permissions.length > 0" class="space-y-1.5">
                    <Label>Permissions</Label>
                    <div class="rounded-md border p-3 space-y-2 max-h-56 overflow-y-auto">
                        <label
                            v-for="perm in all_permissions"
                            :key="perm.id"
                            class="flex items-center gap-2 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="perm.name"
                                :checked="roleForm.permissions.includes(perm.name)"
                                @change="togglePermission(perm.name)"
                                class="rounded border-gray-300"
                            />
                            <span class="text-sm font-mono">{{ perm.name }}</span>
                        </label>
                    </div>
                </div>
                <p v-else class="text-xs text-muted-foreground">Add permissions first to assign them to roles.</p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="roleDialog = false">Cancel</Button>
                    <Button type="submit" :disabled="roleForm.processing">
                        {{ roleDialogMode === 'create' ? 'Create' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
