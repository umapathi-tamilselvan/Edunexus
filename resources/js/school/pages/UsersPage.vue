<script setup>
import { onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import RoleBadge from '../../components/RoleBadge.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { session } from '../session';

const users = ref([]);
const roles = ref([]);
const meta = ref(null);
const page = ref(1);
const loading = ref(true);
const notice = ref('');
const loadError = ref('');

const showCreate = ref(false);
const saving = ref(false);
const createError = ref({ message: '', fields: {} });
const emptyForm = () => ({ name: '', email: '', password: '', roles: [] });
const form = reactive(emptyForm());

const editing = ref(null);
const editRoles = ref([]);
const editError = ref({ message: '', fields: {} });

/** Only a Super Admin may grant Super Admin. */
const assignableRoles = () => roles.value.filter((role) => role.name !== 'Super Admin' || session.hasRole('Super Admin'));

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const [usersResponse, rolesResponse] = await Promise.all([
            http.get('/api/v1/users', { params: { page: page.value } }),
            roles.value.length ? Promise.resolve(null) : http.get('/api/v1/roles'),
        ]);
        users.value = usersResponse.data.data;
        meta.value = usersResponse.data.meta;
        if (rolesResponse) {
            roles.value = rolesResponse.data.data;
        }
    } catch (error) {
        loadError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, emptyForm());
    createError.value = { message: '', fields: {} };
    notice.value = '';
    showCreate.value = true;
}

async function createUser() {
    saving.value = true;
    createError.value = { message: '', fields: {} };

    try {
        const { data } = await http.post('/api/v1/users', form);
        notice.value = `${data.data.name} can now sign in as ${data.data.email}.`;
        showCreate.value = false;
        await load();
    } catch (error) {
        createError.value = parseError(error);
    } finally {
        saving.value = false;
    }
}

function startEdit(user) {
    editing.value = user;
    editRoles.value = [...user.roles];
    editError.value = { message: '', fields: {} };
    notice.value = '';
}

async function saveRoles() {
    saving.value = true;
    editError.value = { message: '', fields: {} };

    try {
        await http.put(`/api/v1/users/${editing.value.id}/roles`, { roles: editRoles.value });
        notice.value = `Updated roles for ${editing.value.name}.`;
        editing.value = null;
        await load();
    } catch (error) {
        editError.value = parseError(error);
    } finally {
        saving.value = false;
    }
}

function goTo(target) {
    page.value = target;
    load();
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="text-sm text-slate-500">Everyone who can sign in to this school.</p>
            </div>
            <button v-if="!showCreate" type="button" class="btn-primary" @click="openCreate">Add user</button>
        </div>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

        <form v-if="showCreate" class="card space-y-5 p-5" @submit.prevent="createUser">
            <h2 class="font-semibold">Add user</h2>
            <AlertMessage v-if="createError.message">{{ createError.message }}</AlertMessage>

            <div class="grid gap-4 sm:grid-cols-3">
                <FormField label="Name" :error="createError.fields.name">
                    <input v-model="form.name" required class="input" />
                </FormField>
                <FormField label="Email" :error="createError.fields.email">
                    <input v-model="form.email" type="email" required class="input" />
                </FormField>
                <FormField label="Password" :error="createError.fields.password" hint="At least 8 characters. Share it with the user.">
                    <input v-model="form.password" type="password" required autocomplete="new-password" class="input" />
                </FormField>
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-medium text-slate-700">Roles</legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="role in assignableRoles()" :key="role.name" class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm has-checked:border-indigo-500 has-checked:bg-indigo-50">
                        <input v-model="form.roles" type="checkbox" :value="role.name" class="accent-indigo-600" />
                        {{ role.name }}
                    </label>
                </div>
                <p v-if="createError.fields.roles || createError.fields['roles.0']" class="mt-1 text-sm text-red-600">
                    {{ createError.fields.roles || createError.fields['roles.0'] }}
                </p>
            </fieldset>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="saving" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Create user' }}</button>
            </div>
        </form>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>

        <div class="card overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Roles</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Loading…</td>
                    </tr>
                    <template v-for="user in users" v-else :key="user.id">
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <RoleBadge v-for="role in user.roles" :key="role" :role="role" />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="!user.roles.includes('Super Admin') || session.hasRole('Super Admin')"
                                    type="button"
                                    class="text-sm font-medium text-indigo-600 hover:underline"
                                    @click="startEdit(user)"
                                >
                                    Edit roles
                                </button>
                            </td>
                        </tr>
                        <tr v-if="editing?.id === user.id" class="bg-slate-50">
                            <td colspan="4" class="px-4 py-4">
                                <form class="space-y-3" @submit.prevent="saveRoles">
                                    <AlertMessage v-if="editError.message">{{ editError.fields.roles || editError.message }}</AlertMessage>
                                    <div class="flex flex-wrap gap-2">
                                        <label v-for="role in assignableRoles()" :key="role.name" class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm has-checked:border-indigo-500 has-checked:bg-indigo-50">
                                            <input v-model="editRoles" type="checkbox" :value="role.name" class="accent-indigo-600" />
                                            {{ role.name }}
                                        </label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn-primary" :disabled="saving || !editRoles.length">Save roles</button>
                                        <button type="button" class="btn-secondary" :disabled="saving" @click="editing = null">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" :disabled="meta.current_page === 1" @click="goTo(meta.current_page - 1)">Previous</button>
                <button type="button" class="btn-secondary" :disabled="meta.current_page === meta.last_page" @click="goTo(meta.current_page + 1)">Next</button>
            </div>
        </div>
    </div>
</template>
