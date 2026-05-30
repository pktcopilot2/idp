<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
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
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    emailMfaEnabled?: boolean;
    emailMfaSetupPending?: boolean;
    whatsappMfaEnabled?: boolean;
    whatsappNumber?: string | null;
    passwordRules: string;
};

const props = withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    emailMfaEnabled: false,
    emailMfaSetupPending: false,
    whatsappMfaEnabled: false,
    whatsappNumber: null,
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);
const showEmailMfaModal = ref<boolean>(props.emailMfaSetupPending);

watch(
    () => props.emailMfaSetupPending,
    (val) => { showEmailMfaModal.value = val; },
);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save password
                </Button>
            </div>
        </Form>
    </div>

    <div v-if="canManageTwoFactor" class="space-y-6">
        <Heading
            variant="small"
            title="Two-factor authentication"
            description="Manage your two-factor authentication settings"
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                When you enable two-factor authentication, you will be prompted
                for a secure pin during login. This pin can be retrieved from a
                TOTP-supported application on your phone.
            </p>

            <div>
                <Button v-if="hasSetupData" @click="showSetupModal = true">
                    <ShieldCheck />Continue setup
                </Button>
                <Form
                    v-else
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                    #default="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        Enable 2FA
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                You will be prompted for a secure, random pin during login,
                which you can retrieve from the TOTP-supported application on
                your phone.
            </p>

            <div class="relative inline">
                <Form v-bind="disable.form()" #default="{ processing }">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        Disable 2FA
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Email MFA"
            description="Receive a one-time verification code via email each time you log in"
        />

        <div v-if="!emailMfaEnabled" class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                When you enable email MFA, a 6-digit verification code will be
                sent to your email address each time you sign in.
            </p>

            <Form
                v-bind="SecurityController.initiateEmailMfa.form()"
                @success="showEmailMfaModal = true"
                #default="{ processing }"
            >
                <Button type="submit" :disabled="processing">
                    Enable email MFA
                </Button>
            </Form>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                Email MFA is active. A 6-digit code will be sent to your email
                address each time you sign in.
            </p>

            <Form
                v-bind="SecurityController.disableEmailMfa.form()"
                #default="{ processing }"
            >
                <Button
                    variant="destructive"
                    type="submit"
                    :disabled="processing"
                >
                    Disable email MFA
                </Button>
            </Form>
        </div>

        <Dialog :open="showEmailMfaModal" @update:open="showEmailMfaModal = $event">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Verify your email</DialogTitle>
                    <DialogDescription>
                        A 6-digit verification code has been sent to your email
                        address. Enter the code below to enable Email MFA.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="SecurityController.enableEmailMfa.form()"
                    class="space-y-4"
                    #default="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="email_mfa_code">Verification code</Label>
                        <Input
                            id="email_mfa_code"
                            name="code"
                            maxlength="6"
                            placeholder="000000"
                            autocomplete="one-time-code"
                            :class="{ 'border-destructive': errors.code }"
                        />
                        <InputError :message="errors.code" />
                    </div>

                    <DialogFooter class="flex-col items-start gap-3 sm:flex-col sm:items-start">
                        <Button type="submit" class="w-full" :disabled="processing">
                            Verify &amp; Enable
                        </Button>
                        <Form
                            v-bind="SecurityController.initiateEmailMfa.form()"
                            #default="{ processing: resending }"
                        >
                            <button
                                type="submit"
                                :disabled="resending"
                                class="text-sm text-muted-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                            >
                                Send a new code
                            </button>
                        </Form>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="WhatsApp MFA"
            description="Receive a one-time verification code via WhatsApp each time you log in"
        />

        <div v-if="!whatsappMfaEnabled" class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                When you enable WhatsApp MFA, a 6-digit verification code will be
                sent to your WhatsApp number each time you sign in.
            </p>

            <Form
                v-bind="SecurityController.enableWhatsappMfa.form()"
                class="w-full max-w-sm space-y-4"
                #default="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="whatsapp_number">WhatsApp number <span class="text-destructive">*</span></Label>
                    <Input
                        id="whatsapp_number"
                        name="whatsapp_number"
                        :default-value="whatsappNumber ?? ''"
                        placeholder="628123456789"
                        autocomplete="off"
                        :class="{ 'border-destructive': errors.whatsapp_number }"
                    />
                    <InputError :message="errors.whatsapp_number" />
                </div>
                <Button type="submit" :disabled="processing">
                    Enable WhatsApp MFA
                </Button>
            </Form>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                WhatsApp MFA is active. A 6-digit code will be sent to
                <span class="font-medium text-foreground">{{ whatsappNumber }}</span>
                each time you sign in.
            </p>

            <Form
                v-bind="SecurityController.disableWhatsappMfa.form()"
                #default="{ processing }"
            >
                <Button
                    variant="destructive"
                    type="submit"
                    :disabled="processing"
                >
                    Disable WhatsApp MFA
                </Button>
            </Form>
        </div>
    </div>
</template>
