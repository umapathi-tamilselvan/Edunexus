<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from './AppIcon.vue';
import InitialsAvatar from './InitialsAvatar.vue';
import RoleBadge from './RoleBadge.vue';

defineProps({
    user: { type: Object, required: true },
});

defineEmits(['sign-out']);

const route = useRoute();
const open = ref(false);
const root = ref(null);
const trigger = ref(null);

function closeOnOutsideClick(event) {
    if (open.value && !root.value?.contains(event.target)) {
        open.value = false;
    }
}

function closeOnEscape(event) {
    if (open.value && event.key === 'Escape') {
        open.value = false;
        trigger.value?.focus();
    }
}

function closeWhenFocusLeaves(event) {
    if (!root.value?.contains(event.relatedTarget)) {
        open.value = false;
    }
}

watch(
    () => route.fullPath,
    () => {
        open.value = false;
    },
);

onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick);
    document.addEventListener('keydown', closeOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutsideClick);
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div ref="root" class="relative" @focusout="closeWhenFocusLeaves">
        <button
            ref="trigger"
            type="button"
            class="flex cursor-pointer items-center gap-2.5 rounded-lg p-1 transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary sm:pr-2.5"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <InitialsAvatar :name="user.name" />
            <span class="hidden flex-col text-left sm:flex">
                <span class="text-2sm font-semibold text-gray-900">{{ user.name }}</span>
                <span v-if="user.roles.length" class="text-xs text-gray-600">{{ user.roles[0] }}</span>
            </span>
            <AppIcon name="chevron-down" class="hidden size-4 text-gray-500 transition-transform sm:block" :class="open ? 'rotate-180' : ''" />
            <span class="sr-only">Account menu</span>
        </button>

        <div v-if="open" role="menu" aria-label="Account" class="absolute top-full right-0 z-50 mt-2 w-72 rounded-xl border border-gray-200 bg-white shadow-dropdown">
            <div class="flex items-center gap-3 p-4">
                <InitialsAvatar :name="user.name" />
                <div class="flex min-w-0 flex-col">
                    <span class="truncate text-2sm font-semibold text-gray-900">{{ user.name }}</span>
                    <span class="truncate text-xs text-gray-600">{{ user.email }}</span>
                </div>
            </div>

            <div v-if="user.roles.length" class="flex flex-wrap gap-1.5 border-t border-gray-200 px-4 py-3">
                <RoleBadge v-for="role in user.roles" :key="role" :role="role" />
            </div>

            <div class="border-t border-gray-200 p-2">
                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-md px-3 py-2 text-2sm font-medium text-gray-700 transition-colors hover:bg-danger-light hover:text-danger-strong focus-visible:bg-danger-light focus-visible:text-danger-strong focus-visible:outline-none"
                    @click="$emit('sign-out')"
                >
                    <AppIcon name="logout" class="size-4.5" />
                    Sign out
                </button>
            </div>
        </div>
    </div>
</template>
