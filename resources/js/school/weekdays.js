/** Weekdays as the API numbers them: 1 is Monday, 7 is Sunday. */
export const WEEKDAYS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

/** The days a timetable has: Monday to Saturday. */
export const TIMETABLE_WEEKDAYS = WEEKDAYS.slice(0, 6);
