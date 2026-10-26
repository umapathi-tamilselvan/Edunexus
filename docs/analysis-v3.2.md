# EduNexus ERP — v3.2 Analysis (Stage 1)

Oct 25, 2026 · Analysis only; no application code was changed.

## Read this first

- **`docs/architecture-v3.2.md` is not in the repository.** It is not on disk, on any branch (`master`, `feature/crud`, `feature/UI_upgrade`, their remotes), in a stash or anywhere in git history. Until it arrives, this analysis treats the Stage 2 list in the brief as the definition of the v3.2 changes. Anything that depends on wording only the v3.2 document has is a question in [section 5](#5-questions-for-you), including the hot queries its Performance section names, endpoint contracts, copy-forward rules and start-month defaults.
- **v3.1 lives in `docs/edunexus-architecture-plan.md`**, titled "Architecture Plan v3.1", with its v3.1 edits still uncommitted. There is no `docs/architecture-v3.1.md`.
- **There is no attendance or fee code at all.** `app/Domain` holds only Academic, Admission, Examination, Identity and Student. So these v3.2 items have nothing to change: attendance bulk upserts, the `fee_balances` projection, and the "morning attendance" and "fee due date" load tests.
- **The framework is Laravel 13.34.0**, not 12. Other direct dependencies: Sanctum 4.3.3, spatie/laravel-permission 8.3.0, owen-it/laravel-auditing 14.0.6 and PHPUnit 12.5. Redis, Horizon and Octane are not installed, and neither phpredis nor predis is present.

### How this was measured

1. Read every file in `app/`, `routes/`, `config/`, `database/`, `tests/` and `resources/js/`.
2. Ran the full suite: **142 tests, 645 assertions, all pass, in 4 min 51 s wall time (22 s CPU)**. The suite is MySQL-bound.
3. Ran the same suite with `Model::shouldBeStrict()` switched on through a throwaway PHPUnit bootstrap that logs violations instead of throwing. All 142 tests passed with **0 violations**. Eloquent only flags lazy loads on models loaded in multi-row results, and most tests use 1–3 rows, so this result alone proves little.
4. Measured at realistic size with a throwaway probe test (not committed) against a separate database. The probe used the production drivers (`SESSION_DRIVER=database`, `CACHE_STORE=database`). Before each call it reset the session store, the scoped instances and Spatie's permission map, as a fresh PHP-FPM request would. Each call counted queries and logged strict-mode violations. It also ran `EXPLAIN` on every SELECT, plus `EXPLAIN ANALYZE` when the plan looked bad, after `ANALYZE TABLE`.

| Probe data | Rows |
| --- | --- |
| Students / current-year enrollments | 5,050 / 5,000 in 130 sections (DemoSchoolSeeder plus sections D–M of 45 students per class) |
| Users | 151 (150 teachers, each linked to one subject) |
| Subject teachers / timetable slots | 845 / 6,240 (full 6-day × 8-period grid for every current section) |
| Exam marks | 68,250 (two exams this year, one last year) |
| Admission applications / guardians | 200 / 1,100 |

## Decisions (Oct 25, 2026)

| Question | Answer | Effect on the plan |
| --- | --- | --- |
| Can a teacher teach more than one subject? | Yes, sometimes. | Build the `subject_teacher` pivot as in [2.4](#24-subjects-and-teachers). A teacher gets one or more subjects and can be subject teacher for any of them. |
| Is the 10k–15k concurrent-user target per client or across all clients? | Across all clients. | See [What the overall target means](#what-the-overall-target-means). |
| Where is the v3.2 document? | Create it from v3.1. | `docs/architecture-v3.2.md` starts as a copy of v3.1 with every v3.2 change marked "planned"; each step flips its items to "built". |
| Attendance and fees don't exist. | Build minimal modules inside v3.2. | Two new steps before step 5 (see [section 4](#4-proposed-order-of-work)), so attendance upserts, `fee_balances` and both load tests have something to target. |
| Redis isolation? | One shared Redis, a per-client prefix and a per-client ACL user. | Step 2 writes `REDIS_USERNAME` and a prefix per client. |
| Approve Stage 2? | Yes, on `feature/UI_upgrade`. | One commit per sub-step below. |
| Sign-in limit per IP? | Raise it from 30 to 300 a minute. | 5 a minute per email + IP stays. |
| API limit? | 120 a minute per signed-in user, 600 a minute per guest IP. | Applied in step 2.4. |
| Default year start month? | June for schools, July for colleges, April for coaching. | Step 3. |
| Year name format? | `2026-27`. | Step 3; also what application numbers show. |
| What does `academic-years/next` copy? | Classes, sections with capacity, class subjects and periods. | Teachers and the timetable are not copied. |
| What follows the selected year? | Everything a user browses, including teacher visibility. | Step 3. |
| New dependencies? | Keep the current ones; add one only when a step needs it. | **Horizon** is added in step 2.3, because the brief names it. **No Redis client package**: production uses the phpredis extension in the server image (the config already defaults to it), while local and tests keep the database drivers. **Octane is not added**: the worker test reproduces Octane's per-request reset itself. **k6** is installed on the machine that runs load tests, not added to the project. |

### What the overall target means

- **Peaks line up across clients.** Schools open, mark attendance and publish results at about the same times, so the aggregate peak is close to the sum of every client's peak, not an average.
- **One large client can be a big share on its own.** A client the size of `LoadTestSeeder` has about 10,000 guardian logins once guardians can sign in, so its results day alone is a large part of the total.
- **Rough arithmetic.** Assume each concurrent user sends one request every 10 seconds. Then 15,000 users make about 1,500 requests a second across all clients, all against the one RDS instance v3.1 describes. At the measured 12 or so queries per typical read, that is about 18,000 queries a second. About 6 of those 12 are the fixed sign-in, session and permission work in [3.2](#32-fixed-cost-of-every-request), including one write per request, so about 1,500 writes a second do no useful work.
- **What follows for the plan:**
  - Step 2 matters most. Moving sessions, cache and the limiter to Redis and throttling `last_used_at` removes 2 of the 6 fixed queries, including the write. Caching a user's roles and permissions (step 6) could remove 2 more. Together that is about a third of all queries.
  - Redis should be **one shared instance**, since one per client isn't justified for an aggregate target. That makes per-client key prefixes mandatory, and a per-client Redis ACL user the way to keep v3.1's isolation principle (question 6).
  - Load tests come in two shapes: one large client at its own peak, which runs locally with `LoadTestSeeder`, and several clients at once for the aggregate, which needs staging infrastructure shaped like production.

## 1. Where the code differs from v3.1

### v3.1 says it, the code doesn't do it

| # | v3.1 says | The code | Matters for |
| --- | --- | --- | --- |
| 1 | "The API is limited per user or IP." | Only `POST auth/login` and `POST auth/tokens` are throttled. The `api` limiter (120/min) is defined in `AppServiceProvider` but attached to none of the other 59 routes (`bootstrap/app.php` never calls `throttleApi()`). | Step 2 (limiter) |
| 2 | Audit covers "role changes". | `SyncUserRoles` uses Spatie's `syncRoles()`, which writes the `model_has_roles` pivot. owen-it never sees it, so role changes are not audited. | Step 5 (audits) |
| 3 | "Audit rows older than 3 years move to cold storage." | No job, command or schedule exists. `routes/console.php` is empty. | Step 5 |
| 4 | Core data model lists `attendance_records` and `holidays`; the module tree lists Staff, Attendance, Fee, Communication and Report. | None of these tables or folders exist. | Steps 5 and 8 |
| 5 | Terminology is stored in `settings.institution.terminology` and the SPA uses it. | `InstitutionTypeSeeder` writes the setting, but nothing reads it. `GET /branding` returns `InstitutionType::terminology()` from code, so editing the setting changes nothing. | Step 6 (settings cache) |
| 6 | Client settings are changed by client admins. | There is no settings endpoint or screen. `settings.manage` is unused. | Step 6 |
| 7 | Request lifecycle: "A Form Request validates the input and authorizes it through a policy." | Only the user requests have `authorize()`. Elsewhere authorization is route `can:<permission>` middleware or `Gate::authorize()` in controllers. Only three policies exist (User, Student, Exam). | Convention for new endpoints |
| 8 | "A teacher still assigned to sections cannot move to another subject." | `SetTeacherSubject` checks assignments in **every** year. A teacher who taught Maths last year can never switch subjects unless last year's history is deleted. | Step 4 (fixes itself if the rule is per year) |
| 9 | Queues by priority, Horizon or `queue:work`, NotificationService, S3. | Nothing is queued anywhere (no Jobs, no listeners) and audits are synchronous. There is no NotificationService and storage is local only. | Step 2 |
| 10 | Academic years are "never overwritten". | `PATCH academic-years/{id}` changes the name and dates of any year, including years with classes, enrollments and exams. There is no overlap check, and `make_current` is accepted by validation but ignored on PATCH. | Step 3 (endpoint retired) |
| 11 | DemoSchoolSeeder is called by DatabaseSeeder when local (doc). | The doc and code agree. The seeder's own docblock still says "DatabaseSeeder never calls it". It links no teacher to a subject and creates no subject teachers, periods, timetable or exams. | Step 4 (seeder update) |

### The brief and the code disagree

- **`ExamPolicy::enterMarks` and `student.view_assigned` do not use `users.subject_id`.** Both read `section_subject_teachers.teacher_id`. `Student::visibleTo` also reads `sections.class_teacher_id` and `timetable_entries.teacher_id`. `users.subject_id` decides only who *may be assigned* to a subject, and who appears in teacher lists. See the inventory in [2.4](#24-subjects-and-teachers).

### Defects found on the way

- **Teacher pickers are cut off at 50 users.** The class-teacher picker (`AcademicPage.vue`) and the slot-teacher picker (`TimetablePage.vue`) load page 1 of `GET users` (50 per page, all roles, by name) and filter for Teacher. With 151 users, most teachers can't be picked.
- **Timetable race returns a 500.** `SetTimetableSlot` catches `UniqueConstraintViolationException` and calls `ensureTeacherIsFree($teacher, …)` even when `$teacher` is null. Two people filling the same empty slot at once get a `TypeError`.
- **Bulk deletes bypass model events.** These deletes use the query builder, so they won't fire the model events v3.2's cache invalidation relies on: `TimetableController::clear`, `AssignSubjectTeacher` (clearing a teacher) and `SyncClassSubjects`.
- **Stale comments.** `StudentPolicy::view` still says "class teacher of" only.

### What matches

These all match v3.1 as written:

- client lifecycle, `ClientEnvironment`, per-client config caches and storage
- institution types and entitlements
- admissions with gapless numbers and consent
- enrollment rules, guardian phone encryption and hashing
- exam rules and the score formula
- the RBAC visibility rule, Super Admin rules
- the SPA pages and design system

## 2. Per v3.2 change

Estimates include tests and doc updates. **S** is at most half a day, **M** is 1–2 days and **L** is 3–5 days.

### 2.1 Safety net: strict mode and query-count tests

- **Files:**
  - `app/Providers/AppServiceProvider.php`: `Model::shouldBeStrict(! $this->app->isProduction())`.
  - New feature tests asserting the query count of every list endpoint stays flat when the data doubles. Exact counts would break on every harmless change.
  - Characterization tests for the 20 untested routes before steps 3–5 change them (see [3.11](#311-tests)).
- **Migration:** none.
- **Risk:** low. The existing suite and the scale probe both produced 0 strict-mode violations. Partial selects (`teachers:id,name,subject_id`, `student:id,name,admission_no`) will throw if later code reads another column, which is the point.
- **Estimate:** M. That is S for strict mode and the count tests, plus the missing exam and subject-teacher tests.

### 2.2 Redis: sessions, cache, queues, rate limiter

- **Files:**
  - `config/database.php`, `config/cache.php`, `config/session.php`: prefixes from `EDUNEXUS_CLIENT_CODE` instead of `APP_NAME`.
  - `config/cache.php`: `limiter` store.
  - `config/queue.php`: `critical`, `default` and `bulk` queues.
  - `config/permission.php`: cache store.
  - `config/sanctum.php`: `last_used_at => false`, plus a `TokenAuthenticated` listener that writes at most every N minutes.
  - New `config/horizon.php`, a Horizon provider with a gate, and `bootstrap/providers.php`.
  - `CreateClient::settings()`: production clients get Redis keys, local keeps the database drivers.
  - `.env.example`.
  - `bootstrap/app.php`: `throttleApi()`, which v3.1 already promises.
- **Migration:** none.
- **Risk: medium, with one high-risk part.**
  - **Today every client would share Redis keys.** `REDIS_PREFIX` is `edunexus-database-` and `CACHE_PREFIX` is `edunexus-cache-` for every client, because every client `.env` has `APP_NAME=EduNexus`. On a shared Redis, client A would read client B's `spatie.permission.cache`. The prefix change must land before any Redis driver is switched on.
  - **Moving sessions logs everyone out once.**
  - **Existing clients' `.env` files need new keys.** `CreateClient` only writes them for new clients (question 7).
  - **Shared Redis.** With one Redis for every client, each client needs its own prefix and its own ACL user limited to that prefix (question 6).
- **Dependencies:** `laravel/horizon`, added in step 2.3. No Redis client package: production installs the phpredis extension in the server image (the config already defaults to `REDIS_CLIENT=phpredis`).
- **Estimate:** M.

### 2.3 Academic year as context

**Where the year is created, edited and read today:**

| Kind | Where |
| --- | --- |
| Created | `CreateAcademicYear` (called by `POST academic-years` and `DemoSchoolSeeder`); `AcademicYearFactory` in tests |
| Edited | `AcademicYearController::update` (`PATCH academic-years/{id}`, `StoreAcademicYearRequest`); `MakeAcademicYearCurrent` (`POST …/make-current`, `CreateAcademicYear` for the first year or `make_current`, `DemoSchoolSeeder`) |
| Current year read in PHP | `StudentController::index` (default year); `AdmissionController::index` (default year); `Student::visibleTo` (used by `StudentController`, `StudentPolicy::view`, `ExamMarkController::index`, `SubjectScoreController`); `SectionSubjectTeacherController::mine`; `TimetableController::mine` (through `period.academicYear`); `is_current` exposed by `AcademicYearResource` and `SectionResource` |
| Year taken from the URL or parent row | classes, periods and exams routes (`{academicYear}`); `CreateApplication` (from class); `EnrollStudent` (from section); `RecordExamMarks`, `SetExamMaxMarks`, `SetTimetableSlot` (compare years) |
| Current year read in the SPA | `StudentsPage`, `StudentDetailPage`, `TimetablePage` and `AdmissionsPage` each call `loadAcademicYears()`, find `is_current`, then `loadYearStructure()` (22 KB, 10 queries at 130 sections). `AcademicPage` has the year selector, the create form and Make current. There is **no edit form**. |

- **Files:**
  - `InstitutionType` (default start month per type), `InstitutionTypeSeeder` (`academic_year.start_month` when missing).
  - `ClientCreateCommand` and `CreateClient` (`--year-start-month`, first year created after seeding).
  - `routes/api.php`; `AcademicYearController` (drop `store` and `update`, add `next`); delete `StoreAcademicYearRequest`; new `CopyAcademicYearForward` action.
  - New scoped `AcademicYearContext` and `SelectedAcademicYear` middleware; current year cached and invalidated by `AcademicYear` saved events.
  - The five PHP readers above.
  - SPA: a new `AcademicYearSwitcher` in `AppHeader`, a shared selected-year store setting the header on `http`, the five pages, and removal of the year form.
  - `DemoSchoolSeeder`; `AcademicStructureTest` (three tests use `POST academic-years`).
- **Migration:** none for the schema. The setting and first year come from the seeder (`edunexus:client:migrate --seed`), as terminology does today.
- **Risk:** medium–high. Every year-dependent query changes. Retiring `POST`/`PATCH academic-years` breaks any mobile build that calls them. Several rules are unknown without the v3.2 document (question 8).
- **Estimate:** L, as 5–6 commits.

### 2.4 Subjects and teachers

**Every use of `users.subject_id` today:**

| Kind | Where |
| --- | --- |
| Writes | `CreateUser` (`POST users`, `StoreUserRequest.subject_id`); `SetTeacherSubject` (`PUT users/{id}/subject`, `UpdateUserSubjectRequest`, `UserPolicy::updateSubject`); `SectionSubjectTeacherFactory` |
| Rules | `AssignSubjectTeacher`: a section's subject teacher must have this `subject_id`. This is the only business rule that reads it. |
| Reads | `Subject::teachers()` (hasMany) → `SubjectController::index`, `SectionController::show` (`schoolClass.subjects.teachers`); `User::subject()` → `UserController`, `CreateUser`, `SetTeacherSubject`, `UserResource.subject` |
| Not used by | `ExamPolicy::enterMarks`, `Student::visibleTo`, `SetTimetableSlot` (no teacher–subject check at all), `DemoSchoolSeeder` (never sets it), the SPA (never reads or writes it) |

`section_subject_teachers` (unique on `section_id, subject_id`) is used by:

- `AssignSubjectTeacher`
- `SyncClassSubjects` (bulk delete)
- `SetTeacherSubject` (the all-years blocking rule)
- `SectionSubjectTeacherController::mine`
- `TimetableController::section`
- `SectionController::show`
- `AcademicYearController::show` (count)
- `ExamPolicy::enterMarks`
- `Student::visibleTo`

`teacher_id` cascades on user delete, so deleting a teacher would erase history.

**Timetable slots** store their teacher in `timetable_entries.teacher_id`, which is nullable, set per slot and never checked against the subject teacher. `UNIQUE (teacher_id, weekday, period_id)` blocks double-booking.

- **Files (pivot confirmed on Oct 25):**
  - Models `User` and `Subject` (belongsToMany).
  - `AssignSubjectTeacher` (check the pivot).
  - `SetTeacherSubject` → `SyncTeacherSubjects`, blocking only on assignments in the current year.
  - New actions for `PUT subjects/{id}/teachers` and `PUT classes/{id}/subjects/{subject}/teacher`.
  - `StoreUserRequest` and `CreateUser` (`subject_ids`), `UserResource` and `UserController` (`subjects`), `SubjectController`, `SectionController`.
  - `SetTimetableSlot` (default to the subject teacher, set `is_override`).
  - A workload endpoint and resource.
  - Factories, `DemoSchoolSeeder` (link teachers, create subject teachers).
  - SPA: Subjects and Subject teachers pages, navigation, router, UsersPage subjects field.
- **Migration:**
  - Create `subject_teacher` (`subject_id`, `user_id`, unique pair, index on `user_id`).
  - Copy `users.subject_id` into it with `insertOrIgnore`.
  - Keep `users.subject_id` for one release so the previous release still works, and drop it in v3.3.
  - Add `timetable_entries.is_override` (default false), backfilled true where the slot's teacher differs from the section's subject teacher.
- **Risk:** medium.
  - Old code running between migrate and roll-out can still write `users.subject_id`. Make the copy idempotent, rerunnable and re-run in a later migration.
  - Re-pointing non-override slots when a subject teacher changes can collide with the new teacher's other slots through the unique index (question 11).
- **Estimate:** L. API is M; the two SPA pages are M.

### 2.5 Database

- **Indexes and query fixes from [3.6](#36-explain-slow-plans-and-missing-indexes):**
  - Rewrite `Student::visibleTo` for `view_assigned` users to resolve the teacher's section IDs first.
  - Add `timetable_entries (teacher_id, section_id)` and `section_subject_teachers (teacher_id, section_id)` so that lookup is index-only.
  - Change the exam list from `withCount('marks')` to `withExists`.
  - Add `audits (created_at)` only with the archive job.
  - Migrations are additive. MySQL 8 builds them online.
- **Bulk upserts for marks:**
  - `RecordExamMarks` diffs against one SELECT of the sheet, then makes one `upsert` and one `delete … whereIn`.
  - Audit rows are built and inserted in bulk, because bulk writes fire no model events. Today the action deliberately deletes one by one "so the audit trail keeps the old marks", so audit fidelity is the main risk (question 17).
  - Expected cost for 45 rows is about 10 queries instead of 149.
- **Attendance bulk upserts:** blocked, because there is no attendance module (question 4).
- **Queued audits:** `audit.queue.enable` on the Redis queue. owen-it resolves user, IP and URL before queuing. The risk is losing audits if a job fails, so it needs a failed-job alert.
- **`student_subject_scores` projection:**
  - A new table: `student_id`, `subject_id`, `exams_count`, `average_percentage`, `score`, `recomputed_at`, unique pair.
  - A recompute job dispatched after commit on mark changes and on paper or exam maximum changes. The maximum changes fan out to every student of the class.
  - A nightly check command and `routes/console.php` schedule.
  - `ConsolidateSubjectScores` stays for the per-exam breakdown.
- **`fee_balances` projection:** blocked, because there is no fee ledger to project.
- **Cursor pagination:** `students`, `admissions` and `users` drop `meta.total` and page numbers. That breaks `TablePagination` and the SPA's "N students" counts (question 12). It also removes the expensive COUNT query in [3.6](#36-explain-slow-plans-and-missing-indexes).
- **Estimate:** L, as five commits. Each part is S–M.

### 2.6 Caching with model-event invalidation

- **Branding:** cache the payload and add an ETag. Laravel's `cache.headers:…;etag` middleware handles conditional requests and 304 responses.
- **Settings and terminology:** cache `OrganizationContext`'s settings map and invalidate on `Setting` saved or deleted. Decide the terminology source first (question 15).
- **`/me`:** cache roles, permissions and modules per user. Role and permission changes are pivot writes, so this needs `permission.events_enabled => true` and listeners. Stale permissions are a security bug, so use a versioned key and a short TTL.
- **Timetables and a teacher's section IDs:** invalidate on `TimetableEntry`, `Period`, `SectionSubjectTeacher` and `Section` (class teacher) events. First convert the three bulk deletes listed in [section 1](#defects-found-on-the-way) to per-model deletes or explicit cache busts.
- **Migration:** none.
- **Risk:** medium (stale data).
- **Estimate:** M–L.

### 2.7 Octane readiness

- **Files:**
  - `config/permission.php`: `register_octane_reset_listener => true`.
  - Scoped bindings for the new contexts.
  - A test that sends two requests through one application instance as different users and years, with different settings, and proves nothing carries over.
- **Migration:** none.
- **Risk:** low today. `OrganizationContext` is already `scoped` (see [3.10](#310-state-unsafe-under-octane)).
- **No Octane package.** The test reproduces Octane's per-request reset on one application instance: scoped instances, auth guards, the session store and Spatie's permission map.
- **Estimate:** S–M.

### 2.8 Load testing

- **Files:**
  - `database/seeders/LoadTestSeeder.php`: about 5,000 students, 10,000 guardians, 3 years. It refuses production and reuses DemoSchoolSeeder's bulk-insert approach; the probe built 5,000 students and 68k marks in 15 s.
  - `tests/load/*.js` for k6.
- **Migration:** none.
- **Risk:** medium.
  - Two of the four scenarios have no endpoints (question 4).
  - The login storm will hit the 30-per-minute per-IP limit from a single k6 machine (question 13).
  - k6 runs on the load-test machine; it is not a project dependency.
  - The aggregate target can only be measured on staging infrastructure; locally the scripts model one large client.
- **Estimate:** M.

## 3. Performance baseline

### 3.1 Configuration

| Setting | Root `.env`, `.env.example` and every client `.env` that `CreateClient` writes | Tests (`phpunit.xml`) |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | `array` |
| `CACHE_STORE` | `database` | `array` |
| `QUEUE_CONNECTION` | `database` (nothing is queued) | `sync` |
| Rate-limiter store | default cache store (no `cache.limiter` key), so `database` | `array` |
| Spatie permission cache | `store => default`, so `database` | `array` |
| `Model::shouldBeStrict()` | not called anywhere | — |
| Sanctum | `expiration => null` (tokens never expire), `last_used_at` tracking on (the default) | — |

### 3.2 Fixed cost of every request

Every authenticated request pays this before any business logic:

| Path | Queries |
| --- | --- |
| Bearer token (mobile) | SELECT token, SELECT user, **UPDATE `last_used_at`** (skipped only when the previous request was in the same second), SELECT Spatie map from `cache`, SELECT user's roles, SELECT user's permissions: **5 reads + 1 write** |
| Session cookie (SPA) | SELECT session, SELECT user, roles, permissions, **UPDATE `sessions`** every request (plus a 2 % GC `DELETE`): **4–5 reads + 1 write** |
| Sign-in | `POST auth/tokens`: 17 queries; `POST auth/login`: 20. **12 of them are the rate limiter**, including two `SELECT … FOR UPDATE` row locks on the per-IP key. A school signing in from one NAT IP serialises on that row and hits the 30-per-minute per-IP limit. |

### 3.3 Query counts per endpoint (reads)

Probe data from the table above. The SQL time is the sum of query times on the developer machine (MySQL 8.0.46, local), so it is useful for comparing endpoints, not as an absolute figure.

| Endpoint | Queries | SQL ms | Payload | Notes |
| --- | --- | --- | --- | --- |
| `GET branding` (public) | 1 | 1 | 0.3 KB | settings table read |
| `GET me` | 6 | 12–14 | 0.7 KB | fixed cost only |
| `GET roles` | 10 | 20 | 1.7 KB | rebuilds the Spatie cache when cold |
| `GET users` (page 1) | 9 | 9 | 9.0 KB | 50 of 151; the teacher pickers' source |
| `GET academic-years` | 6 | 3 | 0.2 KB | |
| `GET academic-years/{id}` (full structure) | 10 | 11 | **22.4 KB** | loaded by **every** SPA page |
| `GET academic-years/{id}/classes` | 9 | 7 | 9.8 KB | |
| `GET sections/{id}` | 14 | 12 | 4.9 KB | loads every teacher of every class subject |
| `GET subjects` | 7 | 6 | 5.6 KB | |
| `GET academic-years/{id}/periods` | 7 | 7 | 1.0 KB | |
| `GET sections/{id}/timetable` | 14 | 10 | 7.9 KB | |
| `GET me/timetable` (teacher) | 7 | 6 | 6.8 KB | |
| `GET me/teaching` (teacher) | 6 | 5 | 0.8 KB | |
| `GET students` (admin) | 12 | 16 | 16.1 KB | 50 of 5,050 |
| `GET students?search=ku` | 12 | 15 | 15.5 KB | leading-wildcard LIKE |
| `GET students?class_id=` / `?section_id=` | 12 | 16 / 11 | 16 KB | |
| **`GET students` (teacher, `view_assigned`)** | 12 | **391** | 16.1 KB | 50 of 1,894; see [3.6](#36-explain-slow-plans-and-missing-indexes) |
| `GET students/{id}` | 13 | 19–20 | 0.7 KB | |
| `GET students/{id}/scores` | 14 | 12–13 | 2.4 KB | computed on every call |
| `GET admissions` | 10 | 9 | 24.9 KB | 50 of 200 |
| `GET admissions/{id}` | 8 | 5 | 0.5 KB | |
| `GET academic-years/{id}/exams` | 7 | 16 | 0.4 KB | `marks_count` scans every mark |
| `GET exams/{id}` | 8 | 11 | 0.2 KB | same |
| `GET exams/{id}/marks` (45 students) | 13–14 | 9–11 | 7.3 KB | |
| Same three reads through the SPA cookie (`me`, `students`, `academic-years/{id}`) | 6 / 13 / 11 | 14 / 25 / 22 | | each includes the session UPDATE |

### 3.4 Writes

| Endpoint | Queries | Breakdown |
| --- | --- | --- |
| `PUT exams/{id}/marks`, 45 new | **149** | 45 × (SELECT, INSERT mark, INSERT audit) + 14 |
| … 45 unchanged | 60 | still one SELECT per student |
| … 45 changed / 45 cleared | 149 / 149 | 45 × (SELECT, UPDATE or DELETE, INSERT audit) + 14 |
| `POST students` (2 guardians + enrollment) | 39 | about 6 per guardian in `LinkGuardian`, 4 audit INSERTs |
| `POST admissions/{id}/admit` | 45 | as above plus the application lock and consent |
| `PUT classes/{id}/subjects` | 15 | 6 are the `exists` rule run once per subject id |
| `PUT sections/{id}/timetable/{w}/{p}` | 15 | |
| `POST users`, `PUT users/{id}/subject` | 14, 13 | |
| `POST academic-years` | 9 | |

### 3.5 N+1s and per-row work

- **No N+1 in any read endpoint.** Strict mode logged zero lazy loads at 5,000 students as well as in the existing suite, and query counts did not grow with rows. Controllers eager-load consistently.
- **Per-row work sits in the writes.**
  - Marks sheets cost 3 queries per student (2 when unchanged). Attendance sheets don't exist.
  - `LinkGuardian` costs about 6 queries per guardian.
  - Array `exists` validation rules run one query per item.

### 3.6 EXPLAIN: slow plans and missing indexes

| Query | Plan | Actual (EXPLAIN ANALYZE) | Fix |
| --- | --- | --- | --- |
| **`Student::visibleTo` for `view_assigned`**: COUNT plus page | Materialises all 5,000 current enrollments, then runs two **dependent EXISTS** per row: on `section_subject_teachers` (by section, filter teacher) and `timetable_entries` (36 of the teacher's slots read per row, 4,741 times) | **211 ms COUNT + 228 ms page** | Resolve the teacher's section IDs first (3 small queries, cacheable per teacher and year, which is v3.2 step 6), then `section_id IN (…)`. **Measured on the same data: 8.5 ms for the IDs + 10 ms COUNT + 16 ms page.** Add `(teacher_id, section_id)` on `timetable_entries` and `section_subject_teachers` so the ID lookup is index-only. The same scope runs in `StudentPolicy::view` and the marks sheet, but by one student or one section, so it is fast there (under 1 ms). |
| `withCount('marks')` on exams | Dependent subquery counting through the unique index: 21–32k rows per exam | 3.9–5.4 ms per exam, linear in marks (about 15 ms per exam at 15k students) | `withExists('marks')`: the flag only decides whether delete is allowed |
| `GET students` default, `ORDER BY name LIMIT 50` plus `COUNT(*)` | Table scan + filesort (priority queue) | 4.7 ms at 5k, linear | Acceptable. Cursor pagination removes the COUNT. |
| `name LIKE '%term%' OR admission_no LIKE 'term%'` | Table scan | 2.4–3.5 ms at 5k, linear | Acceptable now. A FULLTEXT (ngram) index is an option at 15k+. |
| `academic-years/{id}`, `sections/{id}` section counts | Dependent subqueries on covering indexes, 130 loops | under 0.25 ms | none |

**Already indexed and fast:**

- marks-sheet lookups (`exam_id, enrollment_id, subject_id`)
- scores (`exam_marks.enrollment_id`)
- `me/timetable` (`teacher_id, weekday, period_id`)
- `me/teaching` (`teacher_id`)
- section rosters (`section_id, roll_no`)
- capacity checks
- admissions by year
- sessions, tokens, cache keys

**Not measurable:** attendance and fee queries, because the tables don't exist. When they are built, v3.1's `UNIQUE (enrollment_id, date, period)` is the right key for attendance upserts.

### 3.7 Computed values

- **Score out of 5.** Computed on every `GET students/{id}/scores` by `ConsolidateSubjectScores`, from every mark the student has in every year: 14 queries, about 12 ms for 18 marks. Nothing is stored or cached. Any class-wide view (report cards, rank lists) would cost that once per student.
- **Fee balances.** There is no fee module, so nothing is computed. v3.1's rule "balances are derived from entries, never stored as an editable column" still holds for a projection rebuilt from the ledger, once a ledger exists.

### 3.8 Auditing

- **Setup:**
  - owen-it 14.0.6, database driver, `audits` table, written **synchronously** (`queue.enable => false`).
  - `console => true`, `threshold => 0` (never pruned).
  - Events: created, updated, deleted, restored. The user resolver checks the `web` and `sanctum` guards; the IP, user-agent and URL resolvers are on.
- **Audited models:** `User` (except password and remember token), `Student`, `Guardian` (except phone and phone hash), `StudentEnrollment`, `ExamMark`.
- **Not audited:** role assignments, `Exam`, `ExamPaper` (maximum-mark changes), `AcademicYear`, `Section` (class teacher), `SectionSubjectTeacher`, `TimetableEntry`, `AdmissionApplication`.
- **Bypassed:** query-builder bulk writes skip auditing entirely. One INSERT is added per audited row written.

### 3.9 Sanctum, sessions and rate limiting

- **Sanctum `last_used_at`:** one UPDATE on `personal_access_tokens` per bearer request. Sanctum 4.3 only offers on/off (`sanctum.last_used_at`), so throttling means turning it off and writing from a `TokenAuthenticated` listener at most every N minutes. Tokens never expire.
- **SPA sessions:** one SELECT and one UPDATE of `sessions` per request, in the client database. Signing in regenerates the session (DELETE + INSERT).
- **Rate limiting:** see [3.2](#32-fixed-cost-of-every-request). The general API limiter is not applied.

### 3.10 State unsafe under Octane

| State | Where | Under Octane |
| --- | --- | --- |
| `OrganizationContext` (settings memo) | `scoped` in `AppServiceProvider` | **Safe already**: Octane flushes scoped instances per request |
| Spatie `PermissionRegistrar` (in-memory role→permission map) | package singleton, `register_octane_reset_listener => false` | **Unsafe**: a worker keeps the map it loaded, so a role edit made through another worker is invisible until restart. Set it to true. |
| owen-it `Auditable::$auditingDisabled`, `Audit::$auditingGloballyDisabled` | static | Safe in practice: only `withoutAuditing()` (try/finally) in `DemoSchoolSeeder` changes them |
| `ClientEnvironment::apply` (env repository, storage path) | once per worker at boot | Safe: one client per deployment |
| `CreateClient::asClient`, `ClientDatabase::run` (runtime `config()` writes) | console only | Not reachable over HTTP; must stay that way |
| Entitlements, middleware, actions, the Sanctum guard | resolved per request | Safe |
| New in v3.2: `AcademicYearContext`, cached teacher section IDs | — | Must be `scoped`, never singletons or statics |

### 3.11 Tests

There are 14 feature test files and 142 tests:

- `Academic/AcademicStructureTest`
- `Academic/TimetableTest`
- `Admission/AdmissionTest`
- `AuditTrailTest`
- `Auth/AuthenticationTest`, `UserManagementTest`, `UserSeederTest`
- `BrandingAndModulesTest`
- `DemoSchoolSeederTest`
- `Deployment/ClientCommandsTest`
- `SpaTest`
- `Student/EnrollmentTest`, `StudentTest`, `StudentVisibilityTest`

There are no unit tests and no SPA tests.

**Routes with no test (20 of 61):**

- **Examination, all 9 routes:** exam list, create, show, update, delete; paper maximum; marks sheet GET and PUT; `students/{id}/scores`. That means `ExamPolicy` and the score formula are untested.
- **Subject teachers:** `PUT sections/{id}/subjects/{subject}/teacher`, `PUT users/{id}/subject`, `GET me/teaching`. Visibility *through a subject-teacher assignment* is also untested; only class-teacher and timetable visibility are.
- **Academic:** `PATCH academic-years/{id}`, `GET academic-years/{id}/classes`, `PATCH classes/{id}`, `GET sections/{id}`, `GET subjects`, `PATCH subjects/{id}`.
- **Other:** `GET admissions/{id}`, `POST auth/logout`.

**Untested behaviour:** the general API rate limit (it isn't applied), query counts, strict mode, and request isolation under Octane.

## 4. Proposed order of work

One commit each, with the full suite green before every commit (about 5 minutes per run today). Each step also updates the v3.2 document once it exists.

1. **Safety net**
   1. Strict mode outside production.
   2. Query-count tests for every list endpoint (flat when rows double).
   3. Characterization tests for exams, marks and scores.
   4. Characterization tests for subject teachers and the other untested routes.
2. **Redis**
   1. Client-code prefixes for Redis, cache and sessions, with a test that two client codes never share a key.
   2. Redis drivers for sessions and cache in client env generation; database drivers stay for local and tests.
   3. Queues on Redis with Horizon (`critical`, `default`, `bulk`).
   4. Rate limiter on Redis, `throttleApi()`, login limiter review.
   5. Throttled `last_used_at`.
3. **Academic year**
   1. `academic_year.start_month` with per-type defaults and `--year-start-month`.
   2. First year created automatically.
   3. `AcademicYearContext`, `SelectedAcademicYear` and the cached current year, replacing the five `is_current` readers.
   4. `POST academic-years/next` with copy-forward.
   5. Retire the create and edit endpoints and the SPA form.
   6. `AcademicYearSwitcher`, with the five SPA pages switched to the selected year.
4. **Subjects and teachers**
   1. `subject_teacher` pivot and data copy.
   2. `PUT users/{id}/subjects` and `PUT subjects/{id}/teachers`, with the all-years blocking rule replaced.
   3. `PUT classes/{id}/subjects/{subject}/teacher`.
   4. `is_override` with timetable defaults and backfill.
   5. Workload matrix endpoint.
   6. Subjects page.
   7. Subject teachers page, with the 50-user picker bug fixed by a teachers-only endpoint.
   8. `DemoSchoolSeeder`.
5. **Minimal attendance and fee modules** (added Oct 25; scope proposed for your approval before building)
   1. Attendance: `attendance_records` and `holidays`, a section-day sheet saved with one bulk upsert, the edit window.
   2. Fees: a minimal ledger per enrollment with dues, payments and gapless receipts.
6. **Database**
   1. Teacher-visibility rewrite and its two indexes; `withExists` for exams.
   2. Bulk marks upsert with bulk audit rows.
   3. Queued audits.
   4. `student_subject_scores` projection, job and nightly check.
   5. `fee_balances` projection, job and nightly check.
   6. Cursor pagination.
7. **Caching**
   1. Settings and terminology.
   2. Branding with ETag.
   3. `/me`.
   4. A teacher's section IDs.
   5. Timetables, with bulk deletes converted first.
8. **Octane**
   1. Spatie reset listener and scoped contexts.
   2. Two-requests-one-worker test.
9. **Load testing**
   1. `LoadTestSeeder`.
   2. k6 scripts for morning attendance, results day, fee due date and the login storm.

Steps 1 and 2 don't depend on open questions. Steps 3, 4 and 5 each start by settling the questions below that affect them.

## 5. Questions for you

**Answered on Oct 25** (see [Decisions](#decisions-oct-25-2026)):

1. ~~Can a teacher teach more than one subject?~~ Yes, sometimes: build the pivot.
2. ~~Is the 10k–15k target per client or across all clients?~~ Across all clients.
3. ~~Where is `docs/architecture-v3.2.md`?~~ Create it from v3.1.
4. ~~Attendance and fees?~~ Build minimal modules inside v3.2.
5. ~~New dependencies?~~ Keep the current ones; add one only when a step needs it.
6. ~~Redis isolation?~~ One shared Redis, a per-client prefix and a per-client ACL user.
8. ~~Academic year defaults?~~ Start months June / July / April; names like `2026-27`; copy-forward copies classes, sections, class subjects and periods; everything follows the selected year.
13. ~~Login rate limit?~~ 300 a minute per IP; 5 a minute per email + IP.
14. ~~General API limit?~~ 120 a minute per user, 600 a minute per guest IP.
7. **Existing clients' `.env` files need new keys.** Should ops edit them by hand, or should I add a command, for example `edunexus:client:env abc --set=…`?

**Needed before the step they affect:**

8. **Academic year (step 3):**
   - What is the default start month per institution type? For schools, CBSE uses April and Tamil Nadu boards June; the demo data uses June.
   - What is the name format? Demo data uses "2026-2027", the UI hint says "2026-27", and admission numbers use "2026-27".
   - What does copy-forward copy: classes, sections and capacities, class teachers, class subjects, periods, subject teachers, timetable?
   - Does `/next` make the new year current?
   - Is the selected year sent as a header, a query parameter or session state?
   - Does teacher visibility follow the selected year or stay on the current one?
   - What happens when today falls in no year?
9. **Retired endpoints** (`POST`/`PATCH academic-years`, `PUT users/{id}/subject`): return 410 now, or keep them for one release for older mobile builds?
10. **`PUT classes/{id}/subjects/{subject}/teacher`:** does it set the teacher on every section of the class, overwriting section-level assignments? Or is it a class default that sections inherit?
11. **Timetable defaults (step 4):** when a subject teacher changes, should non-override slots follow? If the new teacher is busy in some of those slots, should the request fail, or skip those slots and report them?
12. **Cursor pagination (step 5):** which lists? Is it fine to lose page numbers and totals in the SPA?
13. **Login rate limit.** The per-IP limit is 30 per minute, but a school's staff usually sign in from one NAT IP, as would a single k6 machine. Should it be per email + IP only, or should the per-IP limit be raised?
14. **General API limit:** attach `throttle:api` (120 per minute per user, which v3.1 already claims) in step 2?
15. **Terminology:** should the `institution.terminology` setting become the source (cached, editable later), or should the unused setting be removed?
16. **`users.subject_id`:** keep it for one release after the pivot and drop it in v3.3?
17. **Marks audit trail:** bulk upserts fire no model events. Is writing the audit rows ourselves in bulk acceptable, with the same table, format and old/new values?
