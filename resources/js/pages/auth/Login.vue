<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Passkeys } from '@laravel/passkeys';
import { FingerprintIcon } from 'lucide-vue-next';
import { onMounted, ref, watchEffect } from 'vue';
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
        description: 'Enter your credentials to continue',
    },
});

const props = defineProps<{
    authenticationMode: 'password' | 'passwordless';
    usesPasswordAuthentication: boolean;
    loginMode?: 'password' | 'passwordless';
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    message?: string;
    errors?: Record<string, string[] | string>;
    csrfToken: string;
    oauthClient?: {
        id: string;
        name: string;
    } | null;
}>();

const activeMode = ref<'password' | 'passwordless'>(
    props.loginMode ?? props.authenticationMode,
);

watchEffect(() => {
    setLayoutProps({
        title: 'Log in to your account',
        description:
            activeMode.value === 'passwordless'
                ? 'Enter your username to continue with your MFA method'
                : 'Enter your username and password below to log in',
    });
});

const isPasskeySupported = ref<boolean>(false);
const isPasskeyProcessing = ref<boolean>(false);
const passkeyError = ref<string>('');

onMounted(() => {
    isPasskeySupported.value = Passkeys.isSupported();
});

const loginWithPasskey = async (): Promise<void> => {
    passkeyError.value = '';
    isPasskeyProcessing.value = true;

    try {
        const response = await Passkeys.verify();

        if (response.redirect) {
            window.location.href = response.redirect;

            return;
        }

        window.location.reload();
    } catch (error) {
        passkeyError.value =
            error instanceof Error ? error.message : 'Unable to log in using passkey.';
    } finally {
        isPasskeyProcessing.value = false;
    }
};

const firstError = (field: string): string | undefined => {
    const v = props.errors?.[field];

    if (!v) {
        return undefined;
    }

    return Array.isArray(v) ? v[0] : v;
};
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
        You are signing in to continue to
        <span class="font-semibold">{{ oauthClient.name }}</span>.
    </div>

    <!-- Mode switcher -->
    <div class="mb-6 grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
        <button
            type="button"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-all"
            :class="
                activeMode === 'password'
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
            @click="activeMode = 'password'"
        >
            With Password
        </button>
        <button
            type="button"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-all"
            :class="
                activeMode === 'passwordless'
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
            @click="activeMode = 'passwordless'"
        >
            Passwordless (MFA)
        </button>
    </div>

    <!-- ── Password form ── -->
    <form
        v-if="activeMode === 'password'"
        :action="store().url"
        method="POST"
        class="flex flex-col gap-6"
    >
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
                    autocomplete="username webauthn"
                    placeholder="Enter your username"
                />
                <InputError :message="firstError('username')" />
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
                <InputError :message="firstError('password')" />
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

            <Button
                v-if="isPasskeySupported"
                type="button"
                variant="secondary"
                class="w-full"
                :disabled="isPasskeyProcessing"
                :tabindex="5"
                data-test="login-passkey-button"
                @click="loginWithPasskey"
            >
                <FingerprintIcon class="mr-2 h-5 w-5" />
                {{ isPasskeyProcessing ? 'Signing in with passkey...' : 'Sign in with passkey' }}
            </Button>
            <InputError :message="passkeyError" />
        </div>

        <div
            v-if="canRegister"
            class="text-center text-sm text-muted-foreground"
        >
            Don't have an account?
            <TextLink href="/register" :tabindex="6">Sign up</TextLink>
        </div>
    </form>

    <!-- ── Passwordless form ── -->
    <form
        v-else
        action="/login/passwordless"
        method="POST"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="_token" :value="csrfToken" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username-passwordless">Username</Label>
                <Input
                    id="username-passwordless"
                    type="text"
                    name="username"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Enter your username"
                />
                <InputError :message="firstError('username')" />
            </div>

            <div class="flex items-center">
                <Label for="remember-passwordless" class="flex items-center space-x-3">
                    <Checkbox id="remember-passwordless" name="remember" :tabindex="2" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="3"
                data-test="login-passwordless-button"
            >
                Continue
            </Button>
        </div>

        <div
            v-if="canRegister"
            class="text-center text-sm text-muted-foreground"
        >
            Don't have an account?
            <TextLink href="/register" :tabindex="4">Sign up</TextLink>
        </div>
    </form>

    <!-- ── SSO options (shared) ── -->
    <div class="mt-4 flex flex-col gap-3">
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
            data-test="login-keycloak-button"
        >
            Login with Keycloak (SSO Pusri)
        </Button>

        <Button
            as="a"
            href="/login/fusionauth"
            type="button"
            variant="outline"
            class="w-full bg-linear-to-r from-blue-500 to-purple-500 text-white hover:from-blue-600 hover:to-purple-600"
            data-test="login-fusionauth-button"
        >
            Login with FusionAuth (PI Identik)
        </Button>
    </div>
</template>

