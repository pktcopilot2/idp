<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, Pencil, Shield, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editRoute, index } from '@/routes/clients';
import { index as rolesIndex } from '@/routes/clients/roles';

type OAuthClient = {
    id: string;
    name: string;
    grant_types: string[] | string;
    redirect_uris: string[] | string;
    login_uri: string | null;
    revoked: boolean;
    created_at: string;
};

const props = defineProps<{
    client: OAuthClient;
    secret: string | null;
    roles_count: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clients', href: index() },
            { title: 'Client Details' },
        ],
    },
});

const GRANT_LABELS: Record<string, string> = {
    authorization_code: 'Authorization Code',
    client_credentials: 'Client Credentials',
    refresh_token: 'Refresh Token',
    password: 'Password',
    implicit: 'Implicit',
};

function parseArray(value: string[] | string): string[] {
    if (Array.isArray(value)) return value;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return value ? [value] : [];
    }
}

// Copy-to-clipboard with per-field feedback
const copied = ref<string | null>(null);

async function copyToClipboard(text: string, field: string) {
    await navigator.clipboard.writeText(text);
    copied.value = field;
    setTimeout(() => { copied.value = null; }, 2000);
}

function deleteClient() {
    if (!confirm(`Delete client "${props.client.name}"? This action cannot be undone.`)) return;

    router.delete(`/clients/${props.client.id}`);
}
</script>

<template>
    <Head :title="client.name" />

    <div class="max-w-2xl space-y-6 p-4">
        <div>
            <h2 class="text-lg font-semibold">{{ client.name }}</h2>
            <p class="text-sm text-muted-foreground" v-if="secret">Client created successfully.</p>
        </div>

        <!-- Warning banner — only shown when a secret exists -->
        <div
            v-if="secret"
            class="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-50 p-4 dark:bg-amber-950/20"
        >
            <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="space-y-1">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    Save your client secret now
                </p>
                <p class="text-sm text-amber-700 dark:text-amber-400/80">
                    This is the only time the secret will be displayed. It cannot be recovered after you leave this page.
                </p>
            </div>
        </div>

        <!-- Credentials card -->
        <div class="rounded-lg border divide-y">
            <!-- Client ID -->
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Client ID</p>
                    <p class="mt-0.5 font-mono text-sm break-all">{{ client.id }}</p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    @click="copyToClipboard(client.id, 'id')"
                >
                    <Check v-if="copied === 'id'" class="h-4 w-4 text-green-500" />
                    <Copy v-else class="h-4 w-4 text-muted-foreground" />
                </Button>
            </div>

            <!-- Client Secret -->
            <div v-if="secret" class="flex items-center justify-between gap-4 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Client Secret</p>
                    <p class="mt-0.5 font-mono text-sm break-all">{{ secret }}</p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    @click="copyToClipboard(secret, 'secret')"
                >
                    <Check v-if="copied === 'secret'" class="h-4 w-4 text-green-500" />
                    <Copy v-else class="h-4 w-4 text-muted-foreground" />
                </Button>
            </div>
            <div v-else class="px-4 py-3">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Client Secret</p>
                <p class="mt-0.5 text-sm text-muted-foreground italic">No secret — public client</p>
            </div>
        </div>

        <!-- Client details -->
        <div class="rounded-lg border divide-y">
            <div class="px-4 py-3">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Grant Types</p>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <Badge
                        v-for="grant in parseArray(client.grant_types)"
                        :key="grant"
                        variant="secondary"
                    >
                        {{ GRANT_LABELS[grant] ?? grant }}
                    </Badge>
                </div>
            </div>

            <div v-if="parseArray(client.redirect_uris).length > 0" class="px-4 py-3">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Redirect URIs</p>
                <ul class="mt-1.5 space-y-1">
                    <li
                        v-for="uri in parseArray(client.redirect_uris)"
                        :key="uri"
                        class="flex items-center gap-1.5 text-sm font-mono text-muted-foreground"
                    >
                        <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                        {{ uri }}
                    </li>
                </ul>
            </div>

            <div class="px-4 py-3">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Login URL</p>
                <a
                    v-if="client.login_uri"
                    :href="client.login_uri"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-0.5 inline-flex items-center gap-1.5 text-sm text-primary underline-offset-2 hover:underline break-all"
                >
                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                    {{ client.login_uri }}
                </a>
                <p v-else class="mt-0.5 text-sm text-muted-foreground italic">Not set</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <Button as-child>
                <Link :href="editRoute({ client: client.id }).url">
                    <Pencil class="mr-1.5 h-4 w-4" />
                    Edit client
                </Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="rolesIndex(client).url">
                    <Shield class="mr-1.5 h-4 w-4" />
                    Roles & Permissions
                    <Badge v-if="roles_count > 0" variant="secondary" class="ml-1.5 text-xs">{{ roles_count }}</Badge>
                </Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="index()">Back to clients</Link>
            </Button>
            <Button variant="destructive" @click="deleteClient">
                <Trash2 class="mr-1.5 h-4 w-4" />
                Delete client
            </Button>
        </div>
    </div>
</template>
