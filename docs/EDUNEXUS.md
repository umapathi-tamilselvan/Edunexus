# EduNexus

The single specification. Describes the app as it is, plus the plan. Where this document and the code disagree, the code is right and this document is wrong: fix the document in the same commit.

Status words: **built** (in code, tested), **partial** (some of it is in code), **planned** (not in code), **staged** (in code but not committed).

## 1. What EduNexus is

EduNexus runs an educational institution: students, admissions, attendance, exams and results, fees, staff and performance. One Laravel codebase serves one client per deployment. The web app is a Vue 3 SPA on the same origin; the Flutter mobile app uses the same API with bearer tokens.

**Institution types** (`App\Support\Deployment\InstitutionType`). The type sets default modules, seeded roles and terminology. Everything else is shared.

| Type | Default modules (besides core) | Roles seeded with Super Admin | Terms: class / section / teacher / head | Year starts |
| --- | --- | --- | --- | --- |
| school | Examination, Transport | Admin, Principal, Teacher, Accountant, Guardian, Student | Class / Section / Teacher / Principal | June |
| college | Examination, Library, Hostel | Admin, Principal, Teacher, Accountant, Student | Semester / Division / Faculty / Principal | July |
| coaching | none | Admin, Teacher, Accountant, Guardian, Student | Course / Batch / Tutor / Centre head | April |

The core modules for every type: Academic, Admission, Student, Staff, Attendance, Fee, Communication, Report. Modules can be enabled or disabled per deployment with `EDUNEXUS_MODULES_ENABLE` and `EDUNEXUS_MODULES_DISABLE`.

**Roles and what they do daily.**

| Role | Who | Daily work |
| --- | --- | --- |
| Super Admin | the client's first user | Creates every other login; can grant the Super Admin role. Cannot be removed as the last Super Admin. |
| Admin | office staff | Setup, people, admissions, fees. Holds every permission. Home: the office home (§2.4). |
| Principal | head of the institution | Oversight, approvals, insights. Every permission except role and settings management. |
| Teacher | class teachers and subject teachers | Marks attendance for their sections, enters marks for the subjects they teach, sees their own timetable and their students. |
| Accountant | fee office | Collects payments, issues receipts, reverses entries, sees students and fee balances. |
| Guardian | parent | Mobile app (R7): signs in with a one-time code sent to the phone on file; sees each linked child's attendance, published marks and scores. Dues come with Finance. Holds no permissions. **Built** (dues planned). |
| Student | learner | **Planned.** |

Every role's permissions are in §5 (RBAC). Roles are seeded per institution type; client edits to role permissions are **planned** (no write endpoint yet).

## 2. Product

### 2.1 Areas

Navigation is grouped by what a role does each day. Routes keep their paths where a page exists.

| Area | Screens |
| --- | --- |
| Home | Home per role (below) |
| Daily | Attendance, My timetable, Substitutions (later), Staff attendance (planned) |
| People | Students, Student profile, Classrooms, Staff (planned), Guardians (partial), Admissions |
| Assessment | Exams and marks and Results, both in the classroom (R4, R5); Exam list (planned); Terms (planned); Report cards (later) |
| Finance | Fee structures, Dues, Collection, Receipts, Defaulters (planned) |
| Insights | Student performance, Class performance, Teacher performance (planned) |
| Settings | Year Setup, Classes and sections, Subjects, Bell schedule, Roles, Users, Client settings |

### 2.2 Screens

For each screen: who sees it, what they do, status.

| Screen | Who | What the user does | Status |
| --- | --- | --- | --- |
| Sign in `/login` | everyone | signs in | built |
| Home `/home` (`/dashboard` redirects) | any signed-in user | the role home (§2.4); other roles see the generic dashboard with counts and quick links | partial |
| Attendance `/daily/attendance` (`/attendance` redirects) | `attendance.view` | picks a section and a day (and, in session or period mode, a session or period); sets present, absent, late, half day or leave with a remark per student; saves | built (modes in R3) |
| My timetable `/daily/timetable` (My timetable tab) | any teacher | views their week | built |
| Timetable `/daily/timetable` | `academic.view` | sets or clears a slot; sets an override teacher; adds periods | built |
| Students `/people/students` | `student.view` or `student.view_assigned` | searches; filters by class and section; adds a student with guardians and a section | built |
| Student profile `/people/students/{id}` | as above | edits details; links or unlinks guardians; enrols or moves section; sees attendance (at-risk flag from settings) and lifetime score | built |
| Admissions `/people/admissions` | `admission.manage` | records enquiries; approves, rejects or withdraws; admits with recorded consent | built |
| Academic setup `/settings/academic` | `academic.view` (changes need `academic.manage`) | years (make current, open next year); classes; sections with class teacher; class subjects; holidays; edit and delete for classes, sections and periods | built |
| Subjects `/settings/subjects` | `academic.view` | adds and edits subjects; picks teachers for each | built |
| Subject teachers `/settings/subject-teachers` | `academic.view` | assigns teachers per section or per class; workload tab | built |
| Users `/settings/users` | `user.manage` | creates users; sets roles and subjects a teacher teaches | built |
| Exams and marks, in a classroom `/classrooms/{id}` (Exams & marks tab) | `exam.view`; entering marks needs `exam.enter_marks` for the subject, or `exam.manage`; publishing needs `exam.publish`, reopening `exam.unlock` | exam picker; subject picker limited to what the user may enter; marks grid with absent; submission status of every subject; publish and reopen (with confirmation) | built (R4) |
| Exam list and results | `exam.view`, `exam.manage` | exam list and create screens; results per classroom (academic and attendance scores, §3.6) | exam list not built (API only); results built per classroom (R5) |
| Fee screens | `fee.view`, `fee.collect`, `fee.reverse` | dues, collection, receipts, defaulters | not built (backend staged and committed; no screen) |
| Staff, Staff attendance | `staff.view`, `staff.manage` | staff list; daily staff attendance | planned |
| Terms | `academic.view` | term names and dates per year | planned |
| Insights (three screens) | per §5.9 | performance views | planned |
| Year Setup `/settings/year-setup` | `academic.view` (the links it offers need `academic.manage`) | checklist (§2.3) | built |
| Client settings `/settings/client` | `settings.manage` | values in §6 | built (step 1.5, with R1) |
| Class `/settings/classes/{id}` | `academic.view`; creating classrooms and editing the teacher grid need `academic.manage`; placing students needs `student.manage` | creates classrooms (A, B, C…) with one capacity; places the class's students into them (§3.4); sets the class teacher and each subject's teacher per classroom in one grid (§3.3) | built (R2) |
| Classrooms `/people/classrooms` | `academic.view` | lists each class's classrooms for the selected year, with students, class teacher and capacity; opens a classroom or its class | built (sidebar under People) |
| Classroom `/classrooms/{id}` | `academic.view` | tabs: Students (paged list), Teachers (who teaches what here, read-only; changed on the class page), Attendance (the sheet for a chosen day, and session or period by mode), Exams & marks (R4), Results (empty until R5) | partial (R4); Results empty until R5 |
| Roles | `role.manage` | permissions per role | planned (read-only today) |

### 2.3 Year Setup checklist

Each step shows done, or what is missing. A step never blocks another: an admin can open the next year while steps are open.

| # | Step | Done when | Status |
| --- | --- | --- | --- |
| 1 | Open the year | the year exists and is current or open | built |
| 2 | Terms | the year has terms, and an admin has confirmed their dates | planned |
| 3 | Classes and sections | every class has a section, with capacity | built |
| 4 | Subjects per class | every class has a subject | built |
| 5 | Class teachers | every section has a class teacher | built |
| 6 | Subject teachers | every section subject has a teacher, no teacher over workload | built |
| 7 | Timetable | every section has a timetable | built; optional unless attendance mode is period (R2, R3) |
| 8 | Bell schedule | the year has periods and a teaching period; in session mode, a session is set on them | built |
| 9 | Holidays and weekly off days | holidays added; weekly off days set (default Sunday) | holidays built; weekly off planned |
| 10 | Admissions open | the year's admissions-open switch is on (D44; set on the Academic page) | built |

The status endpoint (`GET academic-years/{id}/setup-status`) and the checklist page (`/settings/year-setup`) are **built** (step 1.3). The status of each step is what the checklist shows; nothing is blocked.

