# EduCore ERP — Architecture Plan v2

Oct 1, 2026 · Umapathi

## Summary and locked principles

EduCore runs as one Laravel deployment that serves every school on its own domain, with one MySQL database per school. This replaces the v1 plan of one deployment per school, which does not scale past a handful of tenants.

1. One codebase, one deployment, one Docker image for all schools.
2. Tenant is identified from the request hostname, never from `.env`.
3. One database per tenant, plus one central database for tenants, domains and billing.
4. Every school has a free subdomain (`abc.educore.in`) and may add a custom domain (`erp.abcschool.com`).
5. API-first: Vue 3 SPA and Flutter apps both consume the same Laravel API. No Blade portals.
6. Business logic lives in domain Actions; controllers stay thin.
7. Subscription entitlements live in the central DB, not in the tenant DB.
8. Enrollment, not student, is the anchor for attendance, marks and fees.
9. Fees are a ledger: immutable entries, gapless receipt numbers, idempotent payments.
10. No tenant-specific `if ($tenant === ...)` logic anywhere. Differences are settings or flags.
11. Every queued job, mail and URL carries tenant context explicitly.
12. Children's data is encrypted where sensitive and governed by a retention policy.

## Tenancy model

One app serves every school; the request hostname picks the tenant, and the tenant picks its database.

```text
  abc.educore.in      erp.abcschool.com      erp.xyzschool.in
        │                    │                      │
        └────────────────────┼──────────────────────┘
                             ▼
           Edge TLS (Cloudflare or Caddy) + ALB
                             │
                             ▼
          ┌─────────────────────────────────────┐
          │  Laravel app on ECS (web + workers)  │
          │  resolves host → tenant, switches DB │
          └─────────────────────────────────────┘
       lookup host │          │          │          │
                   ▼          ▼          ▼          ▼
            ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐
            │Central DB│ │school_abc│ │school_xyz│ │  Redis + S3  │
            │ tenants, │ │students, │ │  same    │ │ keys & paths │
            │ domains, │ │fees,     │ │ schema,  │ │ prefixed by  │
            │ billing  │ │marks     │ │ isolated │ │   tenant     │
            └──────────┘ └──────────┘ └──────────┘ └──────────────┘
```

Adding a school creates a tenant row, a database and a domain row. No new server, container or deploy.

| Database | Holds | Migrations |
| --- | --- | --- |
| Central (`educore_central`) | `tenants` (code, name, db_name, db_host, status), `domains` (domain, is_primary, verified_at), plans, subscriptions, platform admins | `database/migrations/central` |
| Tenant (`school_<code>`) | All school data: users, students, enrollments, attendance, exams, fees, audits, settings | `database/migrations/tenant`, run per tenant |

No tenant table ever references another tenant, and the central DB never stores student data.

## Domains and SSL

Every school gets an instant subdomain at signup and can later point its own domain at it. Both resolve to the same tenant through the `domains` table.

| Domain type | Example | DNS | Certificate | Onboarding |
| --- | --- | --- | --- | --- |
| Default subdomain | `abc.educore.in` | One wildcard `*.educore.in` record | One wildcard cert (ACM) | Instant |
| Custom domain | `erp.abcschool.com` | School adds CNAME to `abc.educore.in` | Issued per domain on demand | After verification |

**Custom domain flow.** The school admin enters the domain in settings. EduCore stores it unverified and shows the CNAME to add. A scheduled job checks DNS, sets `verified_at`, and the domain goes live. Unverified domains never resolve.

**Certificates.** ALB has a per-listener certificate quota, so custom domains should not each be added to the ALB. Use one of:

- Cloudflare for SaaS custom hostnames in front of the ALB. Cloudflare issues and renews certs per school.
- Caddy with on-demand TLS behind an NLB. Its `ask` endpoint must approve only verified rows in `domains`, or anyone can make you issue certs.

**Rules.**

- Unknown host returns a plain 404. Never fall back to a default school.
- `SESSION_DOMAIN` stays null so cookies are host-only and never cross schools.
- Each tenant has one `is_primary` domain. All generated URLs use it.
- Changing or removing a custom domain falls back to the subdomain, which can never be deleted.

## Request lifecycle

The hostname is user input, so tenant resolution is the first security boundary on every request.

