# EduNexus ERP — Architecture Plan v3

Oct 1, 2026 · Umapathi

**Revision note.** v3 replaces the v2 multi-tenant design (one deployment for every school, school picked from the hostname, central database). EduNexus now follows the BizFlow model: one deployment per client, configured by that client's own env files (`<code>.env` and `<code>.env.secure`) and backed by its own MySQL database and user. Sections on the data model, fees and examinations are unchanged from v2.

## Summary and locked principles

EduNexus is one Laravel codebase that runs as one client at a time. A client (a school, college or coaching centre) gets its own environment files, its own MySQL database and its own MySQL user, and a running deployment serves exactly that client.

1. One codebase, one Docker image; each client runs it as its own deployment.
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
| 5. Migrate and seed | Connected as the new client user: runs all migrations, then `DatabaseSeeder`, which runs the institution-type seeder |
| 6. Create the admin | A Super Admin; the password is printed once, or asked for with `--ask-password` |
| 7. Summary | Prints the client code, institution, database, login URL and Super Admin email |

Other options: `--admin-name` (default "Super Admin") and `--url` (default `EDUNEXUS_CLIENT_URL`, `http://localhost:8000`). If any step from 2 to 6 fails, the database, MySQL user and files are removed again.

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

**In containers.** Each client is one service running the same image with `EDUNEXUS_CLIENT=<code>` and its client directory mounted. A `make up CLIENT=abc` target and the Docker setup are not built yet.

## Institution types

`--type` sets what kind of institution a client is. It decides default modules, seeded roles and terminology. Everything else is shared.

| Type | Default modules (besides the core set) | Roles seeded with Super Admin | Terminology (class / section / teacher / head) |
| --- | --- | --- | --- |
| `school` | Examination, Transport | Admin, Principal, Teacher, Accountant, Guardian, Student | Class / Section / Teacher / Principal |
| `college` | Examination, Library, Hostel | Admin, Principal, Teacher, Accountant, Student | Semester / Division / Faculty / Principal |
| `coaching` | none | Admin, Teacher, Accountant, Guardian, Student | Course / Batch / Tutor / Centre head |

The core set for every type is Academic, Admission, Student, Staff, Attendance, Fee, Communication and Report. The definitions live in `App\Support\Deployment\InstitutionType`. `InstitutionTypeSeeder` syncs the roles and stores the terminology in the `settings` table under `institution.terminology`.

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
│   ├── Academic/       # years, classes, sections, subjects, timetable
│   ├── Admission/
│   ├── Student/        # students, guardians, enrollments, promotion
│   ├── Staff/
│   ├── Attendance/
│   ├── Examination/
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
| `students` | admission_no, name, dob, gender, photo | Identity only, no class column |
| `guardians` | name, phone, email, user_id | One login sees all linked children |
| `guardian_student` | guardian_id, student_id, relation, is_primary | Enables sibling discounts |
| `student_enrollments` | student_id, academic_year_id, section_id, roll_no, status | Unique on (student_id, academic_year_id) |
| `attendance_records` | enrollment_id, date, period, status, marked_by | Unique on (enrollment_id, date, period) |
| `holidays` | academic_year_id, date, name | Excluded from attendance percentages |

**Promotion workflow.** At year end, an admin bulk-promotes sections into the next year's sections, marking each student promoted, detained, or left. Leaving issues a transfer certificate. This creates new enrollments and never edits old ones.

**Attendance edits.** Teachers can edit within a configurable window (default same day). Later edits need admin rights and are audited.

## Fees and finance ledger

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

Exam structure, grading and report cards are data, not code, because CBSE, state board and matriculation schools all differ.

- **Assessment scheme** per class per year: terms, exams within each term, and their weightage toward the final result.
- **Components** per subject: theory, practical, internal, each with max marks and pass marks.
- **Grading scales** as rows (min %, max %, grade, grade point), selectable per scheme.
- **Co-scholastic areas** (discipline, art, sports) graded on their own scale.
- **Report card templates** per scheme, rendered to PDF in a queued job.
- **Mark locking:** once results are published, marks are locked; unlocking needs admin rights and is audited.

