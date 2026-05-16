<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { approve, deny } from '@/routes/passport/authorizations';

defineProps<{
    client: {
        id: string;
        name: string;
    };
    user: {
        name: string;
    };
    scopes: Array<{
        id: string;
        description: string;
    }>;
    authToken: string;
    request: Record<string, string>;
    csrfToken: string;
}>();
</script>

<template>
    <Head title="Authorize Application" />

    <div class="flex min-h-screen items-center justify-center bg-background p-4">
        <div class="w-full max-w-md rounded-xl border bg-card p-8 shadow-sm">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">Authorize Application</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    <strong>{{ client.name }}</strong> is requesting access to your account.
                </p>
            </div>

            <div v-if="scopes.length > 0" class="mb-6">
                <p class="mb-2 text-sm font-medium">This application will be able to:</p>
                <ul class="space-y-1">
                    <li
                        v-for="scope in scopes"
                        :key="scope.id"
                        class="flex items-start gap-2 text-sm text-muted-foreground"
                    >
                        <span class="mt-0.5 text-green-500">✓</span>
                        {{ scope.description }}
                    </li>
                </ul>
            </div>

            <div class="flex gap-3">
                <form :action="approve().url" method="POST" class="flex-1">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="auth_token" :value="authToken" />
                    <template v-for="(value, key) in request" :key="key">
                        <input type="hidden" :name="String(key)" :value="value" />
                    </template>
                    <Button type="submit" class="w-full">Authorize</Button>
                </form>

                <form :action="deny().url" method="POST" class="flex-1">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="_method" value="DELETE" />
                    <input type="hidden" name="auth_token" :value="authToken" />
                    <template v-for="(value, key) in request" :key="key">
                        <input type="hidden" :name="String(key)" :value="value" />
                    </template>
                    <Button type="submit" variant="outline" class="w-full">Deny</Button>
                </form>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                Logged in as <strong>{{ user.name }}</strong>
            </p>
        </div>
    </div>
</template>