1. Request arrives at `erp.abcschool.com/api/v1/attendance`.
2. `IdentifyTenantByDomain` looks up the host in `domains` (Redis-cached, 10 min TTL, busted on domain change). No verified match returns 404.
3. Tenant status is checked. Suspended or expired returns 403 with a billing message.
4. The `tenant` DB connection is switched to the tenant's database; cache, storage and queue are prefixed by tenant ID.
5. `OrganizationContext` is populated with the tenant (code, name, settings, primary domain).
6. Sanctum authenticates the user against the tenant DB.
7. Entitlement middleware checks the module is in the school's plan.
8. Policy checks the user's permission.
9. Form Request validates input.
10. Controller calls one Action (e.g. `MarkAttendance`), which writes through Eloquent to the tenant DB.

Use `stancl/tenancy` for steps 2 to 4 rather than hand-rolling connection switching, queue context and storage prefixes.

## Application structure and layering

Each domain module owns its models, actions, policies and events. There are no top-level `app/Models`, `app/Jobs` or `app/Policies` folders competing with the modules.

```text
app/
├── Central/            # runs on central DB only
│   ├── Tenant/         # tenants, domains, provisioning
│   └── Billing/        # plans, subscriptions, entitlements
├── Domain/             # runs on tenant DB
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
│   └── Requests/
└── Support/            # OrganizationContext, money, sequences
```

**Layers.** Controller → Form Request → Action → Eloquent. No repository layer over Eloquent. Add a Data object (Spatie Laravel Data) only where input crosses boundaries awkwardly, such as bulk imports.

**Migrations.** `database/migrations/central` and `database/migrations/tenant` are separate paths. Tenant migrations run per tenant through the tenancy package's artisan command.

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
6. **Receipts:** issued per payment with a gapless number per financial year.
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

## Auth, RBAC, frontend and mobile

The Vue SPA is served from each school's own domain and calls `/api` on that same domain, so auth is same-origin and needs no CORS.

**Web (Vue 3 SPA).** Sanctum cookie auth. Because domains are dynamic, set Sanctum's stateful domain to the current request host after tenant resolution, instead of a static `SANCTUM_STATEFUL_DOMAINS` list. School logo, name and colors load from a public `/api/v1/branding` endpoint.

**Mobile (Flutter).** One app in the stores for all schools. On first launch the user enters a school code or searches by name. The app calls the central endpoint `api.educore.in/v1/schools/lookup?code=ABC`, which returns the school's primary domain, name and logo. The app stores it and sends all further requests to that domain with Sanctum bearer tokens. White-label apps per school are an optional paid add-on later, not the default.

**RBAC.** Spatie Permission in each tenant DB. Permissions are fixed in code (`attendance.mark`, `fee.collect`); roles are seeded (Admin, Principal, Teacher, Accountant, Guardian, Student) and editable by the school. A teacher's access is further scoped by policy to the sections they teach.

**Platform staff.** Your own support access goes through a central-DB super admin that impersonates a tenant user, with every impersonation logged. No shared passwords in tenant DBs.

## Feature flags and billing

What a school paid for is decided in the central DB; how a school uses what it has is decided in its own DB.

| Kind | Lives in | Who changes it | Example |
| --- | --- | --- | --- |
| Entitlement | Central DB (`plans`, `subscriptions`, `tenant_features`) | You, or billing automatically | Transport module, student limit 1,000 |
| School setting | Tenant DB (`settings`) | School admin | Attendance edit window, receipt prefix |
| Rollout flag | Laravel Pennant, scoped to tenant | You | Beta timetable UI for 2 schools |

Entitlements are cached per tenant in Redis and checked by middleware on the module's routes and in the SPA menu. When a subscription lapses, the tenant moves to read-only for a grace period, then suspended. Data is never deleted on lapse.

## Queues, notifications, storage and audit

All schools share one Redis and one worker pool; every job carries its tenant ID and re-initialises tenancy before running.

**Queues.** Separate queues by priority: `critical` (OTP, payment receipts), `default` (notifications), `bulk` (fee reminders, imports, report PDFs). Horizon monitors them. A large school's bulk job must not delay another school's OTP, so bulk jobs are chunked and rate-limited per tenant.

**URLs in background work.** Mails, WhatsApp messages and PDFs build links from the tenant's primary domain, never from `APP_URL`. Add a test that fails if any tenant-context URL contains the central domain.