### 2.4 Home per role

Each role gets its own home. The Teacher home and the Principal home are **built** (step 1.2). Principal sees the leadership home. Admin and Super Admin see the office home (Q20, D43). Admin and Super Admin get the **office home** (D43, built): Year Setup progress, classrooms without a class teacher, and pending admissions, with fee collection added once Finance exists. The Guardian home is the mobile app (R7). The Accountant home is **planned**; other roles see the generic dashboard.

| Role | Shows |
| --- | --- |
| Teacher (built, R6) | My classrooms: where they are class teacher first, then where they teach; attendance not yet taken today, by mode (whole day, session or period); marks not entered for unpublished exams ending within 14 days; their periods today |
| Principal | today's attendance by section, with sheets not yet submitted; admissions by status; fee collection today (after Finance) |
| Admin | Year Setup progress; open admissions |
| Accountant | collections today; defaulters |
| Guardian (mobile, built in R7) | each child's attendance (today, and the chosen month, with the percentage), published exam marks and scores; dues after Finance |

## 3. Business rules by module

### 3.1 Academic years and terms

- Exactly one academic year is current. A year starts on the 1st of the institution's start month (`academic_year.start_month`) and is named after it (`2026-27`).
- Years never overlap. A new year must start after the year before it ends.
- `POST academic-years/next` opens the year after the current one and copies classes, sections with their capacities, class subjects and the bell schedule. Class teachers, subject teachers and the timetable are not copied. Only one year can be open ahead of the current one. It does not become current until `make-current`.
- Admissions are open or closed per year. An admin switches the year's `admissions_open` on or off (D44); the Year Setup step "Admissions open" is done while it is on. The switch does not block applications.
- **Final class (D49).** When a year becomes current, the year that closed has its final class (the highest sort order) completed: its students who are not enrolled in the year that starts get status `left`. Students who are enrolled next year keep their status. The change is audited.
- **Making a year current (§3.1).** The confirm dialog states how many students will become left: a read-only preview (`GET academic-years/{id}/make-current-preview`) counts the closing year's final-class students who are not enrolled in the year that starts. A student marked left can be enrolled again in the new year: that enrolment is active, and the closing year's enrolment stays left as history (checked in a rolled-back transaction on the dev data).
- Years are never edited or created by hand. Their names and dates are not patched.
- A request works in the year named by the `X-Academic-Year` header, else the current year. An unknown year is a 422.
- **Terms (planned).** Each year has terms: school 3, college 2 semesters, coaching one "Full year" term. Names and dates are editable per client. Dates default to an even split by months. The terms step of Year Setup must be confirmed by an admin before exams can be created in that year.

### 3.2 Classes, sections and subjects

- Class names are unique within a year; section names within a class.
- A class with sections, or a section with students, cannot be deleted. A section's capacity cannot drop below its enrolled count.
- Subjects are shared across years. Each class offers a set of subjects.
- Taking a subject off a class also removes its subject teachers in that class's sections.
- **Classrooms (R2).** A class's sections can be created in one go: a count and one capacity. They are named A, B, C… in order, and a name the class already uses is skipped. At most 26 names; capacity 1 to 500.
- **Teacher grid (R2).** The class page shows, for every classroom, its class teacher and the teacher of each class subject. Saving checks every cell first: the subject must be offered to the class and the teacher must teach it. Nothing is written when any cell fails, and each failure is shown on its cell. Subject teacher changes then follow the rules of §3.3.

### 3.3 Subject teachers and timetable

- A teacher can teach one or more subjects (`subject_teacher`).
- A section has one teacher per subject the class studies (`section_subject_teachers`, unique on section and subject). The teacher must teach that subject.
- A class-level assignment (`PUT classes/{id}/subjects/{subject}/teacher`) sets the same teacher on every section of the class. Sections keep their own assignments afterwards.
- A subject cannot be taken away from a teacher who teaches it to sections in the current year or later. Earlier years are history.
- Each year has a bell schedule of periods; some are breaks. A period in use cannot be deleted or made a break. A teaching period can belong to a session, morning or afternoon (R3).
- Each section has a weekly grid of slots: weekday and period, with a subject and an optional teacher.
- A slot given no teacher gets the section's subject teacher, and follows it when that changes. A slot given someone else is an **override** (`is_override`) and stays put. If the new subject teacher is busy in a following slot, the change is refused with the clash named.
- A teacher cannot be in two sections in the same weekday and period. Enforced by a unique index.
- A teacher's own week is `me/timetable`; the teachers list with workload is `teachers/workload`.

### 3.4 Students and admissions

- A student exists once. Each academic year they have one enrollment: class, section (empty until distributed), roll number and status. Attendance, marks and fees hang off the enrollment, not the student.
- One enrollment per student per year. Roll numbers are unique per section and default to the next free number. Capacity is enforced under a lock.
- An enrollment can move to another section of the same year only. Changing years is promotion (planned).
- Each student has at least one guardian and exactly one primary guardian. Guardian phones are normalised (digits only), encrypted, and matched by a keyed hash, so siblings share one guardian record. Guardian phones are masked for anyone without `student.manage`.
- **Admissions.** An application moves enquiry → approved → admitted, or to rejected (reason required) or withdrawn. Closed applications cannot change. Admitting runs in one transaction: it locks the application, checks a chosen section belongs to the class applied for, creates the student, links or reuses the guardian, enrols (the enrolment always carries the application's class; the section is optional and can be filled later by distribution, D45), and writes a consent row. Consent is mandatory: the admitting user confirms the guardian saw the privacy notice and records how consent was given (signed form, in person, digital). The notice version is stored.
- Application numbers are gapless per year (`2026-27/0001`). Admission numbers default to `<CODE><00001>`, skip numbers typed by hand, and are returned if the admission rolls back.
- **Admissions switch (D50).** While a year's admissions are off, new applications for that year are refused, before a number is drawn, so the numbering has no gap. Existing applications can still be decided and admitted.
- **Placing a class's students (D45, replaces R2).** An enrolment carries its class (`class_id`); its section is empty until the student is distributed. Distributing a class does two things, in one transaction. First, students enrolled last year in the class just below this one (the previous class by sort order) and not yet enrolled this year move up: they are enrolled in this class without a section. Second, every enrolment of this class with no section is placed in the class's sections in name order, each up to its capacity, ordered by name or admission number. Each placement uses the normal rules (one enrolment a year, roll numbers, the capacity lock). Students with no seat left stay unplaced and are counted. The R2 rule matched last year's class by name, so returning students stayed in the same class; this rule replaces it.

- **Guardian sign-in (R7).** A guardian signs in with a six-digit code sent to the phone on file. The code is valid for five minutes, works once, and locks after five wrong attempts. Requests are limited per phone (3 a minute and 10 a day, counted per phone hash) and, separately, per IP (20 a minute) (D47). Checking a code is not counted against the daily cap. Whether a phone is on file is never revealed. In production a request is refused with 503 and a clear message when no working message service is configured; the log driver sends nothing and does not count as working (D47). The first successful sign-in creates the guardian's login (role Guardian, no email, no password anyone knows) and links it to the guardian record (D37). Codes are sent on the critical queue (D38).
- **What a guardian sees (R7).** Only their linked children, checked by `GuardianPolicy`: each child's attendance (the year's summary, today by session or period, and a chosen month), exam marks only for exams whose marks are published, and academic and attendance scores for the year that count published exams only (the staff profile keeps the lifetime scores; D42). Guardians hold no permissions.

### 3.5 Attendance

**Built.**

