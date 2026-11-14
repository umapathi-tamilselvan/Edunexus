<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import CheckboxChips from '../../components/CheckboxChips.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import RoleBadge from '../../components/RoleBadge.vue';
import TablePagination from '../../components/TablePagination.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useListQuery } from '../composables/useListQuery';
import { session } from '../session';
import { subjectsAndTeachers } from '../subjectsAndTeachers';

const roles = ref([]);
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const users = useListQuery({
    fetchPage: async (params) => {
        const { data } = await http.get('/api/v1/users', { params });

        return { data: data.data, meta: data.meta };
    },
});

/** Subjects come from academic setup, so only users who can see it set them. */
const canSetSubjects = computed(() => session.can('academic.view') && session.hasModule('academic'));
/** Only a Super Admin may grant Super Admin. */
const assignableRoles = computed(() => roles.value.filter((role) => role.name !== 'Super Admin' || session.hasRole('Super Admin')));
const roleOptions = computed(() => assignableRoles.value.map((role) => ({ value: role.name, label: role.name })));
const subjectOptions = computed(() => subjectsAndTeachers.subjects.map((subject) => ({ value: subject.id, label: subject.name })));

const emptyForm = () => ({ name: '', email: '', password: '', roles: [], subject_ids: [] });
const form = reactive(emptyForm());

/** The drawer: { kind: 'create' } | { kind: 'roles', user } | { kind: 'subjects', user }. */
const drawer = ref(null);
const editRoles = ref([]);
const editSubjectIds = ref([]);

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    return {
        create: 'Add a user',
        roles: `Roles of ${drawer.value.user?.name ?? ''}`,
        subjects: `Subjects taught by ${drawer.value.user?.name ?? ''}`,
    }[drawer.value.kind];
});

onMounted(async () => {
    const loads = [users.load()];

    if (!roles.value.length) {
        loads.push(http.get('/api/v1/roles').then(({ data }) => (roles.value = data.data)));
    }

    if (canSetSubjects.value) {
        loads.push(subjectsAndTeachers.loadSubjects());
    }

    await Promise.all(loads).catch(() => null);
});

function openDrawer(kind, user = null) {
    clear();
    drawer.value = { kind, user };

    if (kind === 'create') {
        Object.assign(form, emptyForm());
    } else if (kind === 'roles') {
        editRoles.value = [...user.roles];
    } else {
        editSubjectIds.value = user.subjects.map((subject) => subject.id);
    }
}

function closeDrawer() {
    drawer.value = null;
}

async function reloadUsers() {
    closeDrawer();
    await users.load();
}

function submitDrawer() {
    const { kind, user } = drawer.value;

    if (kind === 'create') {
        return run(() => http.post('/api/v1/users', form), {
            success: (result) => `${result.data.name} can now sign in as ${result.data.email}.`,
            onSuccess: reloadUsers,
        });
    }

    if (kind === 'roles') {
        return run(() => http.put(`/api/v1/users/${user.id}/roles`, { roles: editRoles.value }), {
            success: `Updated roles for ${user.name}.`,
            onSuccess: reloadUsers,
        });
    }

    return run(() => http.put(`/api/v1/users/${user.id}/subjects`, { subject_ids: editSubjectIds.value }), {
        success: `Updated subjects for ${user.name}.`,
        onSuccess: reloadUsers,
    });
}
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Users" description="Everyone who can sign in to this school.">
            <button type="button" class="btn-primary" :disabled="busy" @click="openDrawer('create')">
                <AppIcon name="user-plus" class="size-4" />
                Add user
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>
        <AlertMessage v-if="users.loadFailed.value">The users could not be loaded. Check your connection and try again.</AlertMessage>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">All users</h2>
            </div>

            <LoadingState v-if="users.loading.value && !users.rows.value.length" />

            <EmptyState v-else-if="!users.rows.value.length" icon="students" title="No users yet" description="Add the first user, then give them a role." />

            <div v-else class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Roles</th>
                            <th v-if="canSetSubjects">Subjects</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.rows.value" :key="user.id" class="hover:bg-gray-50">
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
                            <td v-if="canSetSubjects" class="text-gray-700">{{ user.subjects.map((subject) => subject.name).join(', ') || '—' }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-4">
                                    <button v-if="canSetSubjects" type="button" class="btn-link" @click="openDrawer('subjects', user)">Subjects</button>
                                    <button v-if="!user.roles.includes('Super Admin') || session.hasRole('Super Admin')" type="button" class="btn-link" @click="openDrawer('roles', user)">Roles</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :meta="users.meta.value" noun="users" @go="users.go" />
        </section>

        <Drawer :open="drawer !== null" :title="drawerTitle" @close="closeDrawer">
            <form id="users-drawer-form" class="flex flex-col gap-5" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawer && error.message">{{ message }}</AlertMessage>

                <template v-if="drawer?.kind === 'create'">
                    <FormField label="Name" :error="fieldError('name')">
                        <input v-model="form.name" required class="input" />
                    </FormField>
                    <FormField label="Email" :error="fieldError('email')">
                        <input v-model="form.email" type="email" required class="input" />
                    </FormField>
                    <FormField label="Password" :error="fieldError('password')" hint="At least 8 characters. Share it with the user.">
                        <input v-model="form.password" type="password" required autocomplete="new-password" class="input" />
                    </FormField>
                    <CheckboxChips v-model="form.roles" legend="Roles" :options="roleOptions" :error="fieldError('roles') || fieldError('roles.0')" />
                    <CheckboxChips v-if="canSetSubjects && subjectOptions.length" v-model="form.subject_ids" legend="Subjects taught (for teachers)" :options="subjectOptions" />
                </template>

                <template v-else-if="drawer?.kind === 'roles'">
                    <CheckboxChips v-model="editRoles" legend="Roles" :options="roleOptions" :error="fieldError('roles') || fieldError('roles.0')" />
                </template>

                <template v-else>
                    <CheckboxChips v-model="editSubjectIds" legend="Subjects taught" :options="subjectOptions" :error="fieldError('subject_ids')">
                        <p v-if="!subjectOptions.length" class="text-2sm text-gray-600">Add subjects on the Subjects page first.</p>
                    </CheckboxChips>
                </template>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="users-drawer-form" class="btn-primary" :disabled="busy || (drawer?.kind !== 'subjects' && drawer?.kind !== 'create' && !editRoles.length)">
                    {{ busy ? 'Saving…' : drawer?.kind === 'create' ? 'Create user' : 'Save' }}
                </button>
            </template>
        </Drawer>
    </div>
</template>
