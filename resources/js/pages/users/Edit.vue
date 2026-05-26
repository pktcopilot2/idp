<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { index } from '@/routes/users';

type UserData = {
    id: number;
    name: string;
    username: string;
    email: string;
    active: boolean;
    email_mfa_enabled: boolean;
    is_need_password_reset: boolean;
};

const props = defineProps<{
    user: UserData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Edit User' },
        ],
    },
});

const form = useForm({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email,
    active: props.user.active,
    email_mfa_enabled: props.user.email_mfa_enabled,
    is_need_password_reset: props.user.is_need_password_reset,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmation = ref(false);

function submit() {
    form.put(UserController.update.url({ user: props.user.id }));
}
</script>

<template>
    <Head title="Edit User" />

    <div class="max-w-2xl space-y-8 p-4">
        <div>
            <h2 class="text-lg font-semibold">Edit User</h2>
            <p class="text-sm text-muted-foreground">
                Update details for <span class="font-medium text-foreground">{{ user.name }}</span>.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Name -->
            <div class="grid gap-2">
                <Label for="name">Full name <span class="text-destructive">*</span></Label>
                <Input
                    id="name"
                    v-model="form.name"
                    placeholder="John Doe"
                    autocomplete="off"
                    :class="{ 'border-destructive': form.errors.name }"
                />
                <InputError :message="form.errors.name" />
            </div>

            <!-- Username -->
            <div class="grid gap-2">
                <Label for="username">Username <span class="text-destructive">*</span></Label>
                <Input
                    id="username"
                    v-model="form.username"
                    placeholder="johndoe"
                    autocomplete="off"
                    :class="{ 'border-destructive': form.errors.username }"
                />
                <InputError :message="form.errors.username" />
            </div>

            <!-- Email -->
            <div class="grid gap-2">
                <Label for="email">Email <span class="text-destructive">*</span></Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="john@example.com"
                    autocomplete="off"
                    :class="{ 'border-destructive': form.errors.email }"
                />
                <InputError :message="form.errors.email" />
            </div>

            <Separator />

            <!-- Flags -->
            <div class="space-y-3">
                <Label class="text-base font-medium">Account settings</Label>

                <div class="space-y-2">
                    <!-- Active -->
                    <div class="flex items-start gap-3 rounded-lg border p-4">
                        <Checkbox
                            id="active"
                            :model-value="form.active"
                            class="mt-0.5"
                            @update:model-value="form.active = !!$event"
                        />
                        <div class="space-y-0.5">
                            <Label for="active" class="font-medium cursor-pointer">Active</Label>
                            <p class="text-sm text-muted-foreground">
                                Inactive users cannot log in.
                            </p>
                        </div>
                    </div>

                    <!-- Email MFA -->
                    <div class="flex items-start gap-3 rounded-lg border p-4">
                        <Checkbox
                            id="email_mfa_enabled"
                            :model-value="form.email_mfa_enabled"
                            class="mt-0.5"
                            @update:model-value="form.email_mfa_enabled = !!$event"
                        />
                        <div class="space-y-0.5">
                            <Label for="email_mfa_enabled" class="font-medium cursor-pointer">Email MFA</Label>
                            <p class="text-sm text-muted-foreground">
                                Require a one-time code sent to the user's email on each login.
                            </p>
                        </div>
                    </div>

                    <!-- Force password reset -->
                    <div class="flex items-start gap-3 rounded-lg border p-4">
                        <Checkbox
                            id="is_need_password_reset"
                            :model-value="form.is_need_password_reset"
                            class="mt-0.5"
                            @update:model-value="form.is_need_password_reset = !!$event"
                        />
                        <div class="space-y-0.5">
                            <Label for="is_need_password_reset" class="font-medium cursor-pointer">Force password reset</Label>
                            <p class="text-sm text-muted-foreground">
                                User will be required to change their password on next login.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Change password -->
            <div class="space-y-4">
                <div>
                    <Label class="text-base font-medium">Change password</Label>
                    <p class="text-sm text-muted-foreground">Leave blank to keep the current password.</p>
                </div>

                <div class="grid gap-2">
                    <Label for="password">New password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="pr-10"
                            :class="{ 'border-destructive': form.errors.password }"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                            tabindex="-1"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm new password</Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                            tabindex="-1"
                            @click="showConfirmation = !showConfirmation"
                        >
                            <EyeOff v-if="showConfirmation" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Save changes
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
