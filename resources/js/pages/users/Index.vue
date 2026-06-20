<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    LockOpen,
    Monitor,
    MonitorX,
    MoreHorizontal,
    Pencil,
    Smartphone,
    Trash2,
    UserCheck,
    UserX,
    UserRoundPen,
    Users,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import {
    DxDataGrid,
    DxColumn,
    DxMasterDetail,
    DxPaging,
    DxPager,
    DxStateStoring,
    DxSearchPanel,
    DxHeaderFilter,
    DxFilterRow,
    DxLoadPanel,
} from 'devextreme-vue/data-grid';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dxDataGridBaseProps } from '@/configs/dxDataGridConfig';
import { useDxGridSelection } from '@/composables/useDxGridSelection';
import { useDxGridUrlState } from '@/composables/useDxGridUrlState';
import { createDxRemoteStore } from '@/lib/dxRemoteDataSource';
import DxGridSelectionBar from '@/components/DxGridSelectionBar.vue';
import { edit as editRoute, index, show as showRoute } from '@/routes/users';

// ============================ Types ============================

type UserRow = {
    id: number;
    name: string;
    email: string;
    username: string;
    active: boolean;
    locked_at: string | null;
    created_at: string;
    email_mfa_enabled: boolean;
    whatsapp_mfa_enabled: boolean;
    is_need_password_reset: boolean;
    failed_login_attempts: number;
    active_sessions_count: number;
    assigned_clients: Array<{ id: string }>;
};

type SessionRow = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    clients: string[];
};

type OAuthClient = {
    id: string;
    name: string;
};

// ============================ Props ============================

const props = defineProps<{
    usersDataUrl: string;
    clients: OAuthClient[];
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

const page = usePage();
const currentUserId = (page.props.auth as any)?.user?.id as number | undefined;

// ============================ Data stores ============================

const userStore = createDxRemoteStore<UserRow, number>({
    url: () => props.usersDataUrl,
    key: 'id',
});

const sessionStores = new Map<number, ReturnType<typeof createDxRemoteStore<SessionRow, string>>>();
const sessionRefreshKeys = reactive<Record<number, number>>({});

const createSessionStore = (userId: number) => {
    const cached = sessionStores.get(userId);
    if (cached) return cached;

    const store = createDxRemoteStore<SessionRow, string>({
        url: () => `/users/${userId}/sessions/data`,
        key: 'id',
    });
    sessionStores.set(userId, store);

    return store;
};

function refreshSessionGrid(userId: number) {
    sessionStores.delete(userId);
    sessionRefreshKeys[userId] = (sessionRefreshKeys[userId] ?? 0) + 1;
}

// ============================ Grid selection & refresh ============================

const {
    gridRef,
    selectionCount,
    selectedKeys,
    selectedRows,
    onSelectionChanged,
    clearSelection,
} = useDxGridSelection<UserRow, number>();

// ============================ State ============================

const { stateStoringProps } = useDxGridUrlState('users');

function refreshGrid() {
    gridRef.value?.instance?.refresh();
}

// Assign clients
const assignUser = ref<UserRow | null>(null);
const assignClientIds = ref<string[]>([]);
const assignProcessing = ref(false);

// ============================ Helpers ============================

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

// ============================ Assign Clients ============================

function openAssignClients(user: UserRow) {
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
            onFinish: () => {
                assignProcessing.value = false;
            },
            onSuccess: () => {
                assignUser.value = null;
                refreshGrid();
            },
        },
    );
}

// ============================ MFA badges ============================

