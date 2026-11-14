<script setup>
import { onMounted, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import CheckboxChips from '../../components/CheckboxChips.vue';
import Drawer from '../../components/Drawer.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const DAYS = [
    { value: 'monday', label: 'Mon' },
    { value: 'tuesday', label: 'Tue' },
    { value: 'wednesday', label: 'Wed' },
    { value: 'thursday', label: 'Thu' },
    { value: 'friday', label: 'Fri' },
    { value: 'saturday', label: 'Sat' },
    { value: 'sunday', label: 'Sun' },
];

const TIME_ZONES = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];

/**
 * The settings a client admin sees, in groups. `kind` picks the field the
 * drawer shows and how the value reads on the page.
 */
const GROUPS = [
    {
        title: 'Academic year',
        items: [{ key: 'academic_year.start_month', label: 'Year starts in', kind: 'month', purpose: 'The start of each academic year.' }],
    },
    {
        title: 'School',
        items: [
            { key: 'school.timezone', label: 'Time zone', kind: 'timezone', purpose: 'Local-time rules across the app.' },
            { key: 'weekly_off_days', label: 'Weekly off days', kind: 'weekdays', purpose: 'Days that are not school days. Attendance cannot be marked on them.' },
        ],
    },
    {
        title: 'Attendance',
        items: [
            { key: 'attendance.edit_window_days', label: 'Days back teachers can change attendance', kind: 'number', min: 0, max: 365, suffix: ' days', purpose: 'How far back a teacher can change a day they marked.' },
            { key: 'attendance.cutoff_time', label: 'Attendance cut-off time', kind: 'time', purpose: 'The time teacher on-time attendance is judged against.' },
        ],
    },
    {
        title: 'Insights',
        items: [
            { key: 'insights.section_rank_enabled', label: 'Section rank', kind: 'boolean', onText: 'Shown in insights', offText: 'Hidden', purpose: 'Whether sections are ranked against each other.' },
            { key: 'insights.at_risk.attendance_below', label: 'At risk: attendance below', kind: 'number', min: 0, max: 100, suffix: '%', purpose: 'Students whose attendance is below this are flagged at risk.' },
            { key: 'insights.at_risk.overall_below', label: 'At risk: overall score below', kind: 'number', min: 0, max: 100, suffix: '%', purpose: 'Students whose overall score is below this are flagged at risk.' },
            { key: 'insights.at_risk.trend_drop', label: 'At risk: drop in score', kind: 'number', min: 1, max: 100, suffix: ' points', purpose: 'Students whose score drops by this much are flagged at risk.' },
        ],
    },
    {
        title: 'Set by operations',
        items: [
            { key: 'institution.terminology', label: 'Names used in the app', kind: 'readonly', purpose: 'Set from the institution type. Shown here for reference.' },
            { key: 'branding.logo_url', label: 'Logo', kind: 'readonly', purpose: 'The logo the branding endpoint serves.' },
            { key: 'branding.theme', label: 'Theme', kind: 'readonly', purpose: 'The colours the branding endpoint serves.' },
        ],
    },
];

const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();
const loading = ref(true);
const settings = ref({});
const editing = ref(null);
const draft = ref(null);

function displayValue(item, value) {
    switch (item.kind) {
        case 'month':
            return value ? MONTHS[value - 1] : 'Set by the institution type';
        case 'weekdays':
            return value.length ? value.map((day) => DAYS.find((option) => option.value === day)?.label ?? day).join(', ') : 'None';
        case 'boolean':
            return value ? item.onText : item.offText;
        case 'number':
            return `${value}${item.suffix ?? ''}`;
        case 'readonly':
            if (value === null || value === undefined) {
                return 'Not set';
            }

            return typeof value === 'object' ? 'Set' : String(value);
        default:
            return value ?? 'Not set';
    }
}

function isChanged(item) {
    const setting = settings.value[item.key];

    return JSON.stringify(setting.value) !== JSON.stringify(setting.default);
}

function nest(key, value) {
    return key.split('.').reduceRight((inner, part) => ({ [part]: inner }), value);
}

function draftValue(item) {
    if (item.kind === 'boolean' || item.kind === 'weekdays') {
        return draft.value;
    }

    if (draft.value === '' || draft.value === null) {
        return null;
    }

    return item.kind === 'number' || item.kind === 'month' ? Number(draft.value) : draft.value;
}

async function load() {
    loading.value = true;

    await run(async () => {
        settings.value = (await http.get('/api/v1/settings')).data.data;
    });

    loading.value = false;
}