- A sheet records who submitted it and when (`attendance_sheets`, D41). The first save sets both; later edits do not change them. A sheet saved before this release gets its submitter at its next save.
- Attendance is taken in one of three modes, chosen by the client (`attendance.mode`, §6). **Daily** (the default): one sheet per section per day. **Session**: one sheet per session, morning or afternoon, per section per day. **Period**: one sheet per timetable period, per section per day (R3).
- A session is the teaching periods that share a session value (`periods.session`: morning or afternoon). Its sheet is recorded against its first teaching period by sort order. A period with no session belongs to no session.
- In period mode the sheet is for a period the section has on the timetable that weekday. Year Setup then requires the timetable (§2.3).
- A sheet lists the section's students in roll order with each one's status (present, absent, late, half day or leave) and an optional remark. In session mode half day is not recorded (D28).
- A sheet is saved in one write: one read, one upsert, at most one delete. Changes to saved records are audited in bulk with old and new values. Unchanged saves write nothing.
- A day cannot be marked if it is a holiday (the holiday's name is shown), in the future, or outside the selected year.
- A holiday cannot be added to a day that already has attendance. Clear the attendance first.
- Who may mark (`AttendancePolicy`, by mode). Anyone with `attendance.edit_late` (Principal, Admin, Super Admin) may mark any day. Otherwise, with `attendance.mark`: in daily mode, the section's class teacher or a subject teacher of it (D41); in session mode, the class teacher or the teacher of the session's first period on the timetable; in period mode, the teacher of that period on the timetable. Every such teacher marks within the edit window (`attendance.edit_window_days`, default 0: the day itself).
- Units. A unit is one record: a day, a session or a period. Present and late count 1, half day 0.5, absent 0. **Leave is left out** of both attended and marked units, so it does not change the percentage (release note). The summary keeps the field names `days_marked` and `days_attended`; they count units (D24).
- A student's summary (`students/{id}/attendance`): units marked, units attended, the percentage, the count per status, and the last 30 missed units, each with its session or period id when there is one.
- When a save marks a student absent who was not absent before, the student's primary guardian gets a notice after the save commits. It is sent by the `AbsenceAlert` job on the `critical` queue (3 tries, 5 s then 30 s backoff, 60 s timeout) through `GuardianNotifier`. The driver is `log` by default, which writes to the application log and sends nothing. `whatsapp` sends the WhatsApp Business API template named in the client's `.env` (`WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ABSENCE_TEMPLATE`, `WHATSAPP_TEMPLATE_LANGUAGE`; `EDUNEXUS_GUARDIAN_NOTIFIER=whatsapp` selects it). A driver that fails makes the job retry.

**Planned.**

- Weekly off days (`weekly_off_days`, default Sunday) cannot be marked; they are excluded like holidays.
- Attendance rows keep the section and academic year the student was in when marked (step 0.5, waiting on owner answer Q14).
- Staff attendance is per day (§3.8).

### 3.6 Exams and scores

**Built.**

- Exams belong to an academic year. Names are unique per year. The exam's maximum applies to every subject unless a paper overrides it for one class and subject (`exam_papers`).
- Marks are entered one section and one subject at a time, from the classroom's Exams & marks tab (R4). Each student gets a mark from 0 to the paper's maximum, or is absent. A blank mark removes the entry.
- A save is one bulk write: one read of the entries, one upsert, at most one delete, and every change audited (D30). A save that carries any mark or absence **submits** the subject's sheet (`exam_mark_sheets`). The first submission sets the time; later saves change only who entered it (D29). The classroom shows, for every subject, who teaches it and whether it is submitted.
- **Publishing (R4).** `exam.publish` publishes an exam: from then on its marks and its maximum marks cannot change. `exam.unlock` reopens it. Publishing records when and by whom (`published_at` and `published_by`, D46). Reopening clears both.
- Anyone with `exam.manage` enters marks for any section. A user with `exam.enter_marks` enters marks only for a section they teach that subject to.
- An exam's maximum and a paper's maximum cannot drop below marks already entered under them. A paper set back to the exam's maximum is removed.
- An exam with marks cannot be deleted. The exam response carries `has_marks` (true once any mark exists). It replaced `marks_count` in step 0.4.
- Changes to exams and papers are not yet audited (step 2.6). Marks are audited.

**Scores out of the client's scale (built, R5).** The scale is `results.scale` (default 10, §6). Two scores are kept apart and never combined:

```
percentage of one exam = mark ÷ that paper's maximum × 100        (absent exams are left out)
academic score         = average of those percentages ÷ 100 × scale, one decimal
attendance score       = attendance percentage ÷ 100 × scale, one decimal
```

Every exam counts equally, in any year, for a student's academic score (`students/{id}/scores`). The student's **overall** academic score is the average over all their exams, not an average of subject scores (D34). A classroom's results (`sections/{id}/results`) cover the classroom's year only (D33).

Example: 40/50 (80%) and 90/100 (90%) average 85%, a score of 8.5 out of 10.

**Planned.**

- Term results: the same formula within a term, per subject (step 5.1). The lifetime score stays next to the term score.
- Marks have an optional due date per exam (`exams.marks_due_on`).
- Exams belong to a term (`exams.term_id`).
- Report cards and grading scales come later.

### 3.7 Fees

**Built (backend, staged and committed in `8b52648`; no screens).**

- Money is integer paise (`BIGINT`). Never floats.
- The ledger is append-only per enrollment. Dues are positive, payments negative, a reversal is the opposite of the entry it cancels. The balance is the sum of the amounts.
- Entries are never changed or deleted. A mistake is reversed, once, with a reason. A reversal cannot itself be reversed. A reversed payment keeps its receipt.
- A due can be posted to one enrollment, to all active students of a section, or of a class. Students who have left are not charged.
- A payment is applied to the oldest due first. An excess is advance. A cheque, UPI or bank transfer needs a reference. A payment cannot be dated in the future.
- Receipts: `<RECEIPT_PREFIX>/<financial year>/<number>`, gapless per financial year (April to March), numbered from a locked counter inside the payment transaction.
- Idempotency: a payment carrying a key that was already used returns the first receipt and takes no money again. A key reused for a different payment is refused.
- Fee heads are unique by name.

**Planned.**

- Fee structures per class per year, with installment schedules.
- Fee screens (Finance area).
- Concessions, late fines, defaulters and receipt PDFs.
- Gateway payments (Razorpay) later; the gateway payment id is stored and unique.
- A `fee_balances` projection, only if measurement shows it is needed.

### 3.8 Staff

**Planned.** No code yet. Users are logins; a staff member is a person.

- A `staff` row for each non-student employee, with an optional login (`user_id`). A teacher must have a login to teach a subject (v4.0).
- Backfill when the table is created: one row per user with the Teacher, Principal, Admin or Accountant role. Guardian, Student and Super Admin accounts are not copied.
- Staff attendance is per day, in its own table (`staff_attendance`).
- Staff screens need `staff.view` and `staff.manage`. Both are declared but enforced nowhere today.

### 3.9 Performance metrics

**Planned.** Each metric is computed from source rows and stored in a projection that a queued job rebuilds after commit and a nightly check verifies. Projections are derived data: they can be deleted and rebuilt. All metrics use set-based queries (one statement for many students). The per-student score action is never used for term data; it takes about two minutes for 5,000 students.

**Student, per enrollment per term.**

- **Attendance percentage** = (present + late + 0.5 × half day) ÷ (present + late + half day + absent), one decimal. Leave and days with no record (holidays, weekly offs) are not counted.
- **Subject percentage** = average over the term's exams of mark ÷ maximum × 100, two decimals. Absent marks are left out.
- **Trend** = latest exam's percentage minus the previous exam's, in the same subject and term. Blank with fewer than two exams.
- **Overall term percentage** = average of the subject percentages (unweighted).
- **Section rank** (off by default; client setting): ranked among students who have a value; the rest are shown as unranked. Ties share a rank.
- **At-risk flag** (client settings `insights.at_risk.*`, §6; defaults 75, 35 and 15): true if attendance percentage < 75, or overall term percentage < 35, or any subject's trend ≤ −15 (a drop of 15 points or more between two exams).

**Teacher, per term.** Measure only what the teacher controls. Each measure shows its count, and anything the teacher is not responsible for is reported separately.

- **Attendance on time** = sheets the teacher submitted on time ÷ sheets they were responsible for. Responsible: each school day in the term up to today, for each section where they were class teacher on that day. Not a holiday or a weekly off day. Sheets saved before the submitter was recorded (D41) are reported as "submitter unknown" and are not counted against the class teacher (D51). On time: submitted by the teacher, on that day, at or before the cut-off (`attendance.cutoff_time`, default 10:00 local). Reported separately: sheets marked by someone else; days with no sheet.
- **Marks on time** = mark sheets the teacher entered by the due date ÷ mark sheets they were responsible for. Responsible: each exam with a due date in the term, for each section and subject they teach. Reported separately: sheets entered by someone else; sheets not entered.
- **Periods per week** = the teacher's periods on the timetable, breaks excluded, overrides included.
- **Own section averages**: shown on the teacher's own page, with no comparison to other sections. Principal and Admin see the comparison.
- **Own attendance**: days present ÷ days marked, from the teacher's own staff record (after Phase 3).

**Time and timezone.** Timestamps are stored in UTC. Every local comparison (cut-off, school days, due dates) converts to `school.timezone` first (default `Asia/Kolkata`).

**Who sees Insights.** Principal, Admin and Super Admin see everything. A class teacher sees their class-teacher sections. Teachers see their own numbers. Accountant sees none.

## 4. Architecture

### 4.1 Deployment and client lifecycle

- One codebase. Each client runs it as its own deployment, chosen at boot by `EDUNEXUS_CLIENT=<code>`. Never chosen from the hostname.
- A client has `deployments/<code>/<code>.env` (settings) and `<code>.env.secure` (secrets, mode 0600). Secrets are never committed here.
- Database and MySQL user: `edunexus_<code>`. Each user can reach its own database only.
- Storage: `storage/clients/<code>/`.
- Commands: `edunexus:client:create` (provisions, migrates, seeds, creates the first Super Admin; rolls back on failure), `edunexus:client:list`, `edunexus:client:migrate [--all] [--seed]`, `edunexus:client:drop` (local and testing only).
- The root `.env` is the development client (`EDUNEXUS_CLIENT_CODE=dev`, database `edunexus_dev`). There is no fallback from a missing client file to the root `.env`.
- Deploy order: migrate, then roll the client services. Migrations must stay backward compatible with the previous release.
- Local run: `docker compose up -d` in `docker/`, then `docker compose exec app php artisan migrate --seed`. Vite on port 5173; app on 8000.

### 4.2 Redis

Built for deployed clients. Local and tests use database and array drivers.

- Sessions, cache, queues, rate limiter and Spatie's permission cache live in one shared Redis.
- Every key starts with `edunexus:<code>:`. Each client has its own Redis user, limited to that prefix, with no dangerous commands (so `cache:clear` cannot wipe other clients). The access string is not yet checked against a real Redis.
- `cache:clear` for a deployed client forgets keys; it does not flush.

### 4.3 Queues and jobs

- Horizon runs each client's queues: `critical` (OTP, receipts), `default` (notifications, audits, recomputes), `bulk` (reminders, imports, PDFs). Three tries, 5 s then 30 s backoff, 60 s timeout.
- The Horizon dashboard opens only to a Super Admin outside local development.
- `AbsenceAlert` (R3) runs on `critical` after an attendance save commits, and sends the guardian notice through `GuardianNotifier` (§3.5). `SendGuardianOtp` (R7) sends guardian sign-in codes on `critical`. Audits are still written in the request; queued audits are planned (Phase 6).

### 4.4 Security and rate limits

- Sanctum: SPA sessions (cookie) and mobile tokens (bearer). Tokens never expire. A token's last-used time is written at most every 5 minutes.
- Sign-in: 5 attempts a minute per email and IP; 300 a minute per IP.
- Every API route: 120 a minute per signed-in user; 600 a minute per guest IP. A school's users share one IP, so per-account limits do the real work.
- Guardian phones are encrypted with the client's `APP_KEY`; the searchable value is a keyed hash. Other sensitive fields (Aadhaar, medical notes) are not built yet.
- Every URL in mail, messages and PDFs is built from the client's `APP_URL`.
- Tests refuse to run unless the database name ends in `_test` and the client prefix contains `test`.

### 4.5 Code layering and conventions

- Layers: controller → Form Request → Action → Eloquent. No repository layer.
- Business logic lives in Actions (`app/Domain/<Module>/Actions`). Controllers stay thin.
- Each domain module owns its models, actions, policies, events and enums under `app/Domain/`.
- Authorisation: `can:<permission>` route middleware, `Gate` calls, and policies (`UserPolicy`, `StudentPolicy`, `ExamPolicy`, `AttendancePolicy`). Only some Form Requests define `authorize()`.
- Module gate: `entitled:<module>` route middleware.
- Auditing: owen-it on personal and academic records (Student, Guardian, StudentEnrollment, User, ExamMark, AdmissionApplication, AttendanceRecord). Bulk writes use `BulkAudit`, because bulk queries fire no model events.
- Gapless numbers: `App\Support\Sequences` counters, locked `FOR UPDATE` inside the using transaction. Used for application numbers, admission numbers and receipts.
- Money: integer paise.
- Eloquent is strict outside production: lazy loading, reading an unselected attribute and filling a non-fillable attribute all throw.
- Every list endpoint has a query-count budget (`ListQueryCountTest`). Budgets are never raised to make a test pass; they are lowered when a change lowers the count.
- The SPA follows the Metronic-style design system in Tailwind v4 (tokens and component classes in `resources/css/app.css`).

### 4.6 SPA

- Vue 3, vue-router in history mode, served by the same deployment at every non-API path. Same-origin, so no CORS.
- Stores: `session` (user, permissions, modules), `academicYear` (selected year, sent as `X-Academic-Year`), `terms` (institution vocabulary), `subjectsAndTeachers` (subjects and teachers, loaded once and shared; `invalidate()` after a change).
- Shared components in `resources/js/components`: AppHeader, AppSidebar, AppIcon, AppLogo, UserMenu, AcademicYearSwitcher, PageHeader, AlertMessage, EmptyState, LoadingState, FormField, SearchField, SegmentedTabs, TablePagination, RoleBadge, InitialsAvatar, LoginCard, ConfirmDialog (every removal and every other destructive action asks first), Drawer (every form opens in a drawer; full width on phones), TeacherPicker (single: type a name; multiple: search and tick; compact: a select for dense grids), SectionPicker (sections grouped by class; optionally with enrolment counts), CheckboxChips (tick chips for roles, subjects and the like), AttendanceSheet (one section's sheet for a day, and for a session or period by mode; used by the Attendance screen and a classroom), MarksSheet (one classroom's marks for one exam, with publishing), ResultsTable (a classroom's academic and attendance scores).
- Shared composables in `resources/js/school/composables`: `useBusyNotice` (busy, success, and error state; `run()` for every request), `useListQuery` (search with a debounce, filters, paging), `useYearStructure` (the selected year's structure, reloaded when the year changes).
- Shared helpers: `school/academic.js` (year structure and section options), `school/weekdays.js` (the weekday lists), `school/navigation.js` (the one list of pages), `lib/format.js` (dates as "1 Jun 2026", date-times in the browser's zone, rupees), `lib/http.js`, `lib/errors.js`, `lib/money.js` (paise arithmetic).
- Pages do not copy pickers, dialogs, formatters or loading logic. A missing shared piece is built once in `resources/js/components` first.

### 4.7 Caching

- **Academic years** (`App\Support\AcademicYearContext`). What: the list of years, as plain attribute arrays (never Eloquent models), rebuilt into models for each request. Key: `academic_years:<sha1 of the academic_years column list>`, so adding a column changes the key and an old entry is never read. The column list is read once per request. TTL: one hour. Invalidation: saving or deleting a year forgets the key after the transaction commits, and making a year current does the same. The store is the client's Redis (§4.2).
- No other cache holds business data. Spatie's permission cache and the rate limiter use the framework's own keys.

## 5. Permissions and roles

Permissions are fixed in code (`App\Domain\Identity\Enums\Permission`). Roles hold them. Super Admin and Admin always hold every permission. Only a Super Admin can grant the Super Admin role.

| Permission | Enforced where | Held by default |
| --- | --- | --- |
| `user.manage` | UserPolicy | Super Admin, Admin, Principal |
| `academic.view` / `academic.manage` | route middleware | view: Teacher, Accountant, Principal, Admin, Super Admin; manage: Principal, Admin, Super Admin |
| `admission.manage` | route middleware | Principal, Admin, Super Admin |
| `student.view` | `visibleTo` | Accountant, Principal, Admin, Super Admin |
| `student.view_assigned` | `visibleTo` | Teacher |
| `student.manage` | student routes | Principal, Admin, Super Admin |
| `attendance.view` / `attendance.mark` | route middleware, AttendancePolicy (who may mark depends on `attendance.mode`, §3.5) | view: Teacher, Principal, Admin, Super Admin; mark: same |
| `attendance.edit_late` | AttendancePolicy | Principal, Admin, Super Admin |
| `exam.view` / `exam.enter_marks` | route middleware, ExamPolicy | view: Teacher, Principal, Admin, Super Admin; enter marks: Teacher, Principal, Admin, Super Admin |
| `exam.manage` | route middleware, ExamPolicy | Principal, Admin, Super Admin |
| `fee.view` / `fee.collect` / `fee.reverse` | route middleware | view, collect, reverse: Accountant, Principal, Admin, Super Admin |
| `settings.manage` | route middleware (`can:settings.manage`) on the settings endpoint | Admin, Super Admin |
| `role.manage`, `audit.view` | **nowhere yet** | Admin, Super Admin (and Principal for audit view) |
| `staff.view`, `staff.manage` | **nowhere yet** | Principal, Admin, Super Admin |
| `exam.publish`, `exam.unlock` | route middleware on the publish and unlock endpoints (R4) | Principal, Admin, Super Admin |
| `fee.concession_approve`, `communication.send`, `report.view` | **nowhere yet** | see the role table in code |

Guardian and Student roles hold no permissions. A guardian sees their linked children through `GuardianPolicy` (R7). Roles of clients created before a permission existed keep their old permissions until edited.

## 6. Client settings

Stored in the client's `settings` table and read through `OrganizationContext`. Changed by client admins on `/settings/client`.

**Endpoint (built, step 1.5).** `GET /api/v1/settings` returns every key with its `value`, `default`, `editable` and `used` (whether a screen or rule reads it yet). `PUT /api/v1/settings` takes the editable keys nested by group, the way the keys are written here: `{"attendance": {"cutoff_time": "09:30"}, "weekly_off_days": ["saturday", "sunday"]}`. Both need `settings.manage`. A key sent as `null` takes its default again. Unknown keys and read-only keys are refused with 422, naming the key (D17, D20). Keys marked *not yet used* are shown on the screen with that label.

| Key | Default | Set by | Used for | Status |
| --- | --- | --- | --- | --- |
| `academic_year.start_month` | June (school), July (college), April (coaching); `null` takes the institution type's month | Admin (`--year-start-month` at client creation) | the start of each year | built; editable |
| `attendance.mode` | `daily` (`daily`, `session` or `period`) | Admin | how attendance is marked (§3.5) | built; editable (R3) |
| `attendance.edit_window_days` | 0 | Admin | how far back a teacher can change attendance | built; editable |
| `attendance.cutoff_time` | `10:00` local | Admin | teacher on-time attendance (§3.9) | stored; not yet used |
| `results.scale` | 10 (integer 1–100) | Admin | the score out of which academic and attendance scores are shown (§3.6) | built; editable (R5) |
| `school.timezone` | `Asia/Kolkata` | Admin | local-time rules (§3.9) | stored; not yet used |
| `weekly_off_days` | `["sunday"]` | Admin | attendance refusal and school days | stored; not yet used |
| `insights.section_rank_enabled` | `false` | Admin | section rank (§3.9) | stored; not yet used |
| `insights.at_risk.attendance_below` | 75 | Admin | at-risk flag on the student profile | built; editable |
| `insights.at_risk.overall_below` | 35 | Admin | at-risk flag (§3.9) | stored; not yet used |
| `insights.at_risk.trend_drop` | 15 | Admin | at-risk flag (§3.9) | stored; not yet used |
| `institution.terminology` | from institution type | seeder | labels in the SPA | read-only here; the SPA reads it from the type, not the setting |
| `branding.logo_url`, `branding.theme` | none | operations | branding | read-only here; read by `/branding` |

Deployment settings (not client settings) come from the client's `.env`: `APP_URL`, `RECEIPT_PREFIX`, `EDUNEXUS_MODULES_ENABLE` and `_DISABLE`, mail and database settings, Redis settings, `EDUNEXUS_PRIVACY_NOTICE_VERSION`.

Constants in code, not settings: the 14-day window for the teacher home's marks-due list (`home.marks_due_window_days`).

## 7. Status and roadmap

One commit per step, full suite green before each. Commits and behaviour changes update this document in the same commit. Sizes: S ≤ half a day, M 1–2 days, L 3–5 days.

**Done**

| Step | What | Commit |
| --- | --- | --- |
| Platform | client lifecycle, env files, Redis drivers, institution types, Horizon, rate limits | `d910670`, `59ba917` and earlier |
| v3.2 step 1 | strict Eloquent, query-count budgets | `6214c51`, `f99ad7a` |
| v3.2 step 3 | academic year as context, copy-forward, switcher | `61c5e09` … `2b6142e` |
| v3.2 step 4 | subject teachers, timetable defaults, workload | `7918058` … `24ce6c1` |
| v3.2 step 5 (attendance) | daily attendance by section, holidays | `245d471` |
| v3.2 step 5 (fees, backend) | ledger, payments, receipts, reversals | `8b52648` |
| 0.0 | corrected the architecture document | `2061297` |
| 0.1 | attendance and holiday tests | `b66e53b` |
| 0.2 | fee ledger tests | `17b464e` |
| 0.3 | teacher visibility: set-based subquery and two indexes; tests for subject-teacher, timetable and year visibility | `ab44dfe` |
| 0.4 | exam list and detail show `has_marks` (boolean, `withExists`) in place of `marks_count`; the flag decides whether an exam can be deleted | `9010172` |
| 0.6 | leave is left out of the attendance percentage and days marked; release notes created (`docs/release-notes-v4.md`) | `f1ad0c2` |
| 1.0 | shared SPA pieces (confirm dialog, drawer, teacher and section pickers, checkbox chips, subjects and teachers store, busy and list and year composables, date and rupee formatters); every page moved onto them; forms in drawers, removals confirmed | `3776738` |
| 1.1 | navigation grouped into the six areas; old paths redirect | `43ac064` (with 1.2–1.5, D19) |
| 1.2 | role homes: Teacher and Principal | `43ac064` (with 1.1, 1.3–1.5, D19) |
| 1.3 | Year Setup status endpoint and checklist page | `43ac064` (with 1.1, 1.2, 1.4, 1.5, D19) |
| 1.4 | edit screens for classes, sections and periods | `43ac064` (with 1.1–1.3, 1.5, D19) |
| 1.5 | client settings endpoint and screen (§6); the at-risk attendance threshold comes from the setting | `43ac064` (with 1.1–1.4, D19) |
| R1 | client settings: `attendance.mode` and `results.scale` added to the endpoint and screen; §6 and §8 brought up to date | this commit |

**Classroom rework (owner brief, R1–R9).** These phases replace the remaining Phase 1 to 7 items they name. R1–R8 were committed without tests (§10 override); R9 owes the tests.

| Step | What | Status |
| --- | --- | --- |
| R1 | client settings (above) | this commit |
| R2 | classroom setup: create classrooms, distribute students, teacher grid per class, Classrooms page (Students, Teachers, Attendance, Exams & marks, Results tabs); timetable becomes optional outside period mode | this commit (Exams & marks and Results are empty until R4 and R5; R9 owes the tests) |
| R3 | attendance modes: daily, session, period; `periods.session`; AttendancePolicy by mode; percentage in units (replaces Q1); absence alerts through `GuardianNotifier` | this commit (no tests; R9 owes them) |
| R4 | marks entry per classroom (step 2.4): `exam_mark_sheets`, bulk save, publish and unlock | this commit (no tests; R9 owes them) |
| R5 | scale of 10 for academic and attendance scores, shown per subject and overall (replaces Q9); Q19 | this commit (no tests; R9 owes them) |
| R6 | teacher home "My classrooms" | this commit (no tests; R9 owes them) |
| R7 | guardian login by OTP, and guardian endpoints scoped to linked children | this commit (no tests; R9 owes them) |
| R8 | navigation: Classrooms under People; old section pages route to the classroom page; final doc pass | not started |
| R9 | tests and query-count budgets for R1–R8 and for the changes since (D41–D48). Includes bringing `students/{id}/scores` back within its existing budget (D48); the budget is not raised. Also the tests that assume the old rules: the R2 distribution, the score shape, and an admission that requires a section | owed |

**Remaining, in order**

| Step | What | Size | Status |
| --- | --- | --- | --- |
| 0.5 | the `attendance_records.section_id` and `academic_year_id` snapshot columns, and their backfill (the `attendance_sheets` table is done, D41) | M | waiting on owner answer Q14; not started |
| 0.7 | make `student_enrollments.class_id` required | S | after every client is migrated (D10, D45, D52) |
| 1.6 | branding ETag | S | not started |
| 2.1 | `terms` table, CRUD, defaults per type, admin confirmation before exams | M | not started |
| 2.2 | `exams.term_id`, `exams.marks_due_on`, backfill, validation | S | not started |
| 2.3 | exams list, create and edit screens | M | not started |
| 2.4 | marks entry screen with bulk upsert, bulk audit, `exam_mark_sheets` | M | delivered by R4 |
| 2.5 | results per exam, per class | M | delivered by R5 (per classroom, for the year) |
| 2.6 | audit `Exam` and `ExamPaper` | S | not started |
| 3.1 | `staff` table, backfill, StaffController, People → Staff | M | not started |
| 3.2 | `staff_attendance` and the per-day marking screen | M | not started |
| 3.3 | enforce `staff.view` and `staff.manage` | S | not started |
| 4.1 | fee structures, heads and class amounts | M | not started |
| 4.2 | dues, collection and receipts screens | M | not started |
| 4.3 | defaulters report and receipt PDF | M | not started |
| 4.4 | gateway payments (Razorpay) | M | later (Q13) |
| 5.1 | class-teacher assignment history; term scores and summaries with recompute jobs and nightly check | L | not started |
| 5.2 | student performance on the profile, term scores beside the lifetime score | M | not started |
| 5.3 | class performance screen | M | not started |
| 5.4 | teacher performance screen (own numbers); re-measure | M | not started |
| 6.1 | caching for settings, terminology and `/me`, after role editing | M | not started |
| 6.2 | queued audits, if measured | M | not started |
| 6.3 | cursor pagination, if measured | M | not started |
| 6.4 | Octane readiness: Spatie reset listener and two-requests test | S | not started |
| 6.5 | `LoadTestSeeder` and k6 scripts | M | not started |
| 7.1 | report cards | L | not started |
| 7.2 | guardian login and mobile app | L | not started |
| 7.3 | promotion and transfer certificates | M | not started |

**Deferred from the earlier plan, with reasons.**

- Teacher section-ID cache (v3.2 step 7.4): not built. Caching across requests needs invalidation, and three bulk deletes must be converted to model events first (`TimetableController::clear`, the clear branch of `AssignSubjectTeacher`, `SyncClassSubjects`). Lands in Phase 6.
- Attendance already saves a sheet in one upsert. The same bulk write for marks is step 2.4.

## 8. Decisions

Owner decisions are answers from the product owner. "Technical" decisions were made here, following existing conventions.

| # | Decision | Kind |
| --- | --- | --- |
| D1 | Terms are a table per year, not settings | technical |
| D2 | Summary projections are keyed by enrollment | technical |
| D3 | Staff attendance is a separate table; the anchor is a person | technical |
| D4 | Section subject averages are derived on read, not stored | technical |
| D5 | Projections are recomputed in queued jobs after commit | technical |
| D6 | Attendance rows snapshot the section (moves keep history) | technical |
| D7 | `ConsolidateSubjectScores` keeps its lifetime mode for the single-student API; term data uses set-based queries | technical |
| D8 | Audit `Exam` and `ExamPaper` | technical |
| D9 | Fee money is integer paise | technical |
| D10 | Migrations are additive and backfilled in one idempotent migration; old and new code must both run during rollout | technical |
| D11 | `academic_year_id` on attendance rows, backfilled, set on write | technical (review) |
| D12 | `attendance_sheets` records submitter and first-save time | technical (review) |
| D13 | Terms and weekly offs as client settings (§6) | owner |
| D14 | `class_teacher_assignments` records changes of class teacher, with dates. `sections.class_teacher_id` stays the current value; one action writes both | technical (accepted) |
| D15 | Term defaults: even split by months; the terms step is confirmed by an admin before exams can be created that year | owner (accepted) |
| D16 | Insights: Principal, Admin and Super Admin see everything; class teachers see their class-teacher sections; teachers see their own numbers; Accountant sees none | owner |
| D17 | Settings are written nested by group (`{"attendance": {"cutoff_time": …}}`) and read flat by key. Laravel expands dotted keys when it builds validated input, so a flat body would be silently dropped | technical |
| D18 | `attendance.mode` (daily, session, period; default daily) and `results.scale` (integer 1–100; default 10). Changing the mode does not rewrite records already saved | technical |
| D19 | Steps 1.1–1.5 were committed together in `43ac064`, not one commit per step, because the working tree held them together when the owner committed it. Their rows in §7 point at that commit | technical (recorded) |
| D20 | An unknown key in a settings request is refused (422), not ignored | technical |
| D21 | A class's students for a year are those enrolled last year in a class of the same name and not yet enrolled this year. **Superseded by D45 (Q22).** | technical (R2; superseded) |
| D22 | The teacher grid is one request, GET and PUT per class. Errors are keyed by row and column (`sections.{i}.subjects.{j}.teacher_id`), and the whole save is refused when any cell fails | technical |
| D23 | The Exams & marks and Results tabs of a classroom are empty states until R4 and R5 build them, rather than hidden | technical |
| D24 | The attendance summary counts units (one record each: a day, a session or a period). Its field names `days_marked` and `days_attended` are kept for the apps that read them | technical (R3) |
| D25 | A session's attendance is stored against its first teaching period by sort order, so a session needs no row of its own. The stored period is always a period of the section's year | technical (R3) |
| D26 | Absence notices are a queued job after the save commits, sent through a `GuardianNotifier` interface. The default driver writes to the log and sends nothing, so no client is messaged by accident | technical (R3) |
| D27 | WhatsApp is a driver configured per client in `.env`, not a setting, because its credentials are secrets | technical (R3) |
| D28 | Half day is refused in session mode only, as the brief asks. Period mode accepts it | owner (R3 brief) |
| D29 | A mark sheet is submitted by any save with a mark or an absence. The first submission sets `submitted_at`; later saves change only `entered_by` | technical (R4) |
| D30 | Bulk marks saves are audited with BulkAudit. New marks need their ids, so their creation is audited after one read-back of those ids; that is one more read than the attendance save makes. Audit rows follow the order of the entries | technical (R4) |
| D31 | Publishing recorded when, not who (R4). **Revised by D46:** `exams.published_by` now records who published too. Audited exam changes still come with step 2.6 | technical (R4; revised) |
| D32 | A published exam's maximum marks are locked with its marks, in both the exam update and the per-class maximum | technical (R4) |
| D33 | A classroom's results cover its year. The student's score (`students/{id}/scores`) stays lifetime, as §3.6 says. The two can differ for a student with several years | technical (R5) |
| D34 | The overall academic score averages all exam percentages, every exam counting equally, not the subject scores. It matches the rule in §3.6 | technical (R5) |
| D35 | `students/{id}/scores` returns an object: `scale`, `academic` (overall and subjects) and `attendance`, side by side. Its previous list shape is gone. The existing score tests assert the old shape and are owed in R9 | technical (R5) |
| D36 | Marks due on the teacher's home are exams not yet published that end within `home.marks_due_window_days` (14, §6) or have no end date. There is no due date field yet (`exams.marks_due_on`, step 2.2) | technical (R6) |
| D37 | A guardian's login is created on first sign-in, with no email and an unusable password, and linked to the guardian record. `users.email` becomes nullable for it. Guardians sign in only with codes | technical (R7) |
| D38 | The sign-in code travels in the queued job's payload for at most its few seconds in the queue. It is never logged outside local and testing, and the log driver says a code was requested, not what it was. Only a keyed hash of the code is stored | technical (R7) |
| D39 | Guardian sign-in is limited per phone and IP (3 a minute) with a separate limiter, not the email limiter sign-in uses, because a guardian signs in by phone | technical (R7) |
| D41 | Owner: a subject teacher of the section may also mark its daily sheet, not only the class teacher. The sheet records who submitted it (`attendance_sheets`: section, date, period, submitted by, submitted at; the first save sets both, later saves do not change them). §3.5 and §5 are updated; the step 0.5 snapshot columns and backfill stay blocked on Q14 | owner (Q-answer, daily marking) |
| D42 | Owner (Q19): academic and attendance scores stay separate in v4. The parent app shows the year's scores (`guardian/children/{id}/scores`, year scope). The lifetime scores stay on the staff-facing profile (`students/{id}/scores`) | owner (Q19; parent app) |
| D43 | Owner (Q20): Admin and Super Admin get an office home. It shows Year Setup progress, classrooms without a class teacher, pending admissions, and fee collection today once Finance exists. Principal keeps the leadership home. Endpoint `home/office`, permission checked per block | owner (Q20) |
| D44 | Owner (Q21): each academic year has an admissions-open switch (`academic_years.admissions_open`, default off). The Year Setup step "Admissions open" is done when it is on. Admins set it. It does not block applications yet | owner (Q21) |
| D45 | Owner (Q22): `student_enrollments.class_id` (backfilled from the section for existing rows). `section_id` is nullable until distribution. Admission records the application's class; a section is optional at admission. Returning students move to the next class by sort order (the previous year's class with the next lower sort order), when that class is distributed. Distribution fills sections from enrolments that have a class and no section. The R2 rule placed returning students in the same class; this replaces it | owner (Q22) |
| D46 | Publishing records who published as well as when (`exams.published_by`). This revises D31; the audit of exam changes still waits for step 2.6 | owner (product, via the technical change list) |
| D47 | Guardian code requests are limited per phone hash (3 a minute and 10 a day) and per IP (20 a minute), as separate limits. Checking a code is not counted against the daily cap. In production, a request is refused with 503 and a clear message when no working notifier is configured (the log driver sends nothing, so it does not count) | technical (owner-requested) |
| D48 | `students/{id}/scores` must stay within its existing query budget. Its budget is not raised. Done in R9: the marks query joins the enrolment's class and the score code loads only the columns it reads, so the endpoint is back within 13 | technical (owner-requested, R9) |
| D49 | Owner (final class): students in the final class (highest sort order) of a year that closes end with status `left` when they are not enrolled in the year that starts. The change happens when a year becomes current. Status `left` is used because there is no `completed` status | owner (answer to the final-class question) |
| D50 | Owner (admissions switch): when a year's admissions are off, new applications are refused. Existing applications can still be decided and admitted | owner (answer to the admissions-switch question) |
| D51 | Owner (sheets without a submitter): a sheet saved before the submitter was recorded is reported as "submitter unknown" on the teacher on-time measures (§3.9), and is not counted against the class teacher | owner (answer to the please-check list) |
| D52 | Owner: keep the `attendance_sheets` table. Step 0.5 is now only the snapshot columns and their backfill. Making `student_enrollments.class_id` required is a roadmap step (0.7), after every client is migrated | owner (answer to the please-check list) |
| D40 | No redirects were needed in R8: the only section-level page is the classroom page, which replaces no earlier page. The old section cards stay on the Academic page, linked to the class page | technical (R8) |
| Q1 | Attendance is per day, per session or per period, chosen by `attendance.mode`. The `period` column holds 0 for the day or the period's id (R3). Replaces the earlier per-day answer | owner (R3 brief) |
| Q2 | Leave is excluded from the attendance percentage; release note | owner |
| Q3 | Late counts 1, half day 0.5 | owner |
| Q4 | Teachers see their own numbers only, no peer ranking | owner (T1 confirmed: own sections' averages only) |
| Q5 | Section rank is a client setting, off by default | owner |
| Q6 | At-risk: attendance < 75, overall < 35, trend drop ≥ 15 | owner |
| Q7 | Terms: school 3, college 2 semesters, coaching 1 | owner |
| Q8 | Insights visibility | owner (see D16) |
| Q9 | The lifetime score out of the client's scale (`results.scale`, default 10) stays next to the term score. Academic and attendance scores are kept apart. Replaces the out-of-5 answer | owner (R5 brief) |
| Q10 | A teacher needs a login for v4.0 | owner |
| Q11 | Optional `marks_due_on`; attendance cut-off 10:00 local; `school.timezone` Asia/Kolkata; timestamps stored in UTC | owner |
| Q12 | No Sunday holidays; weekly off days setting, default Sunday | owner |
| Q13 | Razorpay later | owner |
| Q18 | No student logins in v4 | owner (answered; §9) |
| Q19 | Academic and attendance scores stay separate in v4 (D42) | owner (answered; §9) |
| Q14 | Which clients hold live data | **open.** The round-4 reply was the placeholder "[none / list codes]", not an answer. Step 0.5 stays blocked. |
| Q15 | Staff attendance per day | owner |
| Q16 | Substitutions later | owner |
| T1 | Teachers see their own sections' averages only | owner (confirmed) |
| T2 | Class-teacher history accepted (D14) | owner (confirmed) |
| T3 | Term defaults accepted (D15) | owner (confirmed) |
| T4 | Rule F (§10) | owner (confirmed) |
| T5 | Release notes live in `docs/release-notes-v4.md`, created in the leave-counting commit | owner (confirmed) |
| T6 | Classroom rework R1–R8 is committed without tests and without running the suite; tests are owed in R9 (§10) | owner |

## 9. Open questions

**Open**

1. **Q14: which clients hold live data today?** Blocks step 0.5 (its snapshot columns and backfill) and the migration rehearsal. Reply with client codes, or "none".

**Answered**

- **Q17 (R7):** guardians sign in with a code sent to the phone on file, and see the attendance, published marks and year scores of their linked children (§3.4). Dues come with Finance.
- **Q18 (product answer):** no student logins in v4. The student app (step 7.2) is not blocked by anything in v4; it is simply out of scope.
- **Q19 (product answer):** academic and attendance scores stay separate in v4. No overall score combines them (§3.6, D42).
- **Q20 (product answer):** Admin and Super Admin get an office home: Year Setup progress, classrooms without a class teacher, pending admissions, and fee collection today once Finance exists (§2.4, D43).
- **Q21 (product answer):** each academic year has an admissions-open switch that admins set. The Year Setup step "Admissions open" is done when the switch is on (§2.3, D44).
- **Q22 (product answer):** an enrolment carries its class (`student_enrollments.class_id`). `section_id` is empty until the student is distributed. Admitted applications carry their class; returning students move to the next class by sort order; distribution fills sections from enrolments that have a class and no section (§3.4, D45). The earlier code (R2) placed returning students in the **same** class, matched by name, not the next class. That is corrected by the step that delivers D45.

## 10. Working rules

- `docs/EDUNEXUS.md` is the only specification. Do not create other documents, analysis files or specs. Anything that needs writing down goes here.
- Every commit that changes behaviour updates this document in the same commit: status, rules, data model and settings.
- One commit per roadmap step, with the full test suite green before each.
- Every new screen uses the shared components and the design system. No page-specific copies of pickers, dialogs, formatters or loading logic. If a shared piece is missing, build it once in `resources/js/components` first.
- Every new endpoint gets a feature test and a query-count budget. Budgets are never raised to make a test pass.
- Probes and tests use existing credentials only. Never create MySQL or Redis users, grant privileges, or use root or provisioning credentials outside the `edunexus:client:*` commands.
- Ask only when a decision is a product choice. Make technical decisions yourself, following the existing conventions, and record them in §8.
- `AGENTS.md`'s install step is already done: `laravel/boost` is a dependency. Do not run `composer require` for it again. This follows the rule above on dependency changes.
- **Override for the classroom rework.** Classroom rework phases R1–R8 were committed without tests and without running the test suite; tests are owed in R9 (§7, §8 T6). No existing test or query-count budget is edited or deleted in those phases. The other rules above still apply to them: docs in the same commit, one commit per phase, shared components only.

## 11. Data model

Conventions: every table has `id` and timestamps unless noted. Foreign keys are restrictive unless the column says otherwise. Enrollment is the anchor for student data; the academic year is the context.

**Identity and platform (built)**

| Table | Key columns and rules |
| --- | --- |
| `users` | name, email (unique; nullable for a guardian's login, R7), password, remember token, `subject_id` (legacy: one subject, kept in step with `subject_teacher` until a later release; planned for removal) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie permission tables; role name unique per guard |
| `personal_access_tokens` | Sanctum tokens, unique token; no expiry |
| `settings` | key (unique), value (JSON) — client settings (§6) |
| `audits` | owen-it audit rows: auditable, event, old and new values, user, IP, URL, time |
| `sequences` | key (unique), next value — gapless counters, locked on use |
| `guardians` | name, phone (encrypted), phone hash (indexed), email, `user_id` (unique, nullable: the guardian's login, R7) |
| `guardian_otps` (R7) | guardian_id, phone_hash (indexed), code_hash (keyed hash), expires_at, attempts, consumed_at; codes are never stored in clear |

**Academic (built)**

| Table | Key columns and rules |
| --- | --- |
| `academic_years` | name (unique), starts_on, ends_on, is_current (one at a time), admissions_open (default off; D44) |
| `classes` | academic_year_id, name, sort_order; unique (year, name) |
| `sections` | class_id, name, capacity, class_teacher_id (nullable, → users); unique (class, name) |
| `subjects` | code (unique), name |
| `class_subject` | class_id, subject_id; primary key on both |
| `periods` | academic_year_id, name, starts_at, ends_at, is_break, sort_order, session (nullable: morning or afternoon, R3); unique (year, name) |
| `timetable_entries` | section_id, weekday, period_id, subject_id, teacher_id (nullable), is_override; unique (section, weekday, period); unique (teacher, weekday, period); index (teacher, section) |
| `section_subject_teachers` | section_id, subject_id, teacher_id; unique (section, subject); index (teacher, section) |
| `subject_teacher` | subject_id, user_id; primary key on both; index (user) |
| `holidays` | academic_year_id, date, name; unique (year, date) |

**Students and admissions (built)**

| Table | Key columns and rules |
| --- | --- |
| `students` | admission_no (unique), name (indexed), date_of_birth, gender, photo_path |
| `guardian_student` | guardian_id, student_id, relation, is_primary; primary key on both; both cascade |
| `student_enrollments` | student_id, academic_year_id, class_id (nullable for this release, D45), section_id (nullable until distributed), roll_no, status; unique (student, year); unique (section, roll_no); index (year, status) |
| `guardian_consents` | guardian_id, student_id, notice_version, method, consented_at, recorded_by, IP, withdrawn_at |
| `admission_applications` | application_no (unique), academic_year_id, class_id, status, student name, guardian fields, guardian phone hash (indexed), decided_by, student_id (unique) |

**Attendance (built)**

| Table | Key columns and rules |
| --- | --- |
| `attendance_sheets` (D41) | section_id, date, period (0 = whole day), submitted_by, submitted_at (set by the first save); unique (section, date, period). Step 0.5's snapshot columns and backfill are not part of it |
| `attendance_records` | enrollment_id, date, period (0 = whole day; otherwise the `periods.id` of the session or period, R3), status (varchar 20; the enum is in code), remark, marked_by; unique (enrollment, date, period); index (date, status). Planned additions: `section_id`, `academic_year_id` (step 0.5) |

**Examinations (built)**

| Table | Key columns and rules |
| --- | --- |
| `exams` | academic_year_id, name (unique per year), max_marks, starts_on, ends_on, published_at and published_by (R4, D46; null until published). Planned: `term_id`, `marks_due_on` |
| `exam_papers` | exam_id, class_id, subject_id, max_marks; unique (exam, class, subject) |
| `exam_marks` | exam_id, enrollment_id, subject_id, marks (decimal 5,2, null when absent), is_absent, recorded_by; unique (exam, enrollment, subject) |
| `exam_mark_sheets` (R4) | exam_id, section_id, subject_id, entered_by (nullable), submitted_at (first submission); unique (exam, section, subject) |

**Fees (built, backend)**

| Table | Key columns and rules |
| --- | --- |
| `fee_heads` | name (unique) |
| `fee_receipts` | receipt_no (unique), financial_year, enrollment_id, amount (unsigned BIGINT, paise), method, reference, paid_on, idempotency_key (unique), gateway_payment_id (unique), received_by |
| `fee_entries` | enrollment_id, type (due, payment, reversal), fee_head_id, amount (signed BIGINT, paise), due_on, description, receipt_id, reverses_id (unique), recorded_by, created_at only; index (enrollment, type). Never updated or deleted |

**Planned**

| Table | Purpose | Phase |
| --- | --- | --- |
| `class_teacher_assignments` | section, teacher, from and to dates | 5.1 |
| `terms` | per year: name, sequence, starts_on, ends_on; unique (year, sequence) | 2.1 |
| `staff` | person; optional `user_id` (unique); employee code; name; phone (encrypted, hashed); designation; department; joined and left dates | 3.1 |
| `staff_attendance` | staff, date, status, remark, marked_by; unique (staff, date) | 3.2 |
| `student_subject_term_scores` | enrollment, term, subject, section, exams count, percentage, trend, recomputed time; unique (enrollment, term, subject) | 5.1 |
| `student_term_summaries` | enrollment, term, attendance percentage, days marked, overall percentage, section rank, at-risk, recomputed time; unique (enrollment, term) | 5.1 |
| `teacher_term_summaries` | teacher, term, on-time percentages and counts, periods per week, own attendance, recomputed time; unique (teacher, term) | 5.4 |
| `substitutions` | later (Q16) | later |

## Appendix: built routes

All under `/api/v1`. `entitled` is the module gate; `can` the permission. Full list: `php artisan route:list --path=api`.

| Area | Routes |
| --- | --- |
| Auth | `auth/login`, `auth/logout`, `auth/tokens`, `auth/tokens/current`, `me`, `branding` |
| Users and roles | `users` (list, create), `users/{id}/roles`, `users/{id}/subjects`, `roles` (read) |
| Academic | `academic-years` (list, show, `next`, `{id}/make-current`), `academic-years/{id}/classes`, `classes/{id}` (update, delete, subjects, subject teacher), `sections` (show, store, update, delete, subject teacher), `subjects` (list, create, update, teachers), `academic-years/{id}/periods` (list, create), `periods/{id}` (update, delete), `sections/{id}/timetable` (show, set, clear), `me/timetable`, `me/teaching`, `teachers`, `teachers/workload`, `academic-years/{id}/holidays` (list, create), `holidays/{id}` (delete), `academic-years/{id}/setup-status`, `academic-years/{id}/admissions` (set open or closed; `academic.manage`), `academic-years/{id}/make-current-preview` (read only; the confirm dialog's count), `classes/{id}/teacher-grid` (show, save), `classes/{id}/classrooms` (create) |
| Students | `students` (list, create, show, update), `students/{id}/guardians` (add, remove), `students/{id}/enrollments`, `enrollments/{id}`, `students/{id}/attendance`, `students/{id}/scores`, `students/{id}/fees`, `classes/{id}/students/distribute` |
| Admissions | `admissions` (list, create, show, update), `admissions/{id}/decision`, `admissions/{id}/admit` |
| Attendance | `sections/{id}/attendance` (show with `date` and `period_id`; save with `date`, `period_id` and `entries`), `attendance/day-summary` |
| Examinations | `academic-years/{id}/exams` (list, create), `exams/{id}` (show, update, delete), `exams/{id}/classes/{class}/subjects/{subject}/max-marks`, `exams/{id}/marks` (show, save), `exams/{id}/sections/{section}/mark-sheets` (subject status), `exams/{id}/publish`, `exams/{id}/unlock`, `me/classrooms` (the teacher's classrooms and today's due attendance and marks), `sections/{section}/results` (academic and attendance scores for the year), `students/{id}/scores` (academic and attendance scores, lifetime) |
| Fees | `fee-heads` (list, create), `fee-dues` (create), `enrollments/{id}/payments` (collect), `fee-entries/{id}/reverse`, `receipts/{id}`, `sections/{id}/fees` |
| Settings | `settings` (show, save; `settings.manage`) |
| Office home (D43) | `home/office` (Year Setup progress, classrooms without a class teacher, pending admissions) |
| Guardian (R7) | `guardian/otp` (send a code), `guardian/otp/verify` (sign in), `guardian/children`, `guardian/children/{student}/attendance`, `guardian/children/{student}/marks`, `guardian/children/{student}/scores` (each child checked by GuardianPolicy) |
