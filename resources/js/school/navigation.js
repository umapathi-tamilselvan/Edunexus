import { computed } from 'vue';
import { session } from './session';

/** Any of these lets a user open the students pages. */
export const STUDENT_VIEW = ['student.view', 'student.view_assigned', 'student.manage'];

/**
 * Pages the signed-in user may open, for the sidebar and the dashboard
 * shortcuts. Links sharing a section are grouped under that heading.
 */
export const navigation = computed(() =>
    [
        {
            to: '/dashboard',
            label: 'Dashboard',
            icon: 'dashboard',
            visible: true,
        },
        {
            to: '/students',
            label: 'Students',
            icon: 'students',
            section: 'Academics',
            description: 'Student records, guardians and enrollment.',
            visible: session.hasModule('student') && session.canAny(STUDENT_VIEW),
        },
        {
            to: '/admissions',
            label: 'Admissions',
            icon: 'admissions',
            section: 'Academics',
            description: 'Enquiries, decisions and admitting applicants.',
            visible: session.hasModule('admission') && session.can('admission.manage'),
        },
        {
            to: '/timetable',
            label: 'Timetable',
            icon: 'timetable',
            section: 'Academics',
            description: 'Bell schedule and weekly timetables.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/academic',
            label: 'Academic setup',
            icon: 'academic',
            section: 'Academics',
            description: 'Academic years, structure and subjects.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/users',
            label: 'Users',
            icon: 'shield',
            section: 'Administration',
            description: 'Create logins and assign their roles.',
            visible: session.can('user.manage'),
        },
    ].filter((link) => link.visible),
);
