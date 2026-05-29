<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';

defineOptions({
    layout: {
        title: 'WhatsApp verification',
        description: 'Enter the 6-digit verification code sent to your WhatsApp number.',
    },
});

defineProps<{
    csrfToken: string;
    status?: string;
    errors?: Record<string, string>;
}>();

const code = ref('');
</script>

<template>
    <Head title="WhatsApp verification" />

    <div class="space-y-6">
        <div
            v-if="status"
            class="text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form action="/whatsapp-mfa-challenge" method="POST" class="space-y-4">
            <input type="hidden" name="_token" :value="csrfToken" />
            <input type="hidden" name="code" :value="code" />

            <div
                class="flex flex-col items-center justify-center space-y-3 text-center"
            >
                <div class="flex w-full items-center justify-center">
                    <InputOTP v-model="code" :maxlength="6" autofocus>
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

            <Button type="submit" class="w-full">Verify</Button>
        </form>

        <form
            action="/whatsapp-mfa-challenge/resend"
            method="POST"
            class="text-center"
        >
            <input type="hidden" name="_token" :value="csrfToken" />
            <button
                type="submit"
                class="text-sm text-muted-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
            >
                Didn't receive a code? Resend
            </button>
        </form>
    </div>
</template>
