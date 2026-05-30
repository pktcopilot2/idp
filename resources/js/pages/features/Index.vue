<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Search, ToggleLeft, ToggleRight, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FeatureFlagController from '@/actions/App/Http/Controllers/FeatureFlagController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, purge as purgeRoute } from '@/routes/features';

type User = { id: number; name: string; username: string };

type Override = {
    scope: string;
    user_id: number | null;
    user: User | null;
    enabled: boolean;
};

type FeatureFlag = {
    slug: string;
    label: string;
    description: string;
    global: boolean | null;
    overrides: Override[];
};

const GLOBAL_SCOPE = '__laravel_null';

const props = defineProps<{
    features: FeatureFlag[];
    users: User[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Feature Flags', href: index() }],
    },
});

// ─── Dialog state ────────────────────────────────────────────────────────────

const showDialog = ref(false);
const activeFeature = ref<FeatureFlag | null>(null);
const userSearch = ref('');

const overrideForm = useForm({
    scope: 'global' as 'global' | 'users',
    user_ids: [] as number[],
    enabled: true,
});

const deleteForm = useForm({ scope: '' });
const toggleForm = useForm({ scope: '', user_ids: [] as number[], enabled: false });
const purgeForm = useForm({});
const showPurgeDialog = ref(false);

// ─── Computed ─────────────────────────────────────────────────────────────────

const filteredUsers = computed(() => {
    const q = userSearch.value.trim().toLowerCase();
    if (!q) return props.users;
    return props.users.filter(
        (u) => u.name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q),
    );
});

// ─── Actions ──────────────────────────────────────────────────────────────────

function openDialog(feature: FeatureFlag) {
    activeFeature.value = feature;
    userSearch.value = '';
    overrideForm.reset();
    showDialog.value = true;
}

function toggleUser(userId: number) {
    const idx = overrideForm.user_ids.indexOf(userId);
    if (idx === -1) {
        overrideForm.user_ids.push(userId);
    } else {
        overrideForm.user_ids.splice(idx, 1);
    }
}

function submitOverride() {
    if (!activeFeature.value) return;

    overrideForm.patch(FeatureFlagController.update(activeFeature.value.slug), {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
        },
    });
}

function removeOverride(feature: FeatureFlag, scope: string) {
    deleteForm.scope = scope;
    deleteForm.delete(FeatureFlagController.destroyOverride(feature.slug), {
        preserveScroll: true,
    });
}

function toggleOverride(feature: FeatureFlag, scope: string, currentEnabled: boolean, userId?: number) {
    toggleForm.scope = scope === GLOBAL_SCOPE ? 'global' : 'users';
    toggleForm.user_ids = userId ? [userId] : [];
    toggleForm.enabled = !currentEnabled;
    toggleForm.patch(FeatureFlagController.update(feature.slug), {
        preserveScroll: true,
    });
}

function confirmPurge() {
    purgeForm.delete(purgeRoute(), {
        preserveScroll: true,
        onSuccess: () => {
            showPurgeDialog.value = false;
        },
    });
}
</script>