**Notifications.** One `NotificationService` routes events (`StudentMarkedAbsent`, `FeeDue`, `ResultPublished`) to push (FCM), SMS, email and WhatsApp Business. Templates and channel choices are per school. Each send is logged with status for delivery reports.

**Storage.** One private S3 bucket, paths prefixed `tenants/{tenant_id}/...`. Files are served only through short-lived signed URLs generated after a policy check. Never public URLs for student documents.

**Audit.** `owen-it/laravel-auditing` on marks, attendance edits, fee entries, concessions, role changes and student record edits. Stores user, old and new values, IP and time. Audit rows older than 3 years move to cold storage.

## Security and compliance

EduCore holds minors' personal data, so India's DPDP Act requirements for children's data apply from the first school onboarded.

- **Consent:** capture verifiable guardian consent at admission, stored with timestamp and version of the notice shown.
- **Encryption:** Aadhaar number, medical notes and guardian phone numbers use Laravel encrypted casts. Store a hash alongside any field you need to search on.
- **Minimisation:** collect Aadhaar only if the school needs it; make it optional by default.
- **Retention:** define how long records of students who left are kept, then anonymise. Document it in the school contract.
- **Isolation tests:** an automated test logs in as School A and attempts School B's IDs and domain; it must always fail.
- **Rate limits:** login, OTP and the mobile school lookup endpoint are rate-limited per IP and per tenant.
- **Secrets:** tenant DB credentials are generated at provisioning and stored encrypted in the central DB, or one app DB user with grants per database.

Confirm the exact DPDP obligations with a lawyer before launch; the rules were still being phased in as of mid-2026.

## Infrastructure and operations

Run on AWS ap-south-1 (Mumbai) with ECS Fargate services and managed data stores; no databases in containers.

| Component | Service | Notes |
| --- | --- | --- |
| Edge / TLS | Cloudflare for SaaS (or Caddy + NLB) | Wildcard + custom hostnames |
| Load balancer | ALB | Routes all hosts to the web service |
| Web/API | ECS service, PHP-FPM + Nginx | Horizontal autoscaling on CPU |
| Workers | ECS service running Horizon | Separate service so queues scale independently |
| Scheduler | ECS task, one instance | `schedule:run`, tenant-aware |
| Database | RDS MySQL, one instance, many databases | Central DB + one DB per tenant |
| Cache / queue | ElastiCache Redis | Keys prefixed by tenant |
| Files | S3 private bucket | Tenant-prefixed paths |

**Tenant provisioning** (one Action, used by signup and admin): create tenant row → create database → run tenant migrations → seed roles, permissions, grading defaults → create subdomain row → create first admin user → send welcome mail.

**Deploys.** CI builds one image. Deploy runs central migrations, then tenant migrations across all tenants in batches, then rolls the ECS services. Migrations must be backward compatible with the previous release, since tenants migrate one after another.

**Backups.** RDS automated snapshots for disaster recovery, plus a nightly per-tenant `mysqldump` to S3 so one school can be restored or exported without touching others. Test a single-tenant restore every quarter.

**Scale trigger.** When one RDS instance gets busy, add a second and record each tenant's `db_host` in the central DB. The tenant model supports this from day one.

## MVP scope and roadmap

Ship a v1 a school can run daily on, then add modules only when a paying school asks.

1. **Phase 1, platform:** tenancy, domains, provisioning, billing entitlements, RBAC, audit, isolation tests. Exit: two test schools on separate domains with no data leakage.
2. **Phase 2, school core:** academic years, classes, sections, subjects, timetable, admissions, students, guardians, enrollments. Exit: one pilot school fully set up.
3. **Phase 3, daily operations:** attendance, fees ledger with Razorpay, receipts, guardian notifications. Exit: pilot school collects one full fee term in EduCore.
4. **Phase 4, academics:** exams, grading, report cards, homework, guardian mobile app. Exit: one term's report cards issued from EduCore.
5. **Phase 5, year-end:** promotion workflow, transfer certificates, custom domains. Exit: pilot school rolls over to the next academic year.
6. **Later, on demand:** transport, library, HR and payroll, inventory, hostel.

Open question: which board's grading (CBSE, Tamil Nadu State Board, matriculation) the pilot school follows, since it shapes Phase 4 defaults.
