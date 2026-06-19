<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    count: number;
}>();

defineEmits<{
    clear: [];
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="count > 0"
            class="mb-3 flex flex-col gap-2 rounded-lg border border-primary/20 bg-primary/5 px-4 py-2.5 sm:flex-row sm:items-center sm:gap-3"
        >
            <!-- Mobile: count + clear in one row. Desktop: display:contents agar jadi flex children langsung -->
            <div class="flex items-center justify-between gap-3 sm:contents">
                <span class="shrink-0 text-sm font-medium text-foreground">
                    {{ count }} baris dipilih
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1.5 px-2 text-xs text-muted-foreground hover:text-foreground sm:hidden"
                    @click="$emit('clear')"
                >
                    <X class="size-3.5" />
                    Batal pilih
                </Button>
            </div>

            <div class="hidden h-4 w-px shrink-0 bg-border sm:block" />

            <div class="flex flex-1 flex-wrap items-center gap-2">
                <slot />
            </div>

            <Button
                variant="ghost"
                size="sm"
                class="ml-auto hidden h-7 shrink-0 gap-1.5 px-2 text-xs text-muted-foreground hover:text-foreground sm:flex"
                @click="$emit('clear')"
            >
                <X class="size-3.5" />
                Batal pilih
            </Button>
        </div>
    </Transition>
</template>
