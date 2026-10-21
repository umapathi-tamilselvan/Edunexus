<script setup>
import { onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import RoleBadge from '../../components/RoleBadge.vue';
import TablePagination from '../../components/TablePagination.vue';
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
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Users" description="Everyone who can sign in to this school.">
            <button v-if="!showCreate" type="button" class="btn-primary" @click="openCreate">
                <AppIcon name="user-plus" class="size-4" />
                Add user
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

        <form v-if="showCreate" class="card" @submit.prevent="createUser">
            <div class="card-header">
                <h2 class="card-title">Add user</h2>
                <button type="button" class="btn-icon btn-sm" aria-label="Close" :disabled="saving" @click="showCreate = false">
                    <AppIcon name="x" class="size-4.5" />
                </button>
            </div>

            <div class="card-body flex flex-col gap-6">
                <AlertMessage v-if="createError.message">{{ createError.message }}</AlertMessage>

                <div class="grid gap-5 sm:grid-cols-3">
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

                <fieldset class="flex flex-col gap-2.5">
                    <legend class="mb-2.5 text-2sm font-medium text-gray-800">Roles</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="role in assignableRoles()" :key="role.name" class="choice">
                            <input v-model="form.roles" type="checkbox" :value="role.name" class="checkbox" />
                            {{ role.name }}
                        </label>
                    </div>
                    <p v-if="createError.fields.roles || createError.fields['roles.0']" class="text-xs text-danger-strong">
                        {{ createError.fields.roles || createError.fields['roles.0'] }}
                    </p>
                </fieldset>
            </div>

            <div class="card-footer">
                <button type="button" class="btn-secondary" :disabled="saving" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Create user' }}</button>
            </div>
        </form>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">All users</h2>
            </div>

            <LoadingState v-if="loading" />

            <EmptyState v-else-if="!users.length" icon="students" title="No users yet" />

            <div v-else class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Roles</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="user in users" :key="user.id">
                            <tr class="hover:bg-gray-50">
                                <td>
                                    <div class="flex items-center gap-3 whitespace-nowrap">
                                        <InitialsAvatar :name="user.name" size="sm" />
                                        <div class="flex min-w-0 flex-col">
                                            <span class="font-medium text-gray-900">{{ user.name }}</span>
                                            <span class="text-xs text-gray-600">{{ user.email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <RoleBadge v-for="role in user.roles" :key="role" :role="role" />
                                    </div>
                                </td>
                                <td class="text-right">
                                    <button
                                        v-if="(!user.roles.includes('Super Admin') || session.hasRole('Super Admin')) && editing?.id !== user.id"
                                        type="button"
                                        class="btn-link"
                                        @click="startEdit(user)"
                                    >
                                        Edit roles
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="editing?.id === user.id" class="bg-gray-50">
                                <td colspan="3">
                                    <form class="flex flex-col gap-3" @submit.prevent="saveRoles">
                                        <AlertMessage v-if="editError.message">{{ editError.fields.roles || editError.message }}</AlertMessage>
                                        <div class="flex flex-wrap gap-2">
                                            <label v-for="role in assignableRoles()" :key="role.name" class="choice">
                                                <input v-model="editRoles" type="checkbox" :value="role.name" class="checkbox" />
                                                {{ role.name }}
                                            </label>
                                        </div>
                                        <div class="flex gap-2.5">
                                            <button type="submit" class="btn-primary btn-sm" :disabled="saving || !editRoles.length">Save roles</button>
                                            <button type="button" class="btn-secondary btn-sm" :disabled="saving" @click="editing = null">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <TablePagination :meta="meta" noun="users" @go="goTo" />
        </section>
    </div>
</template>
