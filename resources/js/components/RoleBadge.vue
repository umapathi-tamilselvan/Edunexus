<script setup>
defineProps({
    role: { type: String, required: true },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="role === 'Super Admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700'"
    >
        {{ role }}
    </span>
</template>