## Auth, RBAC, users, frontend and mobile

**No registration.** A client's first and only seeded user is its Super Admin, created by `edunexus:client:create`. Users with the `user.manage` permission create every other login and assign roles through the API (`POST /api/v1/users`, `PUT /api/v1/users/{id}/roles`).

**RBAC.** Spatie Permission in the client database, guard `web` for both cookie and token requests. Permissions are fixed in code (`App\Domain\Identity\Enums\Permission`, e.g. `attendance.mark`, `fee.collect`). Roles are seeded per institution type and editable by the client; reseeding never overwrites a client's edits. A teacher's access is further scoped by policy to the sections they teach.

**Super Admin rules.**

- Super Admin and Admin always hold every permission.
- Only a Super Admin can grant the Super Admin role or change another Super Admin's roles.
- A client can never lose its last Super Admin; the request fails validation.

**Web (Vue 3 SPA).** Served by the same deployment at every non-API path, so auth is same-origin and needs no CORS. Sanctum cookie auth: `GET /sanctum/csrf-cookie`, then `POST /api/v1/auth/login`. Sessions are stored in the client database; cookies are host-only (`SESSION_DOMAIN=null`). Branding (name, type, terminology, logo, theme) comes from the public `GET /api/v1/branding`.

**Mobile (Flutter).** Signs in with `POST /api/v1/auth/tokens` and sends a Sanctum bearer token. Tokens live in the client database, so a token from one client never works on another. How the app finds a client's URL is an open question (see below), because there is no central school directory in this model.

## Modules and settings

| Kind | Lives in | Who changes it | Example |
| --- | --- | --- | --- |
| Module | Institution type defaults, plus `EDUNEXUS_MODULES_ENABLE` / `EDUNEXUS_MODULES_DISABLE` in the client `<code>.env` | EduNexus operations, per deployment | Enable `hostel` for one college |
| Client setting | Client DB `settings` table | Client admins | Attendance edit window, logo |
| Deployment setting | Client `<code>.env` | EduNexus operations | `APP_URL`, `RECEIPT_PREFIX`, mail sender |

Module overrides are comma-separated module codes, e.g. `EDUNEXUS_MODULES_ENABLE=hostel,library`. Unknown codes are ignored. `Entitlements` resolves the final list, `GET /api/v1/me` returns it for the SPA menu, and the `entitled:<module>` route middleware blocks a disabled module's endpoints. Billing and subscription state are handled outside the app.

## Queues, notifications, storage and audit

Every client has its own queue tables, cache and workers, because they live in its own database and deployment.

**Queues.** Separate queues by priority: `critical` (OTP, payment receipts), `default` (notifications), `bulk` (fee reminders, imports, report PDFs). Run Horizon or `queue:work` per client deployment.

**URLs in background work.** Mails, WhatsApp messages and PDFs build links from the client's `APP_URL`.

**Notifications.** One `NotificationService` routes events (`StudentMarkedAbsent`, `FeeDue`, `ResultPublished`) to push (FCM), SMS, email and WhatsApp Business. Templates and channel choices are per client. Each send is logged with status for delivery reports.

**Storage.** One private S3 bucket per client, or one bucket with a per-client path prefix (`clients/{code}/...`), set in the client `<code>.env`. Files are served only through short-lived signed URLs generated after a policy check. Never public URLs for student documents. Locally, all clients still share `storage/`; a per-client storage path is not built yet.

**Audit.** `owen-it/laravel-auditing` on marks, attendance edits, fee entries, concessions, role changes and user and student record edits. Changes made from queued jobs and commands are audited too. Stores user, old and new values, IP and time; password and remember-token changes are never stored. Audit rows older than 3 years move to cold storage.

## Security and compliance

EduNexus holds minors' personal data, so India's DPDP Act requirements for children's data apply from the first client onboarded.

