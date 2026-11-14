# EduNexus v4 release notes

Behaviour changes that users or API consumers will notice. The product rules themselves are in `docs/EDUNEXUS.md`.

## Attendance percentage no longer counts leave

A day marked **leave** is now left out of a student's attendance percentage, both as an attended day and as a marked day. Before, leave counted as a marked day that was not attended, so it lowered the percentage.

Example: present on 2 days, leave on 2 days.

| | Before | Now |
| --- | --- | --- |
| Days marked | 4 | 2 |
| Percentage | 50% | 100% |

Students with leave will see a higher percentage than before. The "% of N days" line on the student profile now counts only days that were not on leave. Leave is still listed as a count per status.

## Exam responses carry `has_marks`, not `marks_count`

The exam list, exam detail, create and update responses no longer include `marks_count`. They include `has_marks`, a true or false value that says whether any marks have been entered. It decides whether an exam can be deleted. Clients that read `marks_count` should read `has_marks` instead.

## Timetable is optional in Year Setup

The Timetable step of the Year Setup checklist no longer needs to be complete. It shows as **Optional** and stays optional until attendance is taken by period (a later release). Schools that take attendance per day or per session do not need a timetable.

## Classrooms are created and filled from the class page

A class's sections can be created together (a count and a capacity, named A, B, C…). The class page also places last year's students of that class into its classrooms, in name order, up to each classroom's capacity, and holds the teacher grid: the class teacher and each subject's teacher for every classroom, saved in one step.

## Attendance can be taken per session or per period

The client chooses how attendance is taken: once a day (the default), per session (morning or afternoon) or per timetable period. Each session or period has its own sheet. In session mode, half day is not recorded.

Who may mark depends on the mode. In daily mode it is the section's **class teacher** only; subject teachers can no longer mark a whole day unless they are also the class teacher. In session mode it is the class teacher or the teacher of the session's first period; in period mode it is the teacher of that period on the timetable. Principal, Admin and Super Admin can still mark any day.

## Attendance percentage counts sessions and periods

The percentage counts each record as one unit, so in session or period mode a student's percentage is over the sessions or periods marked, not days. Leave is still left out. The fields keep their names (`days_marked`, `days_attended`) for existing apps, and they now mean units.

## Guardians can be told about absences

When a student is newly marked absent, the primary guardian is sent a notice after the attendance is saved. The default driver sends nothing and only writes to the application log. A client that wants WhatsApp notices sets `EDUNEXUS_GUARDIAN_NOTIFIER=whatsapp` and the `WHATSAPP_*` values in its `.env`.

## Marks are entered per classroom, and published exams are locked

Marks are entered from the classroom's **Exams & marks** tab, one subject at a time. The tab shows, for every subject, who teaches it and whether its marks have been submitted.

An exam can be **published**. Its marks and maximum marks then cannot change until someone with the unlock permission reopens it. Publishing and reopening ask for confirmation.

## Scores are out of 10, and academic and attendance scores are separate

Scores are now out of the client's score scale (`results.scale`, default **10**, previously 5). The student's scores endpoint returns an object with the academic score (overall and per subject) and the attendance score side by side. The previous list shape is gone, so clients that read the list must read `academic.subjects` instead. The two scores are not combined; whether they should be is an open question (Q19).

Each classroom has a **Results** tab with each student's academic score per subject and overall, and their attendance score for the year.

## The teacher's home shows their classrooms and what is due

The teacher's home lists **My classrooms**: the classrooms where they are class teacher first, then the ones where they teach. It shows attendance not yet taken today (for the whole day, a session or a period, by the school's attendance mode) and marks not yet entered for exams that are not published and end within 14 days.

## Guardians sign in with a code, and see their children

Guardians sign in on the mobile app with a six-digit code sent to the phone on file. A code is valid for five minutes and works once. The first sign-in creates the guardian's login; a guardian has no password and no email on it.

Guardians see each linked child's attendance (the year's summary, today, and a chosen month), exam marks once they are published, and academic and attendance scores. Nothing about other students is visible to them.

## Guardian sign-in: stricter limits, and a clear refusal when messages are not set up

Sign-in codes are limited to three requests a minute per phone number, and separately to twenty a minute per IP address. On a production school with no message service set up, a code request is refused with a clear message instead of appearing to succeed.

A published exam now records who published it as well as when. A parent's app shows the year's scores; the lifetime scores stay on the staff profile.

## Admin and Super Admin get an office home; admissions have an open switch

Admin and Super Admin land on an **office home**: Year Setup progress, the classrooms that have no class teacher, and the applications waiting for a decision. Principals keep the leadership home.

Each academic year now has an **admissions open** switch, on the Academic page. The Year Setup step "Admissions open" is done when it is on. Every existing year starts with it off, so that step is open until an admin switches it on.

## Students have a class before a section, and move up a class by sort order

A student's enrolment now records its class first. Admissions take the application's class; a section can be given at admission or left for later. **Distribute students** moves last year's students into the next class (by sort order) and places every student of the class who has no section into its classrooms.

Previously, distribution moved returning students into the **same** class (matched by name). That is corrected.

## Subject teachers can mark the daily sheet, and each sheet records who submitted it

In daily mode a subject teacher of a section can now mark its daily attendance, as well as the class teacher. Each attendance sheet records who submitted it and when; later edits by someone else do not change that.

## Students in the final class leave when the year closes

When a new year becomes current, students in the final class of the year that closed, and not enrolled in the new year, are marked **left**. Students who move up are not affected.
