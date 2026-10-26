# EduNexus ERP — Architecture Plan v3.2

Oct 25, 2026 · Umapathi

**Revision note.** v3 replaces the v2 multi-tenant design (one deployment for every school, school picked from the hostname, central database). EduNexus now follows the BizFlow model: one deployment per client, configured by that client's own env files (`<code>.env` and `<code>.env.secure`) and backed by its own MySQL database and user. The fees section is unchanged from v2.

v3.1 matches the code as of Oct 22, 2026. It adds subject teachers, a first examination module (exams, marks and a score out of 5 per subject), the Metronic-style SPA design system, and the development seeders. Subject teachers and exams exist in the API only; their screens are not built yet.

v3.2 prepares EduNexus for 10,000–15,000 concurrent users across all clients, makes the academic year a context the whole app works in, lets a teacher teach more than one subject, and adds minimal attendance and fee modules. Each change is marked **v3.2 · planned** until it is built and **v3.2 · built** after. The analysis behind it is in `docs/analysis-v3.2.md`.

## v3.2 changes

| # | Change | Where | Status |
| --- | --- | --- | --- |
| 1 | Strict Eloquent models outside production; query-count tests for every list endpoint | [Performance and scale](#performance-and-scale) | built |
| 2 | Redis for sessions, cache, queues (Horizon) and rate limiting, with a per-client key prefix and Redis user; throttled token `last_used_at` | [Queues](#queues-notifications-storage-and-audit), [Security](#security-and-compliance) | built |
| 3 | Academic year as context: start-month setting, automatic first year, next year with copy-forward, year switcher; the year create and edit endpoints are retired | [Core data model](#core-data-model) | built |
| 4 | A teacher teaches one or more subjects; class-level subject teachers; timetable slots default to the subject teacher; workload matrix; Subjects and Subject teachers pages | [Core data model](#core-data-model) | built |
| 5 | Minimal attendance (section-day sheets) and fee (dues, payments, receipts) modules | [Core data model](#core-data-model), [Fees](#fees-and-finance-ledger) | planned |
| 6 | Database: indexes for hot queries, bulk upserts for attendance and marks sheets, queued audits, score and fee-balance projections, cursor pagination | [Performance and scale](#performance-and-scale) | planned |
| 7 | Caching invalidated by model events: branding (ETag), `/me`, settings and terminology, timetables, a teacher's sections | [Performance and scale](#performance-and-scale) | planned |
| 8 | Octane readiness: no request state outlives a request | [Performance and scale](#performance-and-scale) | planned |
| 9 | Load testing: `LoadTestSeeder` and k6 scenarios | [Performance and scale](#performance-and-scale) | planned |

## Summary and locked principles

EduNexus is one Laravel codebase that runs as one client at a time. A client (a school, college or coaching centre) gets its own environment files, its own MySQL database and its own MySQL user, and a running deployment serves exactly that client.

1. One codebase; each client runs it as its own deployment.
2. The client is chosen at boot by `EDUNEXUS_CLIENT=<code>`, never from the request hostname.
3. One MySQL database and one MySQL user per client; the user has rights on that database only.
4. Client settings live in `deployments/<code>/<code>.env`; client secrets live in `deployments/<code>/<code>.env.secure`.
5. API-first: the Vue 3 SPA and the Flutter app both consume the same Laravel API. No Blade portals.
6. Business logic lives in domain Actions; controllers stay thin.
7. No self-registration. Each client starts with one Super Admin, who creates every other user and assigns roles.
8. Enrollment, not student, is the anchor for attendance, marks and fees.
9. Fees are a ledger: immutable entries, gapless receipt numbers, idempotent payments.
10. No client-specific `if ($client === ...)` logic anywhere. Differences are institution type, `.env` overrides or settings.
11. Every URL in mails, messages and PDFs is built from the client's `APP_URL`.
12. Children's data is encrypted where sensitive and governed by a retention policy.

## Deployment model

Each client is a separate deployment of the same image, pointed at its own files and database.

```text
        abc.edunexus.in                     xyz.edunexus.in
               │                                   │
               ▼                                   ▼
  ┌────────────────────────────────┐  ┌────────────────────────────────┐
  │ EduNexus  EDUNEXUS_CLIENT=abc  │  │ EduNexus  EDUNEXUS_CLIENT=xyz  │
  │ deployments/abc/abc.env        │  │ deployments/xyz/xyz.env        │
  │ deployments/abc/abc.env.secure │  │ deployments/xyz/xyz.env.secure │
  └───────────────┬────────────────┘  └───────────────┬────────────────┘
          user edunexus_abc                    user edunexus_xyz
                  ▼                                   ▼
  ┌───────────────────────────────────────────────────────────────────┐
  │              MySQL: edunexus_abc   edunexus_xyz   ...             │
  │            (each user can reach its own database only)            │
  └───────────────────────────────────────────────────────────────────┘
```

Isolation comes from the database layer rather than from application code. A client's MySQL user cannot read another client's database, so a bug in a query cannot leak data across clients.

| Trade-off | Effect |
| --- | --- |
| Strong isolation | Separate DB user, APP_KEY, sessions, tokens, cache and queue per client |
| Simple runtime | No tenancy package, no hostname resolution, no central database |
| Operations per client | Each client is a deployment to roll, migrate, monitor and back up |
| No shared directory | There is no central list of clients at runtime; `deployments/` is the list |

## Client files

`edunexus:client:create` writes two files per client. Both are git-ignored in this repository. Real client files belong in a separate, access-controlled deployment repository (proposed name: `edunexus-deployment`).

| File | Holds | Example keys |
| --- | --- | --- |
| `deployments/<code>/<code>.env` | Non-secret settings: a complete environment for the deployment | `EDUNEXUS_CLIENT_CODE`, `EDUNEXUS_INSTITUTION_TYPE`, `EDUNEXUS_ORG_NAME`, `APP_URL`, `RECEIPT_PREFIX`, `EDUNEXUS_MODULES_ENABLE`, `EDUNEXUS_MODULES_DISABLE`, `DB_HOST`, `DB_PORT`, session, cache, queue and mail settings |
| `deployments/<code>/<code>.env.secure` | Secrets, file mode `0600` | `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |

Both file names start with the client code, so open files are easy to tell apart. Database, MySQL user and receipt prefix are derived from the code too: client `abc` gets database and user `edunexus_abc` (prefix `EDUNEXUS_CLIENT_DB_PREFIX`, default `edunexus_`) and receipt prefix `ABC`.

## Client lifecycle

### Creating a client

```bash
php artisan edunexus:client:create abc \
    --name="ABC Public School" --type=school \
    --admin-email=owner@abc.test
```

| Step | Result |
| --- | --- |
| 1. Validate | Code is lowercase `a-z0-9`, 2–20 characters, and not already used (no directory, database or MySQL user with that name); `--type` is a valid institution type |
| 2. Write `deployments/abc/abc.env` | Non-secret settings, see [Client files](#client-files) |
| 3. Write `deployments/abc/abc.env.secure` | New `APP_KEY`, `DB_DATABASE=edunexus_abc`, `DB_USERNAME=edunexus_abc`, a random 32-character DB password |
| 4. Provision the database | Creates database `edunexus_abc` and MySQL user `edunexus_abc` with rights on that database only, through the `provisioning` connection (`DB_PROVISION_USERNAME`) |
| 5. Migrate and seed | Connected as the new client user: runs all migrations, then `DatabaseSeeder`: the institution-type seeder, the first academic year (v3.2), and the sample school only when `APP_ENV=local` (see [Development data](#development-data)) |
| 6. Create the admin | A Super Admin; the password is printed once, or asked for with `--ask-password` |
| 7. Summary | Prints the client code, institution, database, first academic year, login URL and Super Admin email; for a non-local client also its Redis user and the access string to create it with (v3.2) |

Other options: `--admin-name` (default "Super Admin"), `--url` (default `EDUNEXUS_CLIENT_URL`, `http://localhost:8000`) and, since v3.2, `--year-start-month=<1-12>` (default per institution type, see [Academic years](#academic-years-v32--built)). If any step from 2 to 6 fails, the database, MySQL user and files are removed again.

### Supporting commands

| Command | Purpose |
| --- | --- |
| `edunexus:client:list` | Clients found in `deployments/`, with name, type, database and URL |
| `edunexus:client:migrate abc` | Runs pending migrations for one client, or every client with `--all`; `--seed` reruns the seeders (e.g. to add new permissions) |
| `edunexus:client:drop abc --force` | Local and testing only: drops the database, the MySQL user and the files; refuses in any other environment, and without `--force` |

### The provisioning connection

The `provisioning` database connection uses `DB_PROVISION_USERNAME` and `DB_PROVISION_PASSWORD` (host and port default to `DB_HOST`/`DB_PORT`). It needs `CREATE`, `DROP`, `CREATE USER` and `GRANT OPTION`. Only the `edunexus:client:*` commands use it; a running client never does. New MySQL users are created for host `DB_PROVISION_USER_HOST` (default `%`).

## Running as a client

**Locally.** Prefix any command with the client code:

```bash
EDUNEXUS_CLIENT=abc php artisan serve
EDUNEXUS_CLIENT=abc php artisan tinker
```

Without `EDUNEXUS_CLIENT`, the root `.env` is used as the development client (`EDUNEXUS_CLIENT_CODE=dev`, database `edunexus_dev`).

**At boot.** `bootstrap/app.php` calls `ClientEnvironment::apply()` before the app loads its environment:

1. Reads `EDUNEXUS_CLIENT` from the process environment and checks it is a valid code.
2. Fails immediately if `deployments/<code>/<code>.env` or `<code>.env.secure` is missing. There is no fallback to the root `.env`.
3. Points the config, route and event caches at `bootstrap/cache/clients/<code>/`, so `config:cache` for one client can never boot another with its credentials.
4. Loads `<code>.env.secure` first, then `<code>.env`. The environment is immutable, so values from the secrets file cannot be overridden by the settings file.

`EDUNEXUS_DEPLOYMENTS_PATH` overrides where client directories are looked up (default `deployments/`).

**Storage.** Each client gets its own storage directory, `storage/clients/<code>/` (files, logs, compiled views), created on first boot. `EDUNEXUS_STORAGE_PATH` overrides it, e.g. to point at a mounted volume. There is no Docker setup; run each client with `EDUNEXUS_CLIENT=<code> php artisan serve`, on its own port and `APP_URL`. Since v3.2 the session cookie is named after the client (`edunexus_<code>_session`), so clients running side by side on `localhost` keep separate sessions without setting `SESSION_COOKIE`.

### Development data

`DatabaseSeeder` runs `InstitutionTypeSeeder`, `AcademicYearSeeder` (v3.2: opens the first academic year when there is none) and `UserSeeder` everywhere, and `DemoSchoolSeeder` only when `APP_ENV=local`. Tests seed only `InstitutionTypeSeeder` and build their own data, years included, and production clients never get sample data.

| Seeder | What it creates |
| --- | --- |
| `UserSeeder` | A Super Admin from `EDUNEXUS_SUPER_ADMIN_NAME`, `_EMAIL` and `_PASSWORD` in the root `.env`, to sign in to the development client. Skipped when no email is set. `edunexus:client:create` and `edunexus:client:migrate --seed` clear these values first, so the root credentials never reach a client database. |
| `DemoSchoolSeeder` | The year today falls in (current) and the year before, from the start month, e.g. 2025-26 and 2026-27; Class 1 to Class 10 with sections A, B and C (capacity 40), 8 subjects, a bell schedule of eight periods with two breaks, 30 teachers (`teacher01@<code>.test` to `teacher30@<code>.test`, password `password`, each a class teacher, teaching one subject or two) with a subject teacher for every section subject, and 50 students per class with two guardians each. No timetable. Last year's students move up a class. Refuses to run in production; safe to rerun. Run it alone with `php artisan db:seed --class=DemoSchoolSeeder`. |

## Institution types

`--type` sets what kind of institution a client is. It decides default modules, seeded roles and terminology. Everything else is shared.

| Type | Default modules (besides the core set) | Roles seeded with Super Admin | Terminology (class / section / teacher / head) |
| --- | --- | --- | --- |
| `school` | Examination, Transport | Admin, Principal, Teacher, Accountant, Guardian, Student | Class / Section / Teacher / Principal |
| `college` | Examination, Library, Hostel | Admin, Principal, Teacher, Accountant, Student | Semester / Division / Faculty / Principal |
| `coaching` | none | Admin, Teacher, Accountant, Guardian, Student | Course / Batch / Tutor / Centre head |

The core set for every type is Academic, Admission, Student, Staff, Attendance, Fee, Communication and Report. The definitions live in `App\Support\Deployment\InstitutionType`. `InstitutionTypeSeeder` syncs the roles and stores the terminology, with plural forms (Classes, Semesters, Batches…), in the `settings` table under `institution.terminology`. The SPA uses these words in every label. Since v3.2 it also stores the month academic years start in, `academic_year.start_month`: June for schools, July for colleges and April for coaching centres, unless the client already has one.

## Request lifecycle

1. Request arrives at `abc.edunexus.in/api/v1/users`. The deployment already runs as client `abc`; nothing is resolved from the hostname.
2. Sanctum authenticates the user, by session cookie for the SPA or bearer token for the mobile app.
3. Module middleware (`entitled:<module>`) checks the module is enabled for this client.
4. A Form Request validates the input and authorizes it through a policy.
5. The controller calls one Action (e.g. `CreateUser`), which writes through Eloquent to the client database.

## Application structure and layering

Each domain module owns its models, actions, policies and events. There are no top-level `app/Models`, `app/Jobs` or `app/Policies` folders competing with the modules.

```text
app/
├── Console/Commands/   # edunexus:client:create|list|migrate|drop
├── Domain/             # business modules, all in the client DB
│   ├── Identity/       # users, roles, permissions, user management (built)
│   ├── Academic/       # years, classes, sections, subjects, subject teachers, periods, timetable (built)
│   ├── Admission/      # enquiries, decisions, admission with consent (built)
│   ├── Student/        # students, guardians, enrollments (built); promotion
│   ├── Staff/
│   ├── Attendance/
│   ├── Examination/    # exams, papers, marks, subject scores (API built)
│   ├── Fee/
│   ├── Communication/
│   └── Report/
│       └── (each) Models/ Actions/ Policies/ Events/ Jobs/ Data/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   ├── Requests/Api/V1/
│   └── Resources/Api/V1/
└── Support/
    ├── Deployment/     # ClientEnvironment, CreateClient, ClientFiles, ClientDatabase, InstitutionType
    ├── Modules/        # Module enum, Entitlements
    ├── Settings/       # Setting model
    ├── Sequences.php   # gapless counters (admission no., later receipts)
    └── OrganizationContext.php
```

**Layers.** Controller → Form Request → Action → Eloquent. No repository layer over Eloquent. Add a Data object (Spatie Laravel Data) only where input crosses boundaries awkwardly, such as bulk imports.

**Migrations.** A single `database/migrations` path, run against each client database by `edunexus:client:create` and `edunexus:client:migrate`.

**Organization context.** `OrganizationContext` exposes the client's code, name and institution type (from `<code>.env`) and its settings (from the `settings` table, loaded once per request).

## Core data model

`student_enrollments` is the anchor table. A student exists once; each academic year they get one enrollment, and attendance, marks and fee assignments hang off that enrollment.

| Table | Key columns | Notes |
| --- | --- | --- |
| `academic_years` | name, starts_on, ends_on, is_current | Never overwritten; one current at a time |
| `classes`, `sections` | academic_year_id, name, capacity, class_teacher_id | Recreated per year so history stays intact |
| `subjects`, `class_subject` | code, name; class_id, subject_id | Subjects are shared across years; each class offers a set of them |
| `users.subject_id` | subject_id | v3.1's one subject per teacher. **v3.2 · built:** replaced by `subject_teacher`, kept in step for one release and dropped in v3.3 |
| `subject_teacher` | subject_id, user_id | **v3.2 · built.** The subjects a teacher teaches (one or more); primary key on the pair, index on user_id |
| `timetable_entries.is_override` | is_override | **v3.2 · built.** The slot's teacher was set by hand instead of following the subject teacher |
| `section_subject_teachers` | section_id, subject_id, teacher_id | Who teaches each subject to a section; unique on (section_id, subject_id) |
| `exams`, `exam_papers`, `exam_marks` | see [Examinations](#examinations) | Marks hang off the enrollment |
| `students` | admission_no, name, dob, gender, photo | Identity only, no class column |
| `guardians` | name, phone, email, user_id | One login sees all linked children |
| `guardian_student` | guardian_id, student_id, relation, is_primary | Enables sibling discounts |
| `student_enrollments` | student_id, academic_year_id, section_id, roll_no, status | Unique on (student_id, academic_year_id) |
| `attendance_records` | enrollment_id, date, period, status, marked_by | Unique on (enrollment_id, date, period). **v3.2 · planned** (not built in v3.1) |
| `holidays` | academic_year_id, date, name | Excluded from attendance percentages. **v3.2 · planned** (not built in v3.1) |

**Rules built in Phase 2.**

- Exactly one academic year is current; the first year created becomes current.
- Class names are unique within a year and section names within a class. A class with sections, or a section with students, cannot be deleted, and a section's capacity cannot drop below its enrolled count.
- One enrollment per student per year, always in a section of that year. Capacity is enforced under a row lock; roll numbers are unique per section and default to the next free number.
- An enrollment can move to another section of the same year only; changing years is promotion.
- Guardian phones are normalised (digits only, no +91 or leading 0), encrypted, and matched by keyed hash, so siblings share one guardian record. Each student has at least one guardian and exactly one primary guardian.
- Students, guardians and enrollments are audited; guardian phone values never enter the audit log.

#### Academic years (v3.2 · built)

The academic year is a context every screen and endpoint works in, instead of something each page looks up. No one creates or edits years by hand.

- **Start month.** Each client has an `academic_year.start_month` setting: June for schools, July for colleges and April for coaching centres, or the month given to `edunexus:client:create --year-start-month=<1-12>`. Reseeding keeps a client's month.
- **Shape of a year.** A year starts on the 1st of the start month and is named after it: with June, `2026-27` runs from 1 June 2026 to 31 May 2027 (with January, a year is named `2026`). `CreateAcademicYear` never lets years overlap: a new year starts after the year before it ends and ends before the year after it starts. Application numbers use the same name (`2026-27/0001`).
- **First year.** `AcademicYearSeeder` opens the year today falls in, as current, for a client with no year: at creation, or for an existing client with `edunexus:client:migrate --seed`.
- **Next year.** `POST academic-years/next` (`academic.manage`) opens the year after the current one and copies forward its classes, their sections and capacities, each class's subjects and the bell schedule (`CopyAcademicYearForward`). Class teachers, subject teachers and the timetable are set afresh. Only one year can be open ahead of the current one. The new year does not become current; `POST academic-years/{id}/make-current` switches when it starts, so admissions for the next year can be taken before then.
- **Retired.** `POST academic-years` and `PATCH academic-years/{id}` are removed (405) along with the SPA's year form.
- **Selected year.** A request works in the year named by the `X-Academic-Year` header (a year id), set by the `SelectedAcademicYear` middleware on every API route, or else the current year. `AcademicYearContext` (scoped to the request) resolves it lazily, so requests that never use it pay nothing. A header naming no year fails with 422. The list of years is cached for an hour and forgotten after commit whenever a year is saved or made current.
- **What follows it.** The student and admission lists (unless filtered by `academic_year_id`), the enrollment shown with each student, teacher visibility (`student.view_assigned`), `GET me/timetable` and `GET me/teaching`. A marks sheet uses its exam's year instead.
- **SPA.** The header has an `AcademicYearSwitcher` for users with `academic.view`, tinted when a year other than the current one is picked. The choice is remembered in the browser and sent on every request; switching reloads the open page in that year. Academic setup shows the picked year, with Make current and Open next year.

**Admissions.** An application moves enquiry → approved → admitted, or to rejected (reason required) or withdrawn; small institutions may admit straight from an enquiry. Closed applications cannot change.

- Application numbers are gapless per academic year (`2026-27/0001`); admission numbers default to `<CODE><00001>`, skip numbers typed in by hand, and are given back if the admission rolls back. Both come from the `sequences` table (`App\Support\Sequences`), locked `FOR UPDATE` inside the using transaction.
- Admitting runs in one transaction: it locks the application, checks the section belongs to the class applied for, creates the student, links or reuses the guardian (by phone), enrolls them, and writes a `guardian_consents` row.
- Consent is mandatory: the admitting user confirms the guardian saw the privacy notice and records how consent was obtained (`signed_form`, `in_person`, `digital`). The row stores the notice version (`EDUNEXUS_PRIVACY_NOTICE_VERSION`, shown in the UI via `/api/v1/branding`), time, recorder and IP.
- Guardian phones on applications are encrypted and searchable by exact number, like guardians'.

**Timetable.** Each academic year has a bell schedule of periods (some marked as breaks); each section has a weekly grid of slots (weekday × period → subject and optional teacher).

- A slot's period must belong to the section's year and not be a break; its subject must be offered to the section's class.
- A teacher cannot be in two sections in the same weekday and period: checked with a readable message and enforced by a unique index.
- A period in use cannot be deleted or turned into a break.
- `GET /api/v1/me/timetable` gives a teacher their own week.
- A section's timetable response also lists the section's subject teachers.
- **v3.2 · built.** A slot set without a teacher gets the section's subject teacher for its subject and follows it: when the section's subject teacher changes, those slots move to the new teacher (or lose their teacher when it is cleared). A slot given anyone else is an override (`timetable_entries.is_override`, backfilled for existing slots whose teacher isn't the subject teacher) and stays put. If the new subject teacher is busy in one of the following slots, the change is refused and the error names the clash; nothing is changed.

**Subject teachers (v3.2 · built).** Academic years and subjects are master data. Per year, each section of a class gets one teacher for each subject the class studies, e.g. 2026-27, Class 1 A: Mathematics – Satheesh, English – Karthik, Tamil – Srini.

- A teacher teaches one or more subjects, held in `subject_teacher`. They are set when the user is created (`subject_ids`), with `PUT users/{id}/subjects`, or per subject with `PUT subjects/{id}/teachers`. A subject has many teachers.
- The migration copied `users.subject_id` into `subject_teacher` and can run again safely. The column is kept in step (one of the teacher's subjects) for one release, so the previous release still works during a rollout, and is dropped in v3.3 after a final copy. `PUT users/{id}/subject` is removed.
- A section's subject teacher must teach that subject, and the subject must be offered to the section's class. Assigning no teacher clears it.
- `PUT classes/{id}/subjects/{subject}/teacher` gives every section of the class the same teacher for the subject, replacing their own, or clears it everywhere. It applies the section rules to each section in one transaction. Sections keep their own assignments, so one can differ afterwards.
- A subject can't be taken from a teacher who teaches it to sections in the current year or a later one; the error names the sections. Earlier years are history and no longer block (in v3.1 every year did).
- Taking a subject off a class (`PUT classes/{id}/subjects`) also removes its subject teachers in that class's sections.
- Subject teachers see the students of their sections (see RBAC), and `GET me/teaching` lists their sections and subjects in the selected year.
- `GET teachers` lists everyone with the Teacher role or a subject they teach, with their subjects and without paging; every teacher picker uses it. `GET teachers/workload` gives each teacher's timetable periods per weekday and week and the section subjects they teach in the selected year, plus the teaching periods in a day.

**API (academic, students, admissions).** All under `/api/v1`, behind `entitled:academic`, `entitled:student` or `entitled:admission`. Exam endpoints are under [Examinations](#examinations).

| Area | Endpoints | Permission |
| --- | --- | --- |
| Years | `GET academic-years`, `GET academic-years/{id}`, `POST academic-years/next`, `POST academic-years/{id}/make-current` | read `academic.view`, write `academic.manage` |
| Classes, sections | `GET/POST academic-years/{id}/classes`, `PATCH/DELETE classes/{id}`, `PUT classes/{id}/subjects`, `POST classes/{id}/sections`, `GET/PATCH/DELETE sections/{id}` | read `academic.view`, write `academic.manage` |
| Subjects | `GET/POST subjects` (the list includes each subject's teachers), `PATCH subjects/{id}`, `PUT subjects/{id}/teachers` | read `academic.view`, write `academic.manage` |
| Subject teachers | `PUT sections/{id}/subjects/{subject}/teacher` and `PUT classes/{id}/subjects/{subject}/teacher` (`teacher_id`, or null to clear), `GET me/teaching` | write `academic.manage`; own list: any signed-in user |
| Teachers | `GET teachers`, `GET teachers/workload` | `academic.view` |
| Users | `GET/POST users` (`subject_ids` optional), `PUT users/{id}/roles`, `PUT users/{id}/subjects` | `user.manage` |
| Students | `GET students` (search, `class_id`, `section_id`, `academic_year_id`), `GET students/{id}`, `POST students` (with guardians and optional enrollment), `PATCH students/{id}` | read: see RBAC; write `student.manage` |
| Guardians | `POST students/{id}/guardians`, `DELETE students/{id}/guardians/{guardian}` | `student.manage` |
| Enrollments | `POST students/{id}/enrollments`, `PATCH enrollments/{id}` | `student.manage` |
| Periods | `GET/POST academic-years/{id}/periods`, `PATCH/DELETE periods/{id}` | read `academic.view`, write `academic.manage` |
| Timetable | `GET sections/{id}/timetable`, `PUT/DELETE sections/{id}/timetable/{weekday}/{period}`, `GET me/timetable` | read `academic.view` (own timetable: any signed-in user), write `academic.manage` |
| Admissions | `GET/POST admissions`, `GET/PATCH admissions/{id}`, `POST admissions/{id}/decision`, `POST admissions/{id}/admit` | `admission.manage`, module `admission` |

**Promotion workflow.** At year end, an admin bulk-promotes sections into the next year's sections, marking each student promoted, detained, or left. Leaving issues a transfer certificate. This creates new enrollments and never edits old ones.

**Attendance edits.** Teachers can edit within a configurable window (default same day). Later edits need admin rights and are audited.

**v3.2 · planned.** A minimal attendance module: a section's sheet for one day is marked and saved in one bulk upsert, holidays are excluded, and the edit window above applies.

## Fees and finance ledger

**v3.2 · planned.** A minimal version of this ledger (dues, payments and gapless receipts per enrollment) is built in v3.2; the rest of this section stays the design.

Fees are an append-only ledger per enrollment. Balances are derived from entries, never stored as an editable column.

1. **Fee heads and structures:** tuition, transport, exam, etc., priced per class per academic year, with installment schedules.
2. **Assignment:** a structure applied to an enrollment generates dated installment dues.
3. **Concessions:** sibling, staff ward, scholarship, or manual, each with a reason and approver. Posted as credit entries.
4. **Late fines:** rule-based, posted by a scheduled job after the due date plus grace days.
5. **Payments:** cash, cheque, UPI, or gateway. Partial and advance payments are allowed and allocated to the oldest dues first.
6. **Receipts:** issued per payment with a gapless number per financial year, prefixed with the client's `RECEIPT_PREFIX`.
7. **Reversals:** refunds and cheque bounces are new reversing entries; nothing is deleted or edited.

**Implementation rules.**

- Store money as integer paise (`BIGINT`). Never floats.
- Receipt numbers come from a `sequences` row locked with `SELECT ... FOR UPDATE` inside the payment transaction. Auto-increment leaves gaps on rollback.
- Gateway webhooks (Razorpay) are idempotent on the gateway payment ID, with a unique index. Retries must not double-post.
- A daily reconciliation job compares gateway settlements against ledger entries and flags mismatches.
- Financial year (April to March) is separate from academic year in reports.

## Examinations

A first exam module is built in the API: exams per academic year, marks per student and subject, and a consolidated score out of 5 per subject. Its screens are not built yet. All routes sit behind `entitled:examination`.

| Table | Key columns | Notes |
| --- | --- | --- |
| `exams` | academic_year_id, name, max_marks, starts_on, ends_on | Name unique per year; `max_marks` (1–999) applies to every subject unless a paper overrides it |
| `exam_papers` | exam_id, class_id, subject_id, max_marks | Only for a class's subject whose maximum differs, e.g. Quarterly, Class 1 Computer Science: out of 50 |
| `exam_marks` | exam_id, enrollment_id, subject_id, marks, is_absent, recorded_by | Unique on (exam_id, enrollment_id, subject_id); marks up to 2 decimals; audited |

**Rules.**

- Marks are entered a sheet at a time: one section, one subject. Each student gets marks from 0 to the paper's maximum, or absent; a blank removes their entry.
- Anyone with `exam.manage` enters marks for any section. A user with `exam.enter_marks` enters marks only as that section's subject teacher (`ExamPolicy::enterMarks`).
- A paper's maximum, and an exam's own maximum, cannot drop below marks already entered under it. Setting a paper back to the exam's maximum removes the paper.
- An exam with marks cannot be deleted.

**Score out of 5.** Every exam a student has taken, in any year, counts equally:

```text
percentage of one exam = marks ÷ that paper's max marks × 100
score out of 5         = average of those percentages ÷ 100 × 5, rounded to 1 decimal
```

Exams the student was absent for are left out rather than counted as zero. For example, 40/50 (80%) and 90/100 (90%) average 85%, a score of 4.3.

**API.**

| Area | Endpoints | Permission |
| --- | --- | --- |
| Exams | `GET/POST academic-years/{id}/exams`, `GET/PATCH/DELETE exams/{id}` | read `exam.view`, write `exam.manage` |
| Paper maximum | `PUT exams/{id}/classes/{class}/subjects/{subject}/max-marks` | `exam.manage` |
| Marks sheet | `GET exams/{id}/marks?section_id=&subject_id=` (students the user can see, in roll order, with their marks, the paper's maximum and what the user may change), `PUT exams/{id}/marks` (saves the whole sheet) | read `exam.view`; write per `ExamPolicy` |
| Scores | `GET students/{id}/scores` (each subject's score, average percentage and the exams behind it) | `exam.view` and permission to view the student; module `student` too |

**Planned.** Exam structure, grading and report cards stay data, not code, because CBSE, state board and matriculation schools all differ.

- **Assessment scheme** per class per year: terms, exams within each term, and their weightage toward the final result.
- **Components** per subject: theory, practical, internal, each with max marks and pass marks.
- **Grading scales** as rows (min %, max %, grade, grade point), selectable per scheme.
- **Co-scholastic areas** (discipline, art, sports) graded on their own scale.
- **Report card templates** per scheme, rendered to PDF in a queued job.
- **Mark locking:** once results are published, marks are locked; unlocking needs admin rights and is audited. The `exam.publish` and `exam.unlock` permissions exist for this but nothing uses them yet.

## Auth, RBAC, users, frontend and mobile

**No registration.** A client's first and only seeded user is its Super Admin, created by `edunexus:client:create`. Users with the `user.manage` permission create every other login, assign roles and set the subjects teachers teach through the API (`POST /api/v1/users`, `PUT /api/v1/users/{id}/roles`, `PUT /api/v1/users/{id}/subjects`).

**RBAC.** Spatie Permission in the client database, guard `web` for both cookie and token requests. Permissions are fixed in code (`App\Domain\Identity\Enums\Permission`, e.g. `attendance.mark`, `fee.collect`). Roles are seeded per institution type and editable by the client; reseeding never overwrites a client's edits. A teacher's access is further scoped by policy: the Teacher role holds `student.view_assigned`, which shows only students enrolled, in the selected year (current by default), in sections where they are class teacher, subject teacher, or teach a timetable period. `student.view` (Accountant, Principal, Admin) shows every student. Guardian phone numbers are masked except for users with `student.manage`. Roles of clients created before this permission existed keep their previous Teacher permissions until edited.

**Exam permissions.** Teachers hold `exam.view` and `exam.enter_marks`. `exam.manage` (create and change exams, set paper maximums, enter any section's marks) was added in v3.1. New clients' Principal role gets it. Existing clients add it with `edunexus:client:migrate --seed`: Super Admin and Admin pick it up automatically, while an existing Principal role keeps its permissions until a client admin grants it.

**Super Admin rules.**

- Super Admin and Admin always hold every permission.
- Only a Super Admin can grant the Super Admin role or change another Super Admin's roles.
- A client can never lose its last Super Admin; the request fails validation.

**Web (Vue 3 SPA).** Served by the same deployment at every non-API path, so auth is same-origin and needs no CORS. Sanctum cookie auth: `GET /sanctum/csrf-cookie`, then `POST /api/v1/auth/login`. Sessions are stored in Redis for deployed clients and in the client database locally; cookies are host-only (`SESSION_DOMAIN=null`). Branding (name, type, terminology, logo, theme) comes from the public `GET /api/v1/branding`.

**SPA design system.** The SPA follows the Metronic admin look, rebuilt in Tailwind CSS v4 rather than installed as the paid Metronic package, so it adds no dependency.

- **Tokens** live in `@theme` in `resources/css/app.css`: the Inter font (served by the `@fonts` Blade directive), primary `#1B84FF`, a navy-tinted gray scale, and success, warning, danger and info tones. Each tone has a fill, a hover fill (`-active`), a tinted surface (`-light`) and a readable text colour for that surface (`-strong`).
- **Component classes** in the same file keep every page uniform: `btn-primary`, `btn-secondary`, `btn-danger`, `btn-icon`, `btn-link`, `input`, `checkbox`, `choice`, `card` (with `card-header`, `card-body`, `card-footer`), `badge-*`, `table` and `spinner`.
- **Layout.** A full-width header holds only the app name (EduNexus, with the institution's name under it), the academic year switcher (v3.2) and the profile menu (name, email, roles, sign out). A sidebar below it holds the navigation, grouped under Academics and Administration; on phones it becomes a slide-out drawer.
- **Shared Vue components** in `resources/js/components`: `PageHeader`, `AppIcon` (inline Lucide icons), `InitialsAvatar`, `SearchField`, `SegmentedTabs`, `TablePagination`, `EmptyState`, `LoadingState`, `AlertMessage`, `FormField` and `AcademicYearSwitcher`. The selected year lives in the `academicYear` store (`resources/js/school/academicYear.js`).
- **Navigation.** `resources/js/school/navigation.js` is the one list of pages, filtered by the user's permissions and modules. It feeds both the sidebar and the dashboard's quick-access tiles.

| Page | Path | Shown when the user has |
| --- | --- | --- |
| Sign in | `/login` | — |
| Dashboard | `/dashboard` | any login |
| Students, student profile | `/students`, `/students/{id}` | `student.view`, `student.view_assigned` or `student.manage`; module `student` |
| Admissions | `/admissions` | `admission.manage`; module `admission` |
| Timetable | `/timetable` | `academic.view`; module `academic` |
| Subject teachers, with a Workload tab (v3.2) | `/subject-teachers` | `academic.view`; module `academic` (changes need `academic.manage`) |
| Academic setup (years, classes, sections, class subjects) | `/academic` | `academic.view`; module `academic` |
| Subjects and their teachers (v3.2) | `/subjects` | `academic.view`; module `academic` (changes need `academic.manage`) |
| Users (with the subjects each teaches) | `/users` | `user.manage` |

No screens exist yet for exams, marks entry or subject scores.

**Mobile (Flutter).** Signs in with `POST /api/v1/auth/tokens` and sends a Sanctum bearer token. Tokens live in the client database, so a token from one client never works on another. How the app finds a client's URL is an open question (see below), because there is no central school directory in this model.

## Modules and settings

| Kind | Lives in | Who changes it | Example |
| --- | --- | --- | --- |
| Module | Institution type defaults, plus `EDUNEXUS_MODULES_ENABLE` / `EDUNEXUS_MODULES_DISABLE` in the client `<code>.env` | EduNexus operations, per deployment | Enable `hostel` for one college |
| Client setting | Client DB `settings` table | Client admins | Attendance edit window, logo |
| Deployment setting | Client `<code>.env` | EduNexus operations | `APP_URL`, `RECEIPT_PREFIX`, mail sender |

Module overrides are comma-separated module codes, e.g. `EDUNEXUS_MODULES_ENABLE=hostel,library`. Unknown codes are ignored. `Entitlements` resolves the final list, `GET /api/v1/me` returns it for the SPA menu, and the `entitled:<module>` route middleware blocks a disabled module's endpoints. Billing and subscription state are handled outside the app.

## Queues, notifications, storage and audit

Every client has its own queues, cache and workers.

**Redis (v3.2 · built).** Deployed clients keep sessions, cache, queues and rate-limiter state in one shared Redis. Local clients and tests keep the database drivers (array and sync in tests).

- **Keys.** Every key starts with `edunexus:<code>:` (the default `REDIS_PREFIX`), including Horizon's (`edunexus:<code>:horizon:`).
- **Redis user.** For any environment other than local, `edunexus:client:create` writes `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION=redis`, `REDIS_HOST`, `REDIS_PORT` and `REDIS_USERNAME` (named like the MySQL user) to `<code>.env`, and a random `REDIS_PASSWORD` to `<code>.env.secure`.
- **Access string.** The command prints the access string the client's Redis user must be created with: `on ~edunexus:<code>:* &edunexus:<code>:* +@all -@dangerous`. That keeps isolation at the data layer, as MySQL does. Creating the user is an operations step: ElastiCache manages users through the AWS API, not `ACL SETUSER`.
- **No shared commands.** `-@dangerous` blocks `FLUSHDB`, so `cache:clear` fails for a deployed client instead of wiping every client's cache. Clear a client's keys by forgetting them, not flushing.
- **Not yet validated.** The access string has not been checked against a real Redis yet; check it on staging during load testing.

**Queues.** Separate queues by priority: `critical` (OTP, payment receipts), `default` (notifications, audits, recomputes), `bulk` (fee reminders, imports, report PDFs).

- **Horizon (v3.2 · built).** Each client deployment runs its own Horizon: one supervisor works all three queues with auto-balancing, 3 tries with 5 s and 30 s backoff, and a 60 s timeout (under the connection's 90 s `retry_after`). Production defaults to 4 processes per client (`HORIZON_MAX_PROCESSES`).
- **Dashboard.** `/horizon` opens only for a Super Admin outside local development, because job payloads can hold students' personal data.
- **Locally,** run `queue:work --queue=critical,default,bulk` on the database queue.

**URLs in background work.** Mails, WhatsApp messages and PDFs build links from the client's `APP_URL`.

**Notifications.** One `NotificationService` routes events (`StudentMarkedAbsent`, `FeeDue`, `ResultPublished`) to push (FCM), SMS, email and WhatsApp Business. Templates and channel choices are per client. Each send is logged with status for delivery reports.

**Storage.** One private S3 bucket per client, or one bucket with a per-client path prefix (`clients/{code}/...`), set in the client `<code>.env`. Files are served only through short-lived signed URLs generated after a policy check. Never public URLs for student documents. Locally, each client already has its own `storage/clients/<code>/` (see [Running as a client](#running-as-a-client)).

**Audit.** `owen-it/laravel-auditing` on marks, attendance edits, fee entries, concessions, role changes and user and student record edits. Changes made from queued jobs and commands are audited too. Stores user, old and new values, IP and time; password and remember-token changes are never stored. Audit rows older than 3 years move to cold storage.

Not built yet: role changes are not audited (roles are a pivot owen-it doesn't see), and no job moves old audit rows. Audits are written synchronously in the request; **v3.2 · planned:** they move to the queue.

## Security and compliance

EduNexus holds minors' personal data, so India's DPDP Act requirements for children's data apply from the first client onboarded.

- **Consent:** capture verifiable guardian consent at admission, stored with timestamp and version of the notice shown.
- **Encryption:** Aadhaar number, medical notes and guardian phone numbers use Laravel encrypted casts with the client's own `APP_KEY`. Store a hash alongside any field you need to search on.
- **Minimisation:** collect Aadhaar only if the client needs it; make it optional by default.
- **Retention:** define how long records of students who left are kept, then anonymise. Document it in the client contract.
- **Isolation:** each client's MySQL user is granted rights on its own database only. An automated test checks that the user cannot read another database.
- **Secrets:** `<code>.env.secure` holds the APP_KEY and DB credentials, is created with mode `0600`, and is never committed here. The provisioning credentials stay out of client deployments.
- **Rate limits (v3.2 · built):**
  - Sign-in is limited to 5 per minute per email and IP, and 300 per minute per IP.
  - Every API route is limited to 120 per minute per signed-in user, or 600 per minute per IP for guests (branding and sign-in).
  - A school's users usually share one public IP, so the per-IP limits are sized for a whole school and the per-account limits do the real work.
  - Limiter state lives in the cache store: Redis for deployed clients.
  - In v3.1 only sign-in was limited, at 30 per minute per IP.
- **Token use:** a mobile token's `last_used_at` is written at most every 5 minutes (`sanctum.last_used_at_every`) by `RecordTokenUse`, instead of on every request (v3.2 · built). Tokens still never expire.
- **Tests:** the suite refuses to run unless the database name ends in `_test` and the client prefix contains `test`, because it resets the database and drops test client databases and users.

Confirm the exact DPDP obligations with a lawyer before launch; the rules were still being phased in as of mid-2026.

## Performance and scale

**Target.** 10,000–15,000 concurrent users across all clients. Schools open, mark attendance and publish results at about the same times, so client peaks add up rather than average out; every client's database sits on one RDS instance and every client shares one Redis.

**Hot queries.** Measured on a client with 5,000 current-year students (see `docs/analysis-v3.2.md`):

| Query | Today | v3.2 |
| --- | --- | --- |
| A teacher's student list (`student.view_assigned`) | Two dependent subqueries per current enrollment: about 440 ms per request | **planned:** resolve the teacher's section IDs first (cached), then filter by them; indexes on (teacher_id, section_id) |
| Marks sheet save, 45 students | 149 queries: one SELECT, one write and one audit insert per student | **planned:** one bulk upsert and bulk audit rows |
| Attendance sheet save | Not built | **planned:** one bulk upsert per section-day |
| Score out of 5 | Recomputed from every mark on each request | **planned:** `student_subject_scores` projection, recomputed by a job, checked nightly |
| Fee balance | Not built | **planned:** `fee_balances` projection, recomputed by a job, checked nightly |
| Fixed cost of each request | 5–6 queries, including a session or token `last_used_at` write | **built:** sessions, cache (including Spatie's permission map) and limiter in Redis for deployed clients; `last_used_at` written at most every 5 minutes, leaving the token or session lookup, the user, and the user's roles and permissions |

**Safety net (v3.2 · built).**

- Outside production, Eloquent is strict (`Model::shouldBeStrict()` in `AppServiceProvider`): lazy loading a relation, reading an attribute that wasn't selected and filling one that isn't fillable all throw.
- `tests/Feature/ListQueryCountTest.php` covers all 20 list endpoints. Each test counts one request's queries with one row, adds two more rows with all their relations, and fails if the count changes (an N+1) or exceeds the endpoint's budget, set to today's count. Tighten a budget when a change lowers it; never raise one to make a test pass.
- Every API route has a feature test, including exams, marks sheets, scores and subject teachers, which had none in v3.1.

**Caching.** **v3.2 · planned:** branding (with ETag), `/me`, settings and terminology, timetables and a teacher's section IDs are cached per client and invalidated by model events.

**Octane.** **v3.2 · planned:** all request state is request-scoped, proven by a test that sends two requests through one application instance. Production stays on PHP-FPM until load testing shows Octane is worth it.

**Load testing.** **v3.2 · planned:** `LoadTestSeeder` (about 5,000 students, 10,000 guardians, 3 years; refuses production) and k6 scenarios in `tests/load/` for morning attendance, results day, fee due date and a login storm.

## Infrastructure and operations

Run on AWS ap-south-1 (Mumbai) with managed data stores; no databases in containers.

| Component | Service | Notes |
| --- | --- | --- |
| Edge / TLS | ALB or Cloudflare | One hostname per client, e.g. `abc.edunexus.in` |
| Web/API | One ECS service per client, PHP-FPM + Nginx, same image | `EDUNEXUS_CLIENT=<code>`; client files from the deployment repo or a secrets manager |
| Workers | One Horizon service per client | Queues in the shared Redis, under the client's prefix |
| Scheduler | One `schedule:run` task per client | |
| Database | RDS MySQL, one instance, one database and user per client | Move a client to another instance by changing its `DB_HOST` |
| Cache, sessions, queues | ElastiCache Redis, one instance shared by every client | One Redis user per client, limited to `edunexus:<code>:*` |
| Files | S3 | Per-client bucket or prefix |

**Onboarding a client.** Run `edunexus:client:create` with the provisioning credentials, commit the generated files to the deployment repository, add the client's service and hostname, and deploy.

**Deploys.** CI builds one image. Deploy runs `edunexus:client:migrate --all` (or `migrate` inside each client's service), then rolls the client services. Migrations must stay backward compatible with the previous release, because clients migrate one after another.

**Backups.** RDS automated snapshots for disaster recovery, plus a nightly per-client `mysqldump` to S3 so one client can be restored or exported without touching others. Test a single-client restore every quarter.

## MVP scope and roadmap

Ship a v1 a client can run daily on, then add modules only when a paying client asks.

1. **Phase 1, platform (built):** client creation and lifecycle commands, per-client env files, database and storage, institution types, Super Admin, user and role management, Sanctum login for SPA and mobile, audit, SPA shell with login, dashboard and users. A Docker setup was dropped by choice.
2. **Phase 2, institution core (built):** academic years, classes, sections, subjects, students, guardians, enrollments, admissions with guardian consent, bell schedule and timetable, with SPA pages for academic setup, students, admissions and the timetable. The SPA was redesigned in the Metronic style in October 2026. Exit: one pilot client fully set up.
3. **Phase 3, daily operations:** attendance, fees ledger with Razorpay, receipts, guardian notifications. Exit: pilot client collects one full fee term in EduNexus.
4. **Phase 4, academics (started):** subject teachers, exams, marks entry and the score out of 5 are built in the API. Still to do: their screens (a separate Classes page, section subject teachers, exams and marks sheets, scores on the student profile), feature tests for them, then grading, report cards, mark locking, homework and the guardian mobile app. Exit: one term's report cards issued from EduNexus.
5. **Phase 5, year-end:** promotion workflow, transfer certificates. Exit: pilot client rolls over to the next academic year.
6. **Later, on demand:** transport, library, HR and payroll, inventory, hostel.

**Open questions.**

- Which board's grading (CBSE, Tamil Nadu State Board, matriculation) the pilot school follows, since it shapes Phase 4 defaults. It could become a seeded setting per institution type.
- How the Flutter app finds a client's URL now that there is no central directory: one white-label build per client, a QR code or URL entered on first launch, or a small separate directory service.
- Where client files live in production: a private `edunexus-deployment` repository, a secrets manager, or both (settings in git, secrets in the manager).
