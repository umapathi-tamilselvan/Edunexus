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
| Admin | office staff | Setup, people, admissions, fees. Holds every permission. |
| Principal | head of the institution | Oversight, approvals, insights. Every permission except role and settings management. |
| Teacher | class teachers and subject teachers | Marks attendance for their sections, enters marks for the subjects they teach, sees their own timetable and their students. |
| Accountant | fee office | Collects payments, issues receipts, reverses entries, sees students and fee balances. |
| Guardian | parent | Mobile app: child's attendance, marks and dues. **Planned.** Login does not work yet. |
| Student | learner | **Planned.** |

Every role's permissions are in §5 (RBAC). Roles are seeded per institution type; client edits to role permissions are **planned** (no write endpoint yet).

## 2. Product

### 2.1 Areas

Navigation is grouped by what a role does each day. Routes keep their paths where a page exists.

| Area | Screens |
| --- | --- |
| Home | Home per role (below) |
| Daily | Attendance, My timetable, Substitutions (later), Staff attendance (planned) |
| People | Students, Student profile, Staff (planned), Guardians (partial), Admissions |
| Assessment | Exams, Marks entry, Results, Terms (planned), Report cards (later) |
| Finance | Fee structures, Dues, Collection, Receipts, Defaulters (planned) |
| Insights | Student performance, Class performance, Teacher performance (planned) |
| Settings | Year Setup, Classes and sections, Subjects, Bell schedule, Roles, Users, Client settings |

### 2.2 Screens

For each screen: who sees it, what they do, status.

| Screen | Who | What the user does | Status |
| --- | --- | --- | --- |
| Sign in `/login` | everyone | signs in | built |
| Dashboard `/dashboard` | any signed-in user | sees counts of roles, permissions and modules; quick links | partial (generic; no role homes) |
| Attendance `/attendance` | `attendance.view` | picks a section and a day; sets present, absent, late, half day or leave with a remark per student; saves | built |
| My timetable `/timetable` (My timetable tab) | any teacher | views their week | built |
| Timetable `/timetable` | `academic.view` | sets or clears a slot; sets an override teacher; adds periods | built |
| Students `/students` | `student.view` or `student.view_assigned` | searches; filters by class and section; adds a student with guardians and a section | built |
| Student profile `/students/{id}` | as above | edits details; links or unlinks guardians; enrols or moves section; sees attendance and lifetime score | built |
| Admissions `/admissions` | `admission.manage` | records enquiries; approves, rejects or withdraws; admits with recorded consent | built |
| Academic setup `/academic` | `academic.view` (changes need `academic.manage`) | years (make current, open next year); classes; sections with class teacher; class subjects; holidays | built |
| Subjects `/subjects` | `academic.view` | adds and edits subjects; picks teachers for each | built |
| Subject teachers `/subject-teachers` | `academic.view` | assigns teachers per section or per class; workload tab | built |
| Users `/users` | `user.manage` | creates users; sets roles and subjects a teacher teaches | built |
| Exams, Marks entry, Results | `exam.view`, `exam.manage`, `exam.enter_marks` | exam list; marks sheet; results | not built (API only) |
| Fee screens | `fee.view`, `fee.collect`, `fee.reverse` | dues, collection, receipts, defaulters | not built (backend staged and committed; no screen) |
| Staff, Staff attendance | `staff.view`, `staff.manage` | staff list; daily staff attendance | planned |
| Terms | `academic.view` | term names and dates per year | planned |
| Insights (three screens) | per §5.9 | performance views | planned |
| Year Setup | `academic.manage` | checklist (§2.3) | partial (see §2.3) |
| Client settings | `settings.manage` | values in §6 | planned (no endpoint) |
| Roles | `role.manage` | permissions per role | planned (read-only today) |

### 2.3 Year Setup checklist

Each step shows done, or what is missing. A step never blocks another: an admin can open the next year while steps are open.

| # | Step | Done when | Status |
| --- | --- | --- | --- |
| 1 | Open the year | the year exists and is current or open | built |
| 2 | Terms | the year has terms, and an admin has confirmed their dates | planned |
| 3 | Classes and sections | every class has a section, with capacity | built (data); status view planned |
| 4 | Subjects per class | every class has a subject | built (data); status view planned |
| 5 | Class teachers | every section has a class teacher | built (data); status view planned |
| 6 | Subject teachers | every section subject has a teacher, no teacher over workload | built (data); status view planned |
| 7 | Timetable | every section has a timetable | built (data); status view planned |
| 8 | Bell schedule | the year has periods and a teaching period | built (data); status view planned |
| 9 | Holidays and weekly off days | holidays added; weekly off days set (default Sunday) | holidays built; weekly off planned |
| 10 | Open admissions | an admissions round is open | built (data); status view planned |