<template>
    <Head title="Feature Flags" />

    <h1 class="sr-only">Feature Flags</h1>

    <Heading
        class="px-4 pt-4"
        title="Feature Flags"
        description="Control which authentication features are available. Global settings apply to all users; per-user overrides take precedence."
    />

    <div class="flex justify-end px-4">
        <Button variant="destructive" size="sm" @click="showPurgeDialog = true">
            <Trash2 class="mr-1.5 h-4 w-4" />
            Purge All Overrides
        </Button>
    </div>

    <div class="space-y-6 p-4">
        <Card v-for="feature in features" :key="feature.slug">
            <CardHeader class="flex flex-row items-start justify-between gap-4">
                <div class="min-w-0">
                    <CardTitle>{{ feature.label }}</CardTitle>
                    <CardDescription class="mt-1">{{ feature.description }}</CardDescription>
                </div>
                <Button size="sm" variant="outline" class="shrink-0" @click="openDialog(feature)">
                    <Plus class="mr-1 h-4 w-4" />
                    Add Override
                </Button>
            </CardHeader>

            <CardContent>
                <div
                    v-if="feature.global === null && feature.overrides.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    No overrides set — feature resolves from default configuration.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium text-muted-foreground">Scope</th>
                            <th class="pb-2 font-medium text-muted-foreground">Status</th>
                            <th class="pb-2 text-right font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Global row -->
                        <tr v-if="feature.global !== null" class="border-b last:border-0">
                            <td class="py-3 font-medium">Global (all users)</td>
                            <td class="py-3">
                                <Badge :variant="feature.global ? 'default' : 'secondary'">
                                    {{ feature.global ? 'Enabled' : 'Disabled' }}
                                </Badge>
                            </td>
                            <td class="py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :title="feature.global ? 'Deactivate' : 'Activate'"
                                        :disabled="toggleForm.processing"
                                        @click="toggleOverride(feature, GLOBAL_SCOPE, feature.global!)"
                                    >
                                        <ToggleRight v-if="feature.global" class="h-4 w-4 text-primary" />
                                        <ToggleLeft v-else class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="deleteForm.processing"
                                        @click="removeOverride(feature, GLOBAL_SCOPE)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- User overrides -->
                        <tr
                            v-for="override in feature.overrides"
                            :key="override.scope"
                            class="border-b last:border-0"
                        >
                            <td class="py-3">
                                <template v-if="override.user">
                                    <p class="font-medium">{{ override.user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ override.user.username }}</p>
                                </template>
                                <span v-else class="text-muted-foreground">
                                    Unknown ({{ override.scope }})
                                </span>
                            </td>
                            <td class="py-3">
                                <Badge :variant="override.enabled ? 'default' : 'secondary'">
                                    {{ override.enabled ? 'Enabled' : 'Disabled' }}
                                </Badge>
                            </td>
                            <td class="py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :title="override.enabled ? 'Deactivate' : 'Activate'"
                                        :disabled="toggleForm.processing"
                                        @click="toggleOverride(feature, override.scope, override.enabled, override.user_id ?? undefined)"
                                    >
                                        <ToggleRight v-if="override.enabled" class="h-4 w-4 text-primary" />
                                        <ToggleLeft v-else class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="deleteForm.processing"
                                        @click="removeOverride(feature, override.scope)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>

    <!-- Purge Confirmation Dialog -->
    <Dialog v-model:open="showPurgeDialog">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <TriangleAlert class="h-5 w-5 text-destructive" />
                    Purge All Overrides
                </DialogTitle>
                <DialogDescription>
                    This will remove <strong>all stored feature flag overrides</strong> (global and per-user) from the database.
                    Each feature will revert to its default configuration value. This action cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" type="button" @click="showPurgeDialog = false">
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="purgeForm.processing"
                    @click="confirmPurge"
                >
                    Yes, Purge All
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Add Override Dialog -->
    <Dialog v-model:open="showDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add Override</DialogTitle>
                <DialogDescription>
                    Set a feature flag value for a specific scope.
                    <span v-if="activeFeature" class="font-medium text-foreground">
                        {{ activeFeature.label }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5">
                <!-- Scope selector -->
                <div class="space-y-2">
                    <Label>Scope</Label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                v-model="overrideForm.scope"
                                value="global"
                                class="accent-primary"
                            />
                            <span class="text-sm">Global (all users)</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                v-model="overrideForm.scope"
                                value="users"
                                class="accent-primary"
                            />
                            <span class="text-sm">Specific users</span>
                        </label>
                    </div>
                </div>

                <!-- User picker (shown when scope = users) -->
                <div v-if="overrideForm.scope === 'users'" class="space-y-2">
                    <Label>Select Users</Label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="userSearch"
                            placeholder="Search by name or username…"
                            class="pl-8"
                        />
                    </div>

                    <div class="max-h-52 overflow-y-auto rounded-md border">
                        <label
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="flex cursor-pointer select-none items-center gap-3 px-3 py-2 hover:bg-muted"
                        >
                            <Checkbox
                                :model-value="overrideForm.user_ids.includes(user.id)"
                                @update:model-value="toggleUser(user.id)"
                            />
                            <div>
                                <p class="text-sm font-medium leading-none">{{ user.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ user.username }}</p>
                            </div>
                        </label>

                        <div
                            v-if="filteredUsers.length === 0"
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No users found.
                        </div>
                    </div>

                    <p v-if="overrideForm.user_ids.length > 0" class="text-xs text-muted-foreground">
                        {{ overrideForm.user_ids.length }} user(s) selected
                    </p>
                    <InputError :message="overrideForm.errors.user_ids" />
                </div>

                <!-- Enabled / Disabled -->
                <div class="space-y-2">
                    <Label>Status</Label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                :value="true"
                                v-model="overrideForm.enabled"
                                class="accent-primary"
                            />
                            <span class="text-sm">Enabled</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                :value="false"
                                v-model="overrideForm.enabled"
                                class="accent-primary"
                            />
                            <span class="text-sm">Disabled</span>
                        </label>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="showDialog = false">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="
                        overrideForm.processing ||
                        (overrideForm.scope === 'users' && overrideForm.user_ids.length === 0)
                    "
                    @click="submitOverride"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
