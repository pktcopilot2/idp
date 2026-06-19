<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import {
    DxDataGrid,
    DxColumn,
    DxMasterDetail,
    DxPaging,
    DxPager,
    DxStateStoring,
} from 'devextreme-vue/data-grid';
import { dxDataGridBaseProps } from '@/configs/dxDataGridConfig';
import { useDxGridSelection } from '@/composables/useDxGridSelection';
import { useDxGridUrlState } from '@/composables/useDxGridUrlState';
import { createDxRemoteStore } from '@/lib/dxRemoteDataSource';
import DxGridSelectionBar from '@/components/DxGridSelectionBar.vue';
import { Button } from '@/components/ui/button';

// ============================ Props & Options ============================

const props = defineProps<{
    usersDataUrl: string;
    canManageUsers: boolean;
    canUpdateUsers: boolean;
    canDeleteUsers: boolean;
}>();


defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Manage Users',
                href: '/users/manage',
            },
        ],
    },
});

// ============================ Type Definitions ============================

type ManageUserRow = {
    id: number;
    name: string;
    email: string;
    roles: Array<{
        id: number;
        name: string;
    }>;
    email_verified_at: string | null;
    created_at: string;
};

type UserRoleRow = {
    id: number;
    name: string;
    guard_name: string;
    created_at: string | null;
};

type RolePermissionRow = {
    id: number;
    name: string;
};

// ============================ Reactive State & Format Data ============================

const {
    gridRef,
    selectionCount,
    selectedKeys,
    selectedRows,
    onSelectionChanged,
    clearSelection
} = useDxGridSelection<ManageUserRow, number>();
const {
    gridRef: rolesGridRef,
    selectionCount: rolesGridSelectionCount,
    selectedKeys: rolesSelectedKeys,
    selectedRows: rolesSelectedRows,
    onSelectionChanged: onRolesSelectionChanged,
    onRowClick: onRolesRowClick,
    clearSelection: clearRolesSelection,
} = useDxGridSelection<UserRoleRow, number>();
const { stateStoringProps } = useDxGridUrlState('users');
const { stateStoringProps: rolesStateStoringProps } = useDxGridUrlState('roles');

// ============================== Format Data Column ============================

const formatRoles = (roles: ManageUserRow['roles']) => {
    if (!Array.isArray(roles) || roles.length === 0) {
        return '-';
    }

    const names = roles
        .map((role) => role.name)
        .filter((name) => typeof name === 'string' && name.length > 0);

    return names.length > 0 ? names.join(', ') : '-';
};

// ============================ Data Stores ============================

const userStore = createDxRemoteStore<ManageUserRow, number>({
    url: () => props.usersDataUrl,
    key: 'id',
});

const roleStores = new Map<number, ReturnType<typeof createDxRemoteStore<UserRoleRow, number>>>();
const permissionStores = new Map<string, ReturnType<typeof createDxRemoteStore<RolePermissionRow, number>>>();

const createUserRolesStore = (userId: number) => {
    const cached = roleStores.get(userId);
    if (cached) {
        return cached;
    }

    const store = createDxRemoteStore<UserRoleRow, number>({
        url: () => `/users/manage/${userId}/roles/data`,
        key: 'id',
    });

    roleStores.set(userId, store);

    return store;
};

const createRolePermissionsStore = (userId: number, roleId: number) => {
    const storeKey = `${userId}:${roleId}`;
    const cached = permissionStores.get(storeKey);
    if (cached) {
        return cached;
    }

    const store = createDxRemoteStore<RolePermissionRow, number>({
        url: () => `/users/manage/${userId}/roles/${roleId}/permissions/data`,
        key: 'id',
    });

    permissionStores.set(storeKey, store);

    return store;
};

</script>

