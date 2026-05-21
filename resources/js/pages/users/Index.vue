<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { MonitorX } from 'lucide-vue-next';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/users';

type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    email_mfa_enabled: boolean;
    is_need_password_reset: boolean;
    active_sessions_count: number;
};

type PaginatedUsers = {
    data: User[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
};

defineProps<{
    users: PaginatedUsers;
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
</script>

<template>
    <Head title="Users" />

    <div class="space-y-4">
        <div class="rounded-md border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th
                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                        >
                            User
                        </th>
                        <th
                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                        >
                            Username
                        </th>
                        <th
                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                        >
                            Status
                        </th>
                        <th
                            class="px-4 py-3 text-left font-medium text-muted-foreground"
                        >
                            Active sessions
                        </th>
                        <th
                            class="px-4 py-3 text-right font-medium text-muted-foreground"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.username }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
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
                            <Badge
                                :variant="
                                    user.active_sessions_count > 0
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{ user.active_sessions_count }}
                                {{
                                    user.active_sessions_count === 1
                                        ? 'session'
                                        : 'sessions'
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Form
                                v-bind="
                                    UserController.destroySessions.form(user)
                                "
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="
                                        processing ||
                                        user.active_sessions_count === 0
                                    "
                                    class="text-destructive hover:text-destructive"
                                >
                                    <MonitorX class="mr-1 h-4 w-4" />
                                    Revoke sessions
                                </Button>
                            </Form>
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

        <div
            v-if="users.links.length > 3"
            class="flex items-center justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ users.from }}–{{ users.to }} of
                {{ users.total }} users
            </p>
            <div class="flex gap-1">
                <Link
                    v-for="link in users.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-muted',
                        !link.url
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer',
                    ]"
                    :preserve-scroll="true"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