- **Consent:** capture verifiable guardian consent at admission, stored with timestamp and version of the notice shown.
- **Encryption:** Aadhaar number, medical notes and guardian phone numbers use Laravel encrypted casts with the client's own `APP_KEY`. Store a hash alongside any field you need to search on.
- **Minimisation:** collect Aadhaar only if the client needs it; make it optional by default.
- **Retention:** define how long records of students who left are kept, then anonymise. Document it in the client contract.
- **Isolation:** each client's MySQL user is granted rights on its own database only. An automated test checks that the user cannot read another database.
- **Secrets:** `<code>.env.secure` holds the APP_KEY and DB credentials, is created with mode `0600`, and is never committed here. The provisioning credentials stay out of client deployments.
- **Rate limits:** sign-in is limited per email and IP (5 per minute) and per IP (30 per minute); the API is limited per user or IP.
- **Tests:** the suite refuses to run unless the database name ends in `_test` and the client prefix contains `test`, because it resets the database and drops test client databases and users.

Confirm the exact DPDP obligations with a lawyer before launch; the rules were still being phased in as of mid-2026.

## Infrastructure and operations

Run on AWS ap-south-1 (Mumbai) with managed data stores; no databases in containers.

| Component | Service | Notes |
| --- | --- | --- |
| Edge / TLS | ALB or Cloudflare | One hostname per client, e.g. `abc.edunexus.in` |
| Web/API | One ECS service per client, PHP-FPM + Nginx, same image | `EDUNEXUS_CLIENT=<code>`; client files from the deployment repo or a secrets manager |
| Workers | One worker service per client | Queues in the client database |
| Scheduler | One `schedule:run` task per client | |
| Database | RDS MySQL, one instance, one database and user per client | Move a client to another instance by changing its `DB_HOST` |
| Files | S3 | Per-client bucket or prefix |

**Onboarding a client.** Run `edunexus:client:create` with the provisioning credentials, commit the generated files to the deployment repository, add the client's service and hostname, and deploy.

**Deploys.** CI builds one image. Deploy runs `edunexus:client:migrate --all` (or `migrate` inside each client's service), then rolls the client services. Migrations must stay backward compatible with the previous release, because clients migrate one after another.

**Backups.** RDS automated snapshots for disaster recovery, plus a nightly per-client `mysqldump` to S3 so one client can be restored or exported without touching others. Test a single-client restore every quarter.

## MVP scope and roadmap

Ship a v1 a client can run daily on, then add modules only when a paying client asks.

1. **Phase 1, platform (built):** client creation and lifecycle commands, per-client env files and database, institution types, Super Admin, user and role management, Sanctum login for SPA and mobile, audit, SPA shell with login, dashboard and users. Remaining: Docker setup with `make up CLIENT=abc`, per-client local storage.
2. **Phase 2, institution core:** academic years, classes, sections, subjects, timetable, admissions, students, guardians, enrollments. Exit: one pilot client fully set up.
3. **Phase 3, daily operations:** attendance, fees ledger with Razorpay, receipts, guardian notifications. Exit: pilot client collects one full fee term in EduNexus.
4. **Phase 4, academics:** exams, grading, report cards, homework, guardian mobile app. Exit: one term's report cards issued from EduNexus.
5. **Phase 5, year-end:** promotion workflow, transfer certificates. Exit: pilot client rolls over to the next academic year.
6. **Later, on demand:** transport, library, HR and payroll, inventory, hostel.

**Open questions.**

- Which board's grading (CBSE, Tamil Nadu State Board, matriculation) the pilot school follows, since it shapes Phase 4 defaults. It could become a seeded setting per institution type.
- How the Flutter app finds a client's URL now that there is no central directory: one white-label build per client, a QR code or URL entered on first launch, or a small separate directory service.
- Where client files live in production: a private `edunexus-deployment` repository, a secrets manager, or both (settings in git, secrets in the manager).
