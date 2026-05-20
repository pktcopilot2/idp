<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

defineProps<{
    csrfToken: string;
    errors?: Record<string, string>;
}>();

const showRecoveryInput = ref<boolean>(false);

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});


const toggleRecoveryMode = (): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    code.value = '';
};

const code = ref<string>('');
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <form :action="store().url" method="POST" class="space-y-4">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors?.code" />
                </div>
                <Button type="submit" class="w-full">Continue</Button>
                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="toggleRecoveryMode"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </form>
        </template>

        <template v-else>
            <form :action="store().url" method="POST" class="space-y-4">
                <input type="hidden" name="_token" :value="csrfToken" />
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    :autofocus="showRecoveryInput"
                    required
                />
                <InputError :message="errors?.recovery_code" />
                <Button type="submit" class="w-full">Continue</Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="toggleRecoveryMode"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </form>
        </template>
    </div>
</template>
