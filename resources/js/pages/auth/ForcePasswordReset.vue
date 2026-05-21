<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Your password must be reset before you can continue. Please choose a new password.',
    },
});

defineProps<{
    csrfToken: string;
    passwordRules: string;
    errors?: Record<string, string>;
}>();
</script>

<template>
    <Head title="Reset password" />

    <form action="/force-password-reset" method="POST" class="space-y-6">
        <input type="hidden" name="_token" :value="csrfToken" />

        <div class="grid gap-2">
            <Label for="password">New password</Label>
            <PasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                placeholder="New password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors?.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors?.password_confirmation" />
        </div>

        <Button type="submit" class="w-full">Reset password</Button>
    </form>
</template>
