import { createRouter, createWebHistory } from 'vue-router';
import { http } from '../lib/http';
import { academicYear } from './academicYear';
import AcademicPage from './pages/AcademicPage.vue';
import AdmissionsPage from './pages/AdmissionsPage.vue';
import AttendancePage from './pages/AttendancePage.vue';
import ClientSettingsPage from './pages/ClientSettingsPage.vue';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';
import StudentDetailPage from './pages/StudentDetailPage.vue';
import StudentsPage from './pages/StudentsPage.vue';
import SubjectsPage from './pages/SubjectsPage.vue';
import SubjectTeachersPage from './pages/SubjectTeachersPage.vue';
import TimetablePage from './pages/TimetablePage.vue';
import UsersPage from './pages/UsersPage.vue';
import YearSetupPage from './pages/YearSetupPage.vue';
import { STUDENT_VIEW } from './navigation';
import { session } from './session';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/home' },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },

        { path: '/home', name: 'dashboard', component: DashboardPage },
        { path: '/dashboard', redirect: '/home' },

        { path: '/daily/attendance', name: 'attendance', component: AttendancePage, meta: { permission: 'attendance.view', module: 'attendance' } },
        { path: '/attendance', redirect: '/daily/attendance' },
        { path: '/daily/timetable', name: 'timetable', component: TimetablePage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/timetable', redirect: '/daily/timetable' },

        { path: '/people/students', name: 'students', component: StudentsPage, meta: { permission: STUDENT_VIEW, module: 'student' } },
        { path: '/people/students/:id', name: 'student', component: StudentDetailPage, meta: { permission: STUDENT_VIEW, module: 'student' } },
        { path: '/students', redirect: '/people/students' },
        { path: '/students/:id', redirect: (to) => `/people/students/${to.params.id}` },
        { path: '/people/admissions', name: 'admissions', component: AdmissionsPage, meta: { permission: 'admission.manage', module: 'admission' } },
        { path: '/admissions', redirect: '/people/admissions' },

        { path: '/settings/year-setup', name: 'year-setup', component: YearSetupPage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/settings/academic', name: 'academic', component: AcademicPage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/academic', redirect: '/settings/academic' },
        { path: '/settings/subjects', name: 'subjects', component: SubjectsPage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/subjects', redirect: '/settings/subjects' },
        { path: '/settings/subject-teachers', name: 'subject-teachers', component: SubjectTeachersPage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/subject-teachers', redirect: '/settings/subject-teachers' },
        { path: '/settings/users', name: 'users', component: UsersPage, meta: { permission: 'user.manage' } },
        { path: '/users', redirect: '/settings/users' },
        { path: '/settings/client', name: 'client-settings', component: ClientSettingsPage, meta: { permission: 'settings.manage' } },

        { path: '/:pathMatch(.*)*', redirect: '/home' },
    ],
});

router.beforeEach(async (to) => {
    await session.load();

    if (to.meta.guest) {
        return session.signedIn ? { name: 'dashboard' } : true;
    }

    if (!session.signedIn) {
        return { name: 'login' };
    }

    const permissions = [to.meta.permission ?? []].flat();

    if ((permissions.length && !session.canAny(permissions)) || (to.meta.module && !session.hasModule(to.meta.module))) {
        return { name: 'dashboard' };
    }

    // Pages open in the selected year; without the years, the API uses the current one.
    if (session.can('academic.view') && session.hasModule('academic')) {
        await academicYear.load().catch(() => null);
    }
});

// An expired session sends the user back to sign in.
http.interceptors.response.use(undefined, (error) => {
    if ([401, 419].includes(error.response?.status) && session.signedIn) {
        session.user = null;
        router.push({ name: 'login' });
    }

    return Promise.reject(error);
});
