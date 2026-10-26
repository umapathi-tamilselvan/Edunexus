import { createRouter, createWebHistory } from 'vue-router';
import { http } from '../lib/http';
import { academicYear } from './academicYear';
import AcademicPage from './pages/AcademicPage.vue';
import AdmissionsPage from './pages/AdmissionsPage.vue';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';
import StudentDetailPage from './pages/StudentDetailPage.vue';
import StudentsPage from './pages/StudentsPage.vue';
import TimetablePage from './pages/TimetablePage.vue';
import UsersPage from './pages/UsersPage.vue';
import { STUDENT_VIEW } from './navigation';
import { session } from './session';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/dashboard' },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
        { path: '/dashboard', name: 'dashboard', component: DashboardPage },
        { path: '/users', name: 'users', component: UsersPage, meta: { permission: 'user.manage' } },
        { path: '/admissions', name: 'admissions', component: AdmissionsPage, meta: { permission: 'admission.manage', module: 'admission' } },
        { path: '/timetable', name: 'timetable', component: TimetablePage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/academic', name: 'academic', component: AcademicPage, meta: { permission: 'academic.view', module: 'academic' } },
        { path: '/students', name: 'students', component: StudentsPage, meta: { permission: STUDENT_VIEW, module: 'student' } },
        { path: '/students/:id', name: 'student', component: StudentDetailPage, meta: { permission: STUDENT_VIEW, module: 'student' } },
        { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
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