The status endpoint and the checklist page are **planned** (Phase 1).

### 2.4 Home per role

Each role gets its own home. **Planned** except the generic dashboard.

| Role | Shows |
| --- | --- |
| Teacher | today's attendance sheets they are responsible for; their timetable for today; marks due in the next 14 days (a fixed constant) |
| Principal | today's attendance by section, with sheets not yet submitted; admissions by status; fee collection today (after Finance) |
| Admin | Year Setup progress; open admissions |
| Accountant | collections today; defaulters |
| Guardian (mobile) | each child's attendance, marks and dues |

## 3. Business rules by module

### 3.1 Academic years and terms

- Exactly one academic year is current. A year starts on the 1st of the institution's start month (`academic_year.start_month`) and is named after it (`2026-27`).
- Years never overlap. A new year must start after the year before it ends.
- `POST academic-years/next` opens the year after the current one and copies classes, sections with their capacities, class subjects and the bell schedule. Class teachers, subject teachers and the timetable are not copied. Only one year can be open ahead of the current one. It does not become current until `make-current`.
- Years are never edited or created by hand. Their names and dates are not patched.
- A request works in the year named by the `X-Academic-Year` header, else the current year. An unknown year is a 422.
- **Terms (planned).** Each year has terms: school 3, college 2 semesters, coaching one "Full year" term. Names and dates are editable per client. Dates default to an even split by months. The terms step of Year Setup must be confirmed by an admin before exams can be created in that year.

### 3.2 Classes, sections and subjects

- Class names are unique within a year; section names within a class.
- A class with sections, or a section with students, cannot be deleted. A section's capacity cannot drop below its enrolled count.
- Subjects are shared across years. Each class offers a set of subjects.
- Taking a subject off a class also removes its subject teachers in that class's sections.

### 3.3 Subject teachers and timetable

- A teacher can teach one or more subjects (`subject_teacher`).
- A section has one teacher per subject the class studies (`section_subject_teachers`, unique on section and subject). The teacher must teach that subject.
- A class-level assignment (`PUT classes/{id}/subjects/{subject}/teacher`) sets the same teacher on every section of the class. Sections keep their own assignments afterwards.
- A subject cannot be taken away from a teacher who teaches it to sections in the current year or later. Earlier years are history.
- Each year has a bell schedule of periods; some are breaks. A period in use cannot be deleted or made a break.
- Each section has a weekly grid of slots: weekday and period, with a subject and an optional teacher.
- A slot given no teacher gets the section's subject teacher, and follows it when that changes. A slot given someone else is an **override** (`is_override`) and stays put. If the new subject teacher is busy in a following slot, the change is refused with the clash named.
- A teacher cannot be in two sections in the same weekday and period. Enforced by a unique index.
- A teacher's own week is `me/timetable`; the teachers list with workload is `teachers/workload`.

### 3.4 Students and admissions

- A student exists once. Each academic year they have one enrollment: section, roll number and status. Attendance, marks and fees hang off the enrollment, not the student.
- One enrollment per student per year. Roll numbers are unique per section and default to the next free number. Capacity is enforced under a lock.
- An enrollment can move to another section of the same year only. Changing years is promotion (planned).
- Each student has at least one guardian and exactly one primary guardian. Guardian phones are normalised (digits only), encrypted, and matched by a keyed hash, so siblings share one guardian record. Guardian phones are masked for anyone without `student.manage`.
- **Admissions.** An application moves enquiry → approved → admitted, or to rejected (reason required) or withdrawn. Closed applications cannot change. Admitting runs in one transaction: it locks the application, checks the section belongs to the class applied for, creates the student, links or reuses the guardian, enrols, and writes a consent row. Consent is mandatory: the admitting user confirms the guardian saw the privacy notice and records how consent was given (signed form, in person, digital). The notice version is stored.
- Application numbers are gapless per year (`2026-27/0001`). Admission numbers default to `<CODE><00001>`, skip numbers typed by hand, and are returned if the admission rolls back.