<template>
    <Head title="Manage Users" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border"
        >
            <div class="mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">Manage Users</h1>
                        <p class="text-sm text-muted-foreground">
                            Daftar user terdaftar beserta status verifikasi email.
                        </p>
                    </div>
                </div>
            </div>

            <DxGridSelectionBar :count="selectionCount" @clear="clearSelection">
                <Button variant="destructive" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Delete', selectedKeys, selectedRows)" v-if="canDeleteUsers">
                    <Trash2 class="size-3.5" />
                    Hapus ({{ selectionCount }})
                </Button>
                <Button variant="outline" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Set Active', selectedKeys, selectedRows)" v-if="canUpdateUsers">
                    Activate ({{ selectionCount }})
                </Button>
                <Button variant="outline" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Set Inactive', selectedKeys, selectedRows)" v-if="canUpdateUsers">
                    Deactivate ({{ selectionCount }})
                </Button>
            </DxGridSelectionBar>

            <DxDataGrid
                ref="gridRef"
                :data-source="userStore"
                :on-selection-changed="onSelectionChanged"
                v-bind="dxDataGridBaseProps"
            >
                <DxStateStoring v-bind="stateStoringProps" />
                <DxColumn data-field="name" caption="Nama" />
                <DxColumn data-field="email" caption="Email" />
                <DxColumn
                    data-field="roles.name"
                    caption="Roles"
                    :allow-header-filtering="false"
                    :allow-sorting="false"
                    :calculate-display-value="(rowData: ManageUserRow) => formatRoles(rowData.roles)"
                />
                <DxColumn
                    data-field="email_verified_at"
                    caption="Email Verified"
                    data-type="date"
                    format="dd MMM yyyy"
                />
                <DxColumn
                    data-field="created_at"
                    caption="Created At"
                    data-type="date"
                    format="dd MMM yyyy"
                />
                <DxColumn
                    caption="Aksi"
                    :allow-sorting="false"
                    :allow-filtering="false"
                    :allow-header-filtering="false"
                    :allow-resizing="false"
                    cell-template="actionCell"
                    width="80"
                    alignment="center"
                />

                <template #actionCell="{ data: row }">
                    <div class="flex items-center justify-center gap-0.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7"
                            v-if="canUpdateUsers"
                            @click.stop="() => console.log('Edit', row.data)"
                        >
                            <Pencil class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 text-destructive hover:text-destructive"
                            v-if="canDeleteUsers"
                            @click.stop="() => console.log('Delete', row.data)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </template>

                <DxMasterDetail
                    :enabled="true"
                    template="rolesNestedGrid"
                />

                <template #rolesNestedGrid="{ data: userDetail }">
                    <div class="p-3">
                        <h3 class="mb-2 text-sm font-semibold text-foreground">Roles</h3>

                        <DxGridSelectionBar :count="rolesGridSelectionCount" @clear="clearRolesSelection">
                            <Button variant="destructive" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Delete', rolesSelectedKeys, rolesSelectedRows)" v-if="canDeleteUsers">
                                <Trash2 class="size-3.5" />
                                Hapus ({{ rolesGridSelectionCount }})
                            </Button>
                            <Button variant="outline" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Set Active', rolesSelectedKeys, rolesSelectedRows)" v-if="canUpdateUsers">
                                Activate ({{ rolesGridSelectionCount }})
                            </Button>
                            <Button variant="outline" size="sm" class="h-7 gap-1.5 px-2.5 text-xs" @click="() => console.log('Set Inactive', rolesSelectedKeys, rolesSelectedRows)" v-if="canUpdateUsers">
                                Deactivate ({{ rolesGridSelectionCount }})
                            </Button>
                        </DxGridSelectionBar>

                        <DxDataGrid
                            ref="rolesGridRef"
                            :data-source="createUserRolesStore(userDetail.data.id)"
                            :on-selection-changed="onRolesSelectionChanged"
                            :on-row-click="onRolesRowClick"
                            v-bind="dxDataGridBaseProps"
                        >
                            <DxStateStoring v-bind="rolesStateStoringProps" />
                            <DxColumn data-field="name" caption="Role" />
                            <DxColumn data-field="guard_name" caption="Guard" width="140" />

                            <DxMasterDetail
                                :enabled="true"
                                template="permissionsNestedGrid"
                            />

                            <template #permissionsNestedGrid="{ data: roleDetail }">
                                <div class="p-3">
                                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Permissions
                                    </h4>

                                    <DxDataGrid
                                        :data-source="createRolePermissionsStore(userDetail.data.id, roleDetail.data.id)"
                                        :selection="{ mode: 'none' }"
                                        v-bind="dxDataGridBaseProps"
                                    >
                                        <DxColumn data-field="name" caption="Permission" />
                                        <DxPaging :page-size="100" />
                                        <DxPager
                                            :visible="false"
                                        />
                                    </DxDataGrid>
                                </div>
                            </template>

                            <DxPaging :page-size="100" />
                            <DxPager
                                :visible="false"
                            />
                        </DxDataGrid>
                    </div>
                </template>
            </DxDataGrid>
        </div>
    </div>
</template>
