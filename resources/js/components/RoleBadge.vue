<script setup>
defineProps({
    role: { type: String, required: true },
});
</script>

<template>
    <span :class="role === 'Super Admin' ? 'badge-primary' : 'badge-neutral'">
        {{ role }}
    </span>
</template>
