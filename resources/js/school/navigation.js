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
            to: '/attendance',
            label: 'Attendance',
            icon: 'circle-check',
            section: 'Academics',
            description: 'Mark and review daily attendance by section.',
            visible: session.hasModule('attendance') && session.can('attendance.view'),
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
            to: '/subject-teachers',
            label: 'Subject teachers',
            icon: 'presentation',
            section: 'Academics',
            description: 'Who teaches each subject to each section, and teacher workload.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/academic',
            label: 'Academic setup',
            icon: 'academic',
            section: 'Academics',
            description: 'Academic years and their classes and sections.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/subjects',
            label: 'Subjects',
            icon: 'book',
            section: 'Academics',
            description: 'Subjects and the teachers who teach them.',
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