function openDrawer(item) {
    clear();
    editing.value = item;

    const setting = settings.value[item.key];

    if (item.kind === 'weekdays') {
        draft.value = [...setting.value];
    } else if (item.kind === 'boolean') {
        draft.value = Boolean(setting.value);
    } else {
        draft.value = setting.value ?? '';
    }
}

function closeDrawer() {
    editing.value = null;
}

/** Saves the value, or null to take the default again. */
function save(value) {
    const item = editing.value;

    return run(() => http.put('/api/v1/settings', nest(item.key, value)), {
        success: `${item.label} saved.`,
        onSuccess: (response) => {
            settings.value = response.data.data;
            closeDrawer();
        },
    });
}

function submitDrawer() {
    return save(draftValue(editing.value));
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Client settings" description="What the school has chosen. A change applies to everyone at once." />

        <AlertMessage v-if="error.message && !editing">{{ message }}</AlertMessage>
        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else-if="Object.keys(settings).length">
            <section v-for="group in GROUPS" :key="group.title" class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ group.title }}</h2>
                </div>
                <ul class="divide-y divide-gray-200">
                    <li v-for="item in group.items" :key="item.key" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-7.5">
                        <div class="flex min-w-0 flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">{{ item.label }}</span>
                                <span v-if="!settings[item.key].used" class="badge badge-neutral">Not yet used</span>
                                <span v-if="item.kind === 'readonly'" class="badge badge-neutral">Read only</span>
                            </div>
                            <span class="text-2sm text-gray-600">{{ item.purpose }}</span>
                            <span v-if="item.kind !== 'readonly'" class="text-xs text-gray-600">Default: {{ displayValue(item, settings[item.key].default) }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-4 sm:ml-4">
                            <span class="text-2sm font-semibold text-gray-900">{{ displayValue(item, settings[item.key].value) }}</span>
                            <button v-if="item.kind !== 'readonly'" type="button" class="btn-secondary btn-sm" :disabled="busy" @click="openDrawer(item)">Change</button>
                        </div>
                    </li>
                </ul>
            </section>
        </template>

        <Drawer
            :open="editing !== null"
            :title="editing ? editing.label : ''"
            :description="editing ? editing.purpose : ''"
            @close="closeDrawer"
        >
            <form v-if="editing" id="client-settings-form" class="flex flex-col gap-4" @submit.prevent="submitDrawer">
                <AlertMessage v-if="error.message">{{ message }}</AlertMessage>

                <FormField v-if="editing.kind === 'month'" :label="editing.label" :error="fieldError(editing.key)">
                    <select v-model="draft" class="input">
                        <option value="">Default: {{ displayValue(editing, settings[editing.key].default) }}</option>
                        <option v-for="(month, index) in MONTHS" :key="month" :value="index + 1">{{ month }}</option>
                    </select>
                </FormField>

                <FormField v-else-if="editing.kind === 'timezone'" :label="editing.label" :error="fieldError(editing.key)">
                    <select v-model="draft" class="input">
                        <option value="">Default: {{ settings[editing.key].default }}</option>
                        <option v-for="zone in TIME_ZONES" :key="zone" :value="zone">{{ zone }}</option>
                    </select>
                </FormField>

                <FormField v-else-if="editing.kind === 'number'" :label="editing.label" :error="fieldError(editing.key)" :hint="`Between ${editing.min} and ${editing.max}. Leave empty for the default.`">
                    <input v-model="draft" type="number" :min="editing.min" :max="editing.max" class="input" />
                </FormField>

                <FormField v-else-if="editing.kind === 'time'" :label="editing.label" :error="fieldError(editing.key)" hint="Leave empty for the default.">
                    <input v-model="draft" type="time" class="input" />
                </FormField>

                <CheckboxChips v-else-if="editing.kind === 'weekdays'" v-model="draft" :legend="editing.label" :options="DAYS" :error="fieldError(editing.key)" />

                <FormField v-else-if="editing.kind === 'boolean'" :label="editing.label" :error="fieldError(editing.key)">
                    <label class="choice">
                        <input v-model="draft" type="checkbox" class="checkbox" />
                        {{ draft ? editing.onText : editing.offText }}
                    </label>
                </FormField>
            </form>

            <template #footer>
                <button
                    v-if="editing && isChanged(editing)"
                    type="button"
                    class="btn-secondary me-auto"
                    :disabled="busy"
                    @click="save(null)"
                >
                    Use default
                </button>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="client-settings-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save' }}</button>
            </template>
        </Drawer>
    </div>
</template>
