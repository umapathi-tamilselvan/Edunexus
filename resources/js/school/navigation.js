import { computed } from 'vue';
import { session } from './session';

/** Any of these lets a user open the students pages. */
export const STUDENT_VIEW = ['student.view', 'student.view_assigned', 'student.manage'];

/**
 * Pages the signed-in user may open, for the sidebar and the Home quick
 * links. Links are grouped by area; an area with no visible link is not
 * shown at all. Assessment, Finance and Insights have no pages yet.
 */
export const navigation = computed(() =>
    [
        {
            to: '/home',
            label: 'Home',
            icon: 'dashboard',
            area: '',
            description: 'Your start page.',
            visible: true,
        },
        {
            to: '/daily/attendance',
            label: 'Attendance',
            icon: 'circle-check',
            area: 'Daily',
            description: 'Mark and review daily attendance by section.',
            visible: session.hasModule('attendance') && session.can('attendance.view'),
        },
        {
            to: '/daily/timetable',
            label: 'Timetable',
            icon: 'timetable',
            area: 'Daily',
            description: 'Your week, and the bell schedule and timetables of each section.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/people/students',
            label: 'Students',
            icon: 'students',
            area: 'People',
            description: 'Student records, guardians and enrollment.',
            visible: session.hasModule('student') && session.canAny(STUDENT_VIEW),
        },
        {
            to: '/people/classrooms',
            label: 'Classrooms',
            icon: 'layers',
            area: 'People',
            description: 'Each classroom of the year: its students, teachers, attendance, marks and results.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/people/admissions',
            label: 'Admissions',
            icon: 'admissions',
            area: 'People',
            description: 'Enquiries, decisions and admitting applicants.',
            visible: session.hasModule('admission') && session.can('admission.manage'),
        },
        {
            to: '/settings/year-setup',
            label: 'Year setup',
            icon: 'calendar',
            area: 'Settings',
            description: 'The steps to get the academic year ready, and what is still missing.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/settings/academic',
            label: 'Academic setup',
            icon: 'academic',
            area: 'Settings',
            description: 'Academic years and their classes and sections.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/settings/subjects',
            label: 'Subjects',
            icon: 'book',
            area: 'Settings',
            description: 'Subjects and the teachers who teach them.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/settings/subject-teachers',
            label: 'Subject teachers',
            icon: 'presentation',
            area: 'Settings',
            description: 'Who teaches each subject to each section, and teacher workload.',
            visible: session.hasModule('academic') && session.can('academic.view'),
        },
        {
            to: '/settings/users',
            label: 'Users',
            icon: 'shield',
            area: 'Settings',
            description: 'Create logins and assign their roles.',
            visible: session.can('user.manage'),
        },
        {
            to: '/settings/client',
            label: 'Client settings',
            icon: 'layers',
            area: 'Settings',
            description: 'Time zone, attendance rules and the at-risk thresholds for this school.',
            visible: session.can('settings.manage'),
        },
    ].filter((link) => link.visible),
);
