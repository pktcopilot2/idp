<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your username and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    message?: string;
    errors?: Record<string, string>;
    csrfToken: string;
    oauthClient?: {
        id: string;
        name: string;
    } | null;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div
        v-if="message"
        class="mb-4 text-center text-sm font-medium text-red-600"
    >
        {{ message }}
    </div>

    <div
        v-if="oauthClient"
        class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900"
    >
        You are signing in to continue to <span class="font-semibold">{{ oauthClient.name }}</span>.
    </div>

    <form :action="store().url" method="POST" class="flex flex-col gap-6">
        <input type="hidden" name="_token" :value="csrfToken" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Enter your username"
                />
                <InputError :message="errors?.username" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        href="/forgot-password"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors?.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                data-test="login-button"
            >
                Log in
            </Button>

            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">Or continue with</span>
                </div>
            </div>

            <Button
                as="a"
                href="/login/keycloak"
                type="button"
                variant="outline"
                class="w-full"
                :tabindex="5"
                data-test="login-keycloak-button"
            >
                Login with Keycloak (SSO Pusri)
            </Button>

            <!-- Add login as fusionauth -->
            <Button
                as="a"
                href="/login/fusionauth"
                type="button"
                variant="outline"
                class="w-full bg-linear-to-r from-blue-500 to-purple-500 text-white hover:from-blue-600 hover:to-purple-600"
                :tabindex="6"
                data-test="login-fusionauth-button"
            >
                Login with FusionAuth (PI Identik)
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            Don't have an account?
            <TextLink href="/register" :tabindex="6">Sign up</TextLink>
        </div>
    </form>
</template>
