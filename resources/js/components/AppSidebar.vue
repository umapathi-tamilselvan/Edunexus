<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    links: { type: Array, required: true },
    open: { type: Boolean, default: false },
});

defineEmits(['close']);

const route = useRoute();

/** Links grouped under their section heading, keeping the given order. */
const groups = computed(() =>
    props.links.reduce((result, link) => {
        const title = link.section ?? '';
        const group = result.find((item) => item.title === title);

        if (group) {
            group.links.push(link);
        } else {
            result.push({ title, links: [link] });
        }

        return result;
    }, []),
);

/** A link stays active on its child pages, e.g. Students on /students/12. */
function isActive(link) {
    return route.path === link.to || route.path.startsWith(`${link.to}/`);
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 top-17.5 z-30 bg-gray-900/40 lg:hidden" aria-hidden="true" @click="$emit('close')" />

    <aside
        id="app-sidebar"
        class="fixed top-17.5 bottom-0 left-0 z-30 w-65 overflow-y-auto border-r border-gray-200 bg-white transition-[translate,visibility] duration-200 lg:visible lg:translate-x-0"
        :class="open ? 'visible translate-x-0' : 'invisible -translate-x-full'"
    >
        <nav aria-label="Main" class="flex flex-col gap-6 px-4 py-6">
            <div v-for="group in groups" :key="group.title" class="flex flex-col gap-1">
                <p v-if="group.title" class="px-3 pb-1 text-2xs font-semibold tracking-wider text-gray-500 uppercase">{{ group.title }}</p>
                <RouterLink
                    v-for="link in group.links"
                    :key="link.to"
                    :to="link.to"
                    class="group flex h-10 items-center gap-3 rounded-lg px-3 text-2sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-primary"
                    :class="isActive(link) ? 'bg-primary-light font-semibold text-primary-strong' : 'text-gray-700 hover:bg-gray-100 hover:text-primary'"
                    :aria-current="isActive(link) ? 'page' : undefined"
                >
                    <AppIcon :name="link.icon" class="size-4.5" :class="isActive(link) ? 'text-primary' : 'text-gray-500 group-hover:text-primary'" />
                    {{ link.label }}
                </RouterLink>
            </div>
        </nav>
    </aside>
</template>
