<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { create as createRoute, index } from '@/routes/clients';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clients', href: index() },
            { title: 'New Client', href: createRoute() },
        ],
    },
});

const GRANT_TYPES = [
    { value: 'authorization_code', label: 'Authorization Code', description: 'Standard OAuth 2.0 flow for web & mobile apps' },
    { value: 'client_credentials', label: 'Client Credentials', description: 'Machine-to-machine, no user involved' },
    { value: 'refresh_token', label: 'Refresh Token', description: 'Allow clients to refresh access tokens' },
    { value: 'password', label: 'Password', description: 'Direct username/password exchange (legacy)' },
    { value: 'implicit', label: 'Implicit', description: 'Legacy browser-only flow (deprecated)' },
] as const;

type GrantType = (typeof GRANT_TYPES)[number]['value'];

const form = useForm({
    name: '',
    confidential: true,
    pkce_enabled: false,
    grant_types: ['authorization_code', 'refresh_token'] as GrantType[],
    redirect_uris: [''] as string[],
    login_uri: '',
});

const needsRedirectUri = computed(() =>
    form.grant_types.includes('authorization_code') ||
    form.grant_types.includes('implicit'),
);

function toggleGrantType(value: GrantType) {
    const idx = form.grant_types.indexOf(value);
    if (idx === -1) {
        form.grant_types.push(value);
    } else {
        form.grant_types.splice(idx, 1);
    }
}

function addRedirectUri() {
    form.redirect_uris.push('');
}

function removeRedirectUri(index: number) {
    form.redirect_uris.splice(index, 1);
}

function submit() {
    const payload = {
        name: form.name,
        confidential: form.confidential,
        pkce_enabled: form.pkce_enabled,
        grant_types: form.grant_types,
        redirect_uris: needsRedirectUri.value
            ? form.redirect_uris.filter((u) => u.trim() !== '')
            : [],
        login_uri: form.login_uri.trim(),
    };

    form.transform(() => payload).post(ClientController.store.url());
}
</script>

<template>
    <Head title="New Client" />

    <div class="max-w-2xl space-y-8 p-4">
        <div>
            <h2 class="text-lg font-semibold">New Client</h2>
            <p class="text-sm text-muted-foreground">
                Register a new OAuth 2.0 client application.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Name -->
            <div class="grid gap-2">
                <Label for="name">Application name <span class="text-destructive">*</span></Label>
                <Input
                    id="name"
                    v-model="form.name"
                    placeholder="My Application"
                    autocomplete="off"
                    :class="{ 'border-destructive': form.errors.name }"
                />
                <InputError :message="form.errors.name" />
            </div>

            <Separator />

            <!-- Confidential -->
            <div class="space-y-2">
                <Label class="text-base font-medium">Client type</Label>
                <div class="flex items-start gap-3 rounded-lg border p-4">
                    <Checkbox
                        id="confidential"
                        :model-value="form.confidential"
                        @update:model-value="form.confidential = !!$event"
                        class="mt-0.5"
                    />
                    <div class="space-y-1">
                        <Label for="confidential" class="font-medium cursor-pointer">
                            Confidential client
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            Uses a client secret. Keep this enabled for server-side apps that can store secrets securely.
                        </p>
                    </div>
                </div>
            </div>

            <Separator />

            <!-- PKCE -->
            <div class="space-y-2">
                <Label class="text-base font-medium">PKCE</Label>
                <div class="flex items-start gap-3 rounded-lg border p-4">
                    <Checkbox
                        id="pkce_enabled"
                        :model-value="form.pkce_enabled"
                        @update:model-value="form.pkce_enabled = !!$event"
                        class="mt-0.5"
                    />
                    <div class="space-y-1">
                        <Label for="pkce_enabled" class="font-medium cursor-pointer">
                            Enable PKCE
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            Recommended for modern Authorization Code flow. For public clients, this is required.
                        </p>
                    </div>
                </div>
                <InputError :message="form.errors.pkce_enabled" />
            </div>

            <Separator />

            <!-- Grant types -->
            <div class="space-y-3">
                <div>
                    <Label class="text-base font-medium">Grant types <span class="text-destructive">*</span></Label>
                    <p class="text-sm text-muted-foreground">Select one or more OAuth 2.0 grant types.</p>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="grant in GRANT_TYPES"
                        :key="grant.value"
                        class="flex items-start gap-3 rounded-lg border p-4 transition-colors"
                        :class="{ 'border-primary bg-primary/5': form.grant_types.includes(grant.value) }"
                    >
                        <Checkbox
                            :id="`grant-${grant.value}`"
                            :model-value="form.grant_types.includes(grant.value)"
                            class="mt-0.5"
                            @update:model-value="toggleGrantType(grant.value)"
                        />
                        <div class="space-y-0.5">
                            <Label :for="`grant-${grant.value}`" class="font-medium cursor-pointer">
                                {{ grant.label }}
                            </Label>
                            <p class="text-sm text-muted-foreground">{{ grant.description }}</p>
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.grant_types" />
            </div>

            <!-- Redirect URIs (only when needed) -->
            <template v-if="needsRedirectUri">
                <Separator />
                <div class="space-y-3">
                    <div>
                        <Label class="text-base font-medium">Redirect URIs <span class="text-destructive">*</span></Label>
                        <p class="text-sm text-muted-foreground">
                            Allowed callback URLs after authorization.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(uri, i) in form.redirect_uris"
                            :key="i"
                            class="flex items-center gap-2"
                        >
                            <Input
                                v-model="form.redirect_uris[i]"
                                :placeholder="`https://example.com/callback`"
                                class="flex-1"
                                :class="{ 'border-destructive': (form.errors as Record<string, string>)[`redirect_uris.${i}`] }"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="form.redirect_uris.length === 1"
                                @click="removeRedirectUri(i)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                        <InputError
                            v-for="(_, i) in form.redirect_uris"
                            :key="`err-${i}`"
                            :message="(form.errors as Record<string, string>)[`redirect_uris.${i}`]"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addRedirectUri"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        Add redirect URI
                    </Button>
                </div>
            </template>

            <Separator />

            <!-- Login URI -->
            <div class="grid gap-2">
                <Label for="login_uri">Login URL <span class="text-destructive">*</span></Label>
                <Input
                    id="login_uri"
                    v-model="form.login_uri"
                    placeholder="https://example.com/login"
                    autocomplete="off"
                    :class="{ 'border-destructive': form.errors.login_uri }"
                />
                <p class="text-xs text-muted-foreground">URL that users click to log in to this application from the dashboard.</p>
                <InputError :message="form.errors.login_uri" />
            </div>

            <Separator />

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Create client
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :href="index()"
                    as="a"
                >
                    Cancel
                </Button>
            </div>
        </form>
    </div>
</template>
