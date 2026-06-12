<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Passkeys } from '@laravel/passkeys';
import { computed, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';
import { FingerprintIcon } from 'lucide-vue-next';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your credentials to continue',
    },
});

const props = defineProps<{
    authenticationMode: 'password' | 'passwordless';
    usesPasswordAuthentication: boolean;
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

const loginDescription = computed<string>(() => {
    if (props.usesPasswordAuthentication) {
        return 'Enter your username and password below to log in';
    }

    return 'Enter your username below to continue with your configured MFA or 2FA method';
});

const submitLabel = computed<string>(() => {
    return props.usesPasswordAuthentication ? 'Log in' : 'Continue';
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
        passkeyError.value = error instanceof Error
            ? error.message
            : 'Unable to log in using passkey.';
    } finally {
        isPasskeyProcessing.value = false;
    }
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
        You are signing in to continue to <span class="font-semibold">{{ oauthClient.name }}</span>.
    </div>

    <form :action="store().url" method="POST" class="flex flex-col gap-6">
        <input type="hidden" name="_token" :value="csrfToken" />

        <div class="grid gap-6">
            <p class="text-sm text-muted-foreground">
                {{ loginDescription }}
            </p>

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
                <InputError :message="errors?.username" />
            </div>

            <div v-if="usesPasswordAuthentication" class="grid gap-2">
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
                {{ submitLabel }}
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
                <!-- using icon -->
                <FingerprintIcon class="mr-2 h-5 w-5" />
                {{ isPasskeyProcessing ? 'Signing in with passkey...' : 'Sign in with passkey' }}
            </Button>
            <InputError :message="passkeyError" />

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
                :tabindex="6"
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
                :tabindex="7"
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
            <TextLink href="/register" :tabindex="8">Sign up</TextLink>
        </div>
    </form>
</template>