### 3.5 Attendance

**Built.**

- Attendance is taken per school day, per section. The sheet lists the section's students in roll order with each one's status: present, absent, late, half day or leave; and an optional remark.
- A sheet is saved in one write: one read, one upsert, at most one delete. Changes to saved records are audited in bulk with old and new values. Unchanged saves write nothing.
- A day cannot be marked if it is a holiday (the holiday's name is shown), in the future, or outside the selected year.
- A holiday cannot be added to a day that already has attendance. Clear the attendance first.
- Who may mark: the section's class teacher and its subject teachers, within the edit window (`attendance.edit_window_days`, default 0: the day itself). Anyone with `attendance.edit_late` (Principal, Admin, Super Admin) may change any day.
- A student's summary (`students/{id}/attendance`): days marked, days attended, the percentage, the count per status, and the last 30 missed days. Current formula: present counts 1, late counts 1, half day 0.5, absent 0; **leave counts as a marked day with 0 attended** (changes in step 0.6, §7).

**Planned.**

- Leave is not counted in the percentage at all (step 0.6). Release note required.
- Weekly off days (`weekly_off_days`, default Sunday) cannot be marked; they are excluded like holidays.
- A sheet record (`attendance_sheets`) stores who submitted the sheet and when. The first save sets both; later edits do not change them.
- Attendance rows keep the section and academic year the student was in when marked (step 0.5, waiting on owner answer Q14).
- Per day only for v4.0. The `period` column stays for a later option.
- Staff attendance is per day (§3.8).

### 3.6 Exams and scores

**Built (API).**

- Exams belong to an academic year. Names are unique per year. The exam's maximum applies to every subject unless a paper overrides it for one class and subject (`exam_papers`).
- Marks are entered one section and one subject at a time. Each student gets a mark from 0 to the paper's maximum, or is absent. A blank mark removes the entry.
- Anyone with `exam.manage` enters marks for any section. A user with `exam.enter_marks` enters marks only for a section they teach that subject to.
- An exam's maximum and a paper's maximum cannot drop below marks already entered under them. A paper set back to the exam's maximum is removed.
- An exam with marks cannot be deleted.
- Changes to exams and papers are not yet audited (step 2.6). Marks are audited.

**Score out of 5 (built, lifetime).** Every exam a student has taken, in any year, counts equally:

```
percentage of one exam = mark ÷ that paper's maximum × 100        (absent exams are left out)
score out of 5         = average of those percentages ÷ 100 × 5, one decimal
```

Example: 40/50 (80%) and 90/100 (90%) average 85%, a score of 4.3.

**Planned.**

- Term results: the same formula within a term, per subject (step 5.1). The lifetime score stays next to the term score.
- Marks have an optional due date per exam (`exams.marks_due_on`).
- Exams belong to a term (`exams.term_id`).
- Report cards, grading scales and mark locking come later. The `exam.publish` and `exam.unlock` permissions exist but nothing uses them.

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
- **At-risk flag** (client settings): true if attendance percentage < 75, or overall term percentage < 35, or any subject's trend ≤ −15 (a drop of 15 points or more between two exams).

**Teacher, per term.** Measure only what the teacher controls. Each measure shows its count, and anything the teacher is not responsible for is reported separately.

- **Attendance on time** = sheets the teacher submitted on time ÷ sheets they were responsible for. Responsible: each school day in the term up to today, for each section where they were class teacher on that day. Not a holiday or a weekly off day. On time: submitted by the teacher, on that day, at or before the cut-off (`attendance.cutoff_time`, default 10:00 local). Reported separately: sheets marked by someone else; days with no sheet.
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
- No job exists yet for the business rules; audits are written in the request. Queued audits are planned (Phase 6).

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
- Stores: `session` (user, permissions, modules), `academicYear` (selected year, sent as `X-Academic-Year`), `terms` (institution vocabulary).
- Shared components in `resources/js/components`: AppHeader, AppSidebar, AppIcon, AppLogo, UserMenu, AcademicYearSwitcher, PageHeader, AlertMessage, EmptyState, LoadingState, FormField, SearchField, SegmentedTabs, TablePagination, RoleBadge, InitialsAvatar, LoginCard.
- Shared helpers: `school/academic.js` (year structure, teacher list), `school/navigation.js` (the one list of pages), `lib/http.js`, `lib/errors.js`, `lib/money.js` (paise formatting; not yet used by pages).
- Missing and to be built once in `resources/js/components` before any screen needs them: teacher picker, section picker, confirm dialog, modal, date and money formatter, loading/busy wrapper.

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
| `attendance.view` / `attendance.mark` | route middleware, AttendancePolicy | view: Teacher, Principal, Admin, Super Admin; mark: same |
| `attendance.edit_late` | AttendancePolicy | Principal, Admin, Super Admin |
| `exam.view` / `exam.enter_marks` | route middleware, ExamPolicy | view: Teacher, Principal, Admin, Super Admin; enter marks: Teacher, Principal, Admin, Super Admin |
| `exam.manage` | route middleware, ExamPolicy | Principal, Admin, Super Admin |
| `fee.view` / `fee.collect` / `fee.reverse` | route middleware | view, collect, reverse: Accountant, Principal, Admin, Super Admin |
| `settings.manage`, `role.manage`, `audit.view` | **nowhere yet** | Admin, Super Admin (and Principal for audit view) |
| `staff.view`, `staff.manage` | **nowhere yet** | Principal, Admin, Super Admin |
| `exam.publish`, `exam.unlock` | **nowhere yet** | Principal, Admin, Super Admin |
| `fee.concession_approve`, `communication.send`, `report.view` | **nowhere yet** | see the role table in code |

Guardian and Student roles hold no permissions. Roles of clients created before a permission existed keep their old permissions until edited.

## 6. Client settings

Stored in the client's `settings` table and read through `OrganizationContext`. Changed by client admins. **No settings endpoint exists yet** (Phase 1, step 1.5).

| Key | Default | Set by | Used for | Status |
| --- | --- | --- | --- | --- |
| `academic_year.start_month` | June (school), July (college), April (coaching) | client creation (`--year-start-month`) | the start of each year | built |
| `attendance.edit_window_days` | 0 | operations, until step 1.5 | how far back a teacher can change attendance | built |
| `institution.terminology` | from institution type | seeder | labels in the SPA | stored; the SPA reads it from the type, not the setting |
| `branding.logo_url`, `branding.theme` | none | operations | branding | read by `/branding`; no screen |
| `school.timezone` | `Asia/Kolkata` | Admin | local-time rules | planned |
| `attendance.cutoff_time` | `10:00` local | Admin | teacher on-time attendance | planned |
| `weekly_off_days` | `["sunday"]` | Admin | attendance refusal and school days | planned |
| `insights.section_rank_enabled` | `false` | Admin | section rank | planned |
| `insights.at_risk.attendance_below` | 75 | Admin | at-risk flag | planned |
| `insights.at_risk.overall_below` | 35 | Admin | at-risk flag | planned |
| `insights.at_risk.trend_drop` | 15 | Admin | at-risk flag | planned |

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

**Remaining, in order**

| Step | What | Size | Status |
| --- | --- | --- | --- |
| 0.4 | `has_marks` (boolean, `withExists`) replaces `marks_count` on exams; the one exam test updated | S | not started |
| 0.5 | `attendance_records.section_id` and `academic_year_id` snapshot; `attendance_sheets` table; backfill | M | waiting on owner answer Q14 |
| 0.6 | leave excluded from the attendance percentage; `docs/release-notes-v4.md` in the same commit; update the leave test | S | not started |
| 1.1 | navigation grouped into the six areas; old paths redirect | S | not started |
| 1.2 | role homes, starting with Teacher and Principal | M | not started |
| 1.3 | Year Setup status endpoint and checklist page | M | not started |
| 1.4 | edit screens for classes, sections and periods | M | not started |
| 1.5 | client settings endpoint and screen (§6) | M | not started |
| 1.6 | branding ETag | S | not started |
| 2.1 | `terms` table, CRUD, defaults per type, admin confirmation before exams | M | not started |
| 2.2 | `exams.term_id`, `exams.marks_due_on`, backfill, validation | S | not started |
| 2.3 | exams list, create and edit screens | M | not started |
| 2.4 | marks entry screen with bulk upsert, bulk audit, `exam_mark_sheets` | M | not started |
| 2.5 | results per exam, per class | M | not started |
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
| Q1 | Attendance per day; keep the `period` column | owner |
| Q2 | Leave is excluded from the attendance percentage; release note | owner |
| Q3 | Late counts 1, half day 0.5 | owner |
| Q4 | Teachers see their own numbers only, no peer ranking | owner (T1 confirmed: own sections' averages only) |
| Q5 | Section rank is a client setting, off by default | owner |
| Q6 | At-risk: attendance < 75, overall < 35, trend drop ≥ 15 | owner |
| Q7 | Terms: school 3, college 2 semesters, coaching 1 | owner |
| Q8 | Insights visibility | owner (see D16) |
| Q9 | The lifetime score out of 5 stays next to the term score | owner |
| Q10 | A teacher needs a login for v4.0 | owner |
| Q11 | Optional `marks_due_on`; attendance cut-off 10:00 local; `school.timezone` Asia/Kolkata; timestamps stored in UTC | owner |
| Q12 | No Sunday holidays; weekly off days setting, default Sunday | owner |
| Q13 | Razorpay later | owner |
| Q14 | Which clients hold live data | **open.** The round-4 reply was the placeholder "[none / list codes]", not an answer. Step 0.5 stays blocked. |
| Q15 | Staff attendance per day | owner |
| Q16 | Substitutions later | owner |
| T1 | Teachers see their own sections' averages only | owner (confirmed) |
| T2 | Class-teacher history accepted (D14) | owner (confirmed) |
| T3 | Term defaults accepted (D15) | owner (confirmed) |
| T4 | Rule F (§10) | owner (confirmed) |
| T5 | Release notes live in `docs/release-notes-v4.md`, created in the leave-counting commit | owner (confirmed) |

## 9. Open questions

1. **Q14: which clients hold live data today?** Blocks step 0.5 and the migration rehearsal. Reply with client codes, or "none".
2. **Q17: When do guardians get logins, and what does each guardian see?** Blocks the guardian home (§2.4) and the guardian app (step 7.2).
3. **Q18: Do students get logins, and what do they see?** Blocks the student app (step 7.2).

## 10. Working rules

- `docs/EDUNEXUS.md` is the only specification. Do not create other documents, analysis files or specs. Anything that needs writing down goes here.
- Every commit that changes behaviour updates this document in the same commit: status, rules, data model and settings.
- One commit per roadmap step, with the full test suite green before each.
- Every new screen uses the shared components and the design system. No page-specific copies of pickers, dialogs, formatters or loading logic. If a shared piece is missing, build it once in `resources/js/components` first.
- Every new endpoint gets a feature test and a query-count budget. Budgets are never raised to make a test pass.
- Probes and tests use existing credentials only. Never create MySQL or Redis users, grant privileges, or use root or provisioning credentials outside the `edunexus:client:*` commands.
- Ask only when a decision is a product choice. Make technical decisions yourself, following the existing conventions, and record them in §8.
- `AGENTS.md`'s install step is already done: `laravel/boost` is a dependency. Do not run `composer require` for it again. This follows the rule above on dependency changes.

## 11. Data model

Conventions: every table has `id` and timestamps unless noted. Foreign keys are restrictive unless the column says otherwise. Enrollment is the anchor for student data; the academic year is the context.

**Identity and platform (built)**

| Table | Key columns and rules |
| --- | --- |
| `users` | name, email (unique), password, remember token, `subject_id` (legacy: one subject, kept in step with `subject_teacher` until a later release; planned for removal) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie permission tables; role name unique per guard |
| `personal_access_tokens` | Sanctum tokens, unique token; no expiry |
| `settings` | key (unique), value (JSON) — client settings (§6) |
| `audits` | owen-it audit rows: auditable, event, old and new values, user, IP, URL, time |
| `sequences` | key (unique), next value — gapless counters, locked on use |
| `guardians` | name, phone (encrypted), phone hash (indexed), email, `user_id` (unique, nullable; unused) |

**Academic (built)**

| Table | Key columns and rules |
| --- | --- |
| `academic_years` | name (unique), starts_on, ends_on, is_current (one at a time) |
| `classes` | academic_year_id, name, sort_order; unique (year, name) |
| `sections` | class_id, name, capacity, class_teacher_id (nullable, → users); unique (class, name) |
| `subjects` | code (unique), name |
| `class_subject` | class_id, subject_id; primary key on both |
| `periods` | academic_year_id, name, starts_at, ends_at, is_break, sort_order; unique (year, name) |
| `timetable_entries` | section_id, weekday, period_id, subject_id, teacher_id (nullable), is_override; unique (section, weekday, period); unique (teacher, weekday, period); index (teacher, section) |
| `section_subject_teachers` | section_id, subject_id, teacher_id; unique (section, subject); index (teacher, section) |
| `subject_teacher` | subject_id, user_id; primary key on both; index (user) |
| `holidays` | academic_year_id, date, name; unique (year, date) |

**Students and admissions (built)**

| Table | Key columns and rules |
| --- | --- |
| `students` | admission_no (unique), name (indexed), date_of_birth, gender, photo_path |
| `guardian_student` | guardian_id, student_id, relation, is_primary; primary key on both; both cascade |
| `student_enrollments` | student_id, academic_year_id, section_id, roll_no, status; unique (student, year); unique (section, roll_no); index (year, status) |
| `guardian_consents` | guardian_id, student_id, notice_version, method, consented_at, recorded_by, IP, withdrawn_at |
| `admission_applications` | application_no (unique), academic_year_id, class_id, status, student name, guardian fields, guardian phone hash (indexed), decided_by, student_id (unique) |

**Attendance (built)**

| Table | Key columns and rules |
| --- | --- |
| `attendance_records` | enrollment_id, date, period (default 0 = whole day), status (varchar 20; the enum is in code), remark, marked_by; unique (enrollment, date, period); index (date, status). Planned additions: `section_id`, `academic_year_id` (step 0.5) |

**Examinations (built)**

| Table | Key columns and rules |
| --- | --- |
| `exams` | academic_year_id, name (unique per year), max_marks, starts_on, ends_on. Planned: `term_id`, `marks_due_on` |
| `exam_papers` | exam_id, class_id, subject_id, max_marks; unique (exam, class, subject) |
| `exam_marks` | exam_id, enrollment_id, subject_id, marks (decimal 5,2, null when absent), is_absent, recorded_by; unique (exam, enrollment, subject) |

**Fees (built, backend)**

| Table | Key columns and rules |
| --- | --- |
| `fee_heads` | name (unique) |
| `fee_receipts` | receipt_no (unique), financial_year, enrollment_id, amount (unsigned BIGINT, paise), method, reference, paid_on, idempotency_key (unique), gateway_payment_id (unique), received_by |
| `fee_entries` | enrollment_id, type (due, payment, reversal), fee_head_id, amount (signed BIGINT, paise), due_on, description, receipt_id, reverses_id (unique), recorded_by, created_at only; index (enrollment, type). Never updated or deleted |

**Planned**

| Table | Purpose | Phase |
| --- | --- | --- |
| `attendance_sheets` | one per section and day: submitter, first-save time; unique (section, date) | 0.5 |
| `class_teacher_assignments` | section, teacher, from and to dates | 5.1 |
| `terms` | per year: name, sequence, starts_on, ends_on; unique (year, sequence) | 2.1 |
| `exam_mark_sheets` | exam, section, subject, entered_by, submitted_at; unique (exam, section, subject) | 2.4 |
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
| Academic | `academic-years` (list, show, `next`, `{id}/make-current`), `academic-years/{id}/classes`, `classes/{id}` (update, delete, subjects, subject teacher), `sections` (show, store, update, delete, subject teacher), `subjects` (list, create, update, teachers), `academic-years/{id}/periods` (list, create), `periods/{id}` (update, delete), `sections/{id}/timetable` (show, set, clear), `me/timetable`, `me/teaching`, `teachers`, `teachers/workload`, `academic-years/{id}/holidays` (list, create), `holidays/{id}` (delete) |
| Students | `students` (list, create, show, update), `students/{id}/guardians` (add, remove), `students/{id}/enrollments`, `enrollments/{id}`, `students/{id}/attendance`, `students/{id}/scores`, `students/{id}/fees` |
| Admissions | `admissions` (list, create, show, update), `admissions/{id}/decision`, `admissions/{id}/admit` |
| Attendance | `sections/{id}/attendance` (show, save) |
| Examinations | `academic-years/{id}/exams` (list, create), `exams/{id}` (show, update, delete), `exams/{id}/classes/{class}/subjects/{subject}/max-marks`, `exams/{id}/marks` (show, save) |
| Fees | `fee-heads` (list, create), `fee-dues` (create), `enrollments/{id}/payments` (collect), `fee-entries/{id}/reverse`, `receipts/{id}`, `sections/{id}/fees` |