function mfaBadges(user: UserRow): Array<{ label: string }> {
    const badges: Array<{ label: string }> = [];

    if (user.email_mfa_enabled) badges.push({ label: 'Email' });
    if (user.whatsapp_mfa_enabled) badges.push({ label: 'WhatsApp' });

    return badges;
}
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
            <div class="mb-4">
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="text-sm text-muted-foreground">
                    Manage users, sessions, and client assignments.
                </p>
            </div>

            <DxGridSelectionBar :count="selectionCount" @clear="clearSelection">
                <Button variant="destructive" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="console.log('Revoke sessions', selectedKeys)">
                    <MonitorX class="size-3.5" />
                    Revoke sessions ({{ selectionCount }})
                </Button>
            </DxGridSelectionBar>

            <DxDataGrid
                ref="gridRef"
                :data-source="userStore"
                :on-selection-changed="onSelectionChanged"
                v-bind="dxDataGridBaseProps"
            >
                <DxStateStoring v-bind="stateStoringProps" />

                <!-- Name -->
                <DxColumn
                    data-field="name"
                    caption="Name"
                    :sort-index="0"
                    sort-order="asc"
                    cell-template="nameCell"
                />
                <template #nameCell="{ data: row }">
                    <a
                        :href="showRoute({ id: row.data.id }).url"
                        class="text-primary hover:underline"
                    >
                        <div class="font-medium">{{ row.data.name }}</div>
                    </a>
                    <div class="text-xs text-muted-foreground">{{ row.data.email }}</div>
                </template>

                <!-- Username (hidden by default, available via column chooser) -->
                <DxColumn data-field="username" caption="Username" :visible="false" />

                <!-- MFA -->
                <DxColumn
                    caption="MFA"
                    :allow-filtering="false"
                    :allow-sorting="false"
                    cell-template="mfaCell"
                />
                <template #mfaCell="{ data: row }">
                    <div class="flex flex-wrap gap-1 py-0.5">
                        <Badge
                            v-for="b in mfaBadges(row.data)"
                            :key="b.label"
                            variant="secondary"
                        >
                            {{ b.label }}
                        </Badge>
                        <span
                            v-if="mfaBadges(row.data).length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            —
                        </span>
                    </div>
                </template>

                <!-- Active -->
                <DxColumn
                    data-field="active"
                    caption="Active"
                    width="150"
                    alignment="center"
                    cell-template="activeCell"
                />
                <template #activeCell="{ data: row }">
                    <span
                        :class="[
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                            row.data.active
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                        ]"
                    >
                        {{ row.data.active ? 'Yes' : 'No' }}
                    </span>
                </template>

                <!-- Locked At -->
                <DxColumn
                    data-field="locked_at"
                    caption="Locked At"
                    data-type="date"
                    format="dd MMM yyyy HH:mm"
                    width="160"
                />

                <!-- Created At -->
                <DxColumn
                    data-field="created_at"
                    caption="Created At"
                    data-type="date"
                    format="dd MMM yyyy"
                    width="140"
                />

                <!-- Active Sessions -->
                <DxColumn
                    data-field="active_sessions_count"
                    caption="Sessions"
                    alignment="center"
                    cell-template="sessionsCell"
                />
                <template #sessionsCell="{ data: row }">
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors',
                            row.data.active_sessions_count > 0
                                ? 'bg-primary text-primary-foreground hover:bg-primary/80 cursor-pointer'
                                : 'border border-border bg-transparent text-foreground cursor-default',
                        ]"
                        :disabled="row.data.active_sessions_count === 0"
                        @click="
                            row.data.active_sessions_count > 0 &&
                                row.component.expandRow(row.key)
                        "
                    >
                        {{ row.data.active_sessions_count }}
                        {{ row.data.active_sessions_count === 1 ? 'session' : 'sessions' }}
                    </button>
                </template>

                <!-- Actions -->
                <DxColumn
                    caption=""
                    :allow-filtering="false"
                    :allow-sorting="false"
                    alignment="center"
                    cell-template="actionCell"
                />
                <template #actionCell="{ data: row }">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <!-- Edit -->
                            <DropdownMenuItem as="a" :href="editRoute({ id: row.data.id }).url" class="cursor-pointer">
                                <Pencil class="mr-2 h-4 w-4" />
                                Edit user
                            </DropdownMenuItem>

                            <DropdownMenuSeparator />

                            <!-- Revoke sessions -->
                            <Form
                                v-bind="UserController.destroySessions.form({ id: row.data.id })"
                                @success="refreshGrid()"
                                #default="{ processing }"
                            >
                                <DropdownMenuItem
                                    as="button"
                                    type="submit"
                                    :disabled="processing || row.data.active_sessions_count === 0"
                                    class="w-full text-destructive focus:text-destructive cursor-pointer"
                                >
                                    <MonitorX class="mr-2 h-4 w-4" />
                                    Revoke all sessions
                                </DropdownMenuItem>
                            </Form>

                            <DropdownMenuSeparator />

                            <!-- Unlock -->
                            <Form
                                v-if="row.data.locked_at"
                                v-bind="UserController.unlock.form({ id: row.data.id })"
                                @success="refreshGrid()"
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
                                v-bind="UserController.toggleActive.form({ id: row.data.id })"
                                @success="refreshGrid()"
                                #default="{ processing }"
                            >
                                <DropdownMenuItem
                                    as="button"
                                    type="submit"
                                    :disabled="processing"
                                    class="w-full cursor-pointer"
                                >
                                    <UserCheck v-if="!row.data.active" class="mr-2 h-4 w-4" />
                                    <UserX v-else class="mr-2 h-4 w-4" />
                                    {{ row.data.active ? 'Deactivate' : 'Activate' }}
                                </DropdownMenuItem>
                            </Form>

                            <DropdownMenuSeparator />

                            <!-- Assign clients -->
                            <DropdownMenuItem class="cursor-pointer" @click="openAssignClients(row.data)">
                                <Users class="mr-2 h-4 w-4" />
                                Assign clients
                            </DropdownMenuItem>

                            <!-- Act as User (skip self) -->
                            <template v-if="currentUserId !== row.data.id">
                                <DropdownMenuSeparator />
                                <Form
                                    v-bind="UserController.impersonate.form({ id: row.data.id })"
                                    @success="refreshGrid()"
                                    #default="{ processing }"
                                >
                                    <DropdownMenuItem
                                        as="button"
                                        type="submit"
                                        :disabled="processing"
                                        class="w-full cursor-pointer"
                                    >
                                        <UserRoundPen class="mr-2 h-4 w-4" />
                                        Act as user
                                    </DropdownMenuItem>
                                </Form>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <!-- Sessions MasterDetail -->
                <DxMasterDetail :enabled="true" template="sessionsDetail" />
                <template #sessionsDetail="{ data: userDetail }">
                    <div class="p-3">
                        <h3 class="mb-2 text-sm font-semibold text-foreground">Active Sessions</h3>

                        <DxDataGrid
                            :key="`sessions-${userDetail.data.id}-${sessionRefreshKeys[userDetail.data.id] ?? 0}`"
                            :data-source="createSessionStore(userDetail.data.id)"
                            v-bind="dxDataGridBaseProps"
                        >
                            <DxLoadPanel :enabled="true" :show-indicator="true" :show-pane="true" text="Loading sessions..." />

                            <DxColumn
                                data-field="user_agent"
                                caption="Browser"
                                cell-template="browserCell"
                            />
                            <template #browserCell="{ data: sessionRow }">
                                <div class="flex items-start gap-2.5">
                                    <div class="mt-0.5 shrink-0 rounded-md bg-muted p-1.5">
                                        <Smartphone
                                            v-if="parseDevice(sessionRow.data.user_agent) === 'mobile'"
                                            class="h-3.5 w-3.5 text-muted-foreground"
                                        />
                                        <Monitor v-else class="h-3.5 w-3.5 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <div class="font-medium text-sm">
                                            {{ parseBrowser(sessionRow.data.user_agent) }}
                                        </div>
                                        <div class="text-xs text-muted-foreground break-all">
                                            {{ sessionRow.data.user_agent ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <DxColumn data-field="ip_address" caption="IP" width="140" />

                            <DxColumn
                                data-field="last_activity"
                                caption="Last active"
                                width="120"
                                cell-template="lastActivityCell"
                            />
                            <template #lastActivityCell="{ data: sessionRow }">
                                {{ formatLastActivity(sessionRow.data.last_activity) }}
                            </template>

                            <DxColumn
                                data-field="clients"
                                caption="Clients"
                                width="200"
                                cell-template="clientsCell"
                            />
                            <template #clientsCell="{ data: sessionRow }">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="client in sessionRow.data.clients"
                                        :key="client"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ client }}
                                    </span>
                                    <span
                                        v-if="sessionRow.data.clients.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </div>
                            </template>

                            <DxColumn
                                caption=""
                                :allow-filtering="false"
                                :allow-sorting="false"
                                :width="60"
                                alignment="center"
                                cell-template="revokeSessionCell"
                            />
                            <template #revokeSessionCell="{ data: sessionRow }">
                                <Form
                                    v-bind="UserController.destroySession.form({ user: userDetail.data.id, session: sessionRow.data.id })"
                                    @success="() => { refreshGrid(); refreshSessionGrid(userDetail.data.id); }"
                                    #default="{ processing }"
                                >
                                    <button
                                        type="submit"
                                        :disabled="processing"
                                        class="inline-flex items-center justify-center rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors disabled:opacity-50"
                                        title="Revoke session"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </Form>
                            </template>

                            <DxPaging :page-size="100" />
                            <DxPager :visible="false" />
                        </DxDataGrid>
                    </div>
                </template>

                <DxPaging :page-size="20" />
                <DxPager
                    :visible="true"
                    :allowed-page-sizes="[10, 20, 50, 'all']"
                    :show-page-size-selector="true"
                    :show-navigation-buttons="true"
                    :show-info="true"
                />
            </DxDataGrid>
        </div>
    </div>

    <!-- Assign clients sheet -->
    <Sheet :open="assignUser !== null" @update:open="(v) => !v && (assignUser = null)">
        <SheetContent class="w-full sm:max-w-md flex flex-col">
            <SheetHeader>
                <SheetTitle>Assign clients</SheetTitle>
                <SheetDescription v-if="assignUser">
                    {{ assignUser.name }} &middot; {{ assignUser.email }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="assignUser" class="flex flex-col flex-1 min-h-0 mt-6 gap-4">
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

                <div class="flex items-center justify-between gap-2 shrink-0">
                    <p class="text-xs text-muted-foreground">
                        {{ assignClientIds.length }} of {{ clients.length }} selected
                    </p>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="assignUser = null"> Cancel </Button>
                        <Button :disabled="assignProcessing" @click="submitAssignClients"> Save </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
