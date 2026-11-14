# EduNexus v4 — Product spec (draft, round 3)

Draft. Built from `docs/analysis-v4.md` with the owner's round-3 confirmations applied. Nothing here is built yet, except where a screen is marked as existing. One item is still open: live clients (question 14, §9.1).

Written for: the owner (product decisions) and the engineers who build v4.

## 1. Roles

Roles are seeded per institution type (see the analysis, §2). Super Admin and Admin always hold every permission. Insights visibility (decision D16) is the same for Super Admin and Admin.

| Role | Who | Home | Main jobs | Permissions it needs (existing names) | Insights |
| --- | --- | --- | --- | --- | --- |
| Super Admin | the client's first user | Settings | roles, users, year setup | every permission | everything |
| Admin | office staff with broad rights | Settings and Home | setup, people, finance | every permission | everything |
| Principal | head of the institution | Home (Principal) | oversight, insights, approvals | all except `role.manage`, `settings.manage` | everything |
| Teacher | class teacher and subject teachers | Home (Teacher) | daily attendance, marks, own timetable, own numbers | `attendance.view`, `attendance.mark`, `exam.view`, `exam.enter_marks`, `academic.view`, `student.view_assigned` | own numbers; class teachers also see their class-teacher sections |
| Accountant | fee office | Home (Accountant) | collection, receipts, defaulters | `fee.view`, `fee.collect`, `fee.reverse`, `student.view` | none |
| Guardian | parent or guardian (mobile, not built) | Home (Guardian) | child's attendance, marks, fees | pending (see §9) | not in v4.0 |
| Student | student login (not built) | — | — | pending (see §9) | not in v4.0 |

Teachers see only students in their sections, in the selected year (`student.view_assigned`). Guardian phone numbers stay masked unless the user holds `student.manage`.

## 2. The six areas

Navigation is organised by what a role does each day, not by database table. Each area lists its screens. Screens marked *existing* are in the SPA today.

| Area | Purpose | Screens |
| --- | --- | --- |
| Home | role-specific start page | Home (Teacher), Home (Principal), Home (Admin), Home (Accountant), Home (Guardian, mobile) |
| Daily | the day's work | Attendance (*existing*), My timetable (*existing*), Substitutions (later), Staff attendance (Phase 3) |
| People | who is in the institution | Students (*existing*), Student profile (*existing*), Staff (Phase 3), Guardians, Admissions (*existing*) |
| Assessment | exams and results | Terms (Phase 2), Exams (Phase 2), Marks entry (Phase 2), Results (Phase 2), Report cards (later) |
| Finance | fees | Fee structures, Dues, Collection, Receipts, Defaulters (Phase 4) |
| Insights | performance | Student performance, Class performance, Teacher performance (Phase 5) |
| Settings | the institution's setup | Year setup (checklist), Classes and sections, Subjects, Bell schedule, Roles, Users (*existing*), Client settings |

Area names are for navigation only. Routes keep the existing paths where a page already exists; new pages get new paths under the area.

## 3. Screens

For each screen: purpose, who sees it, main actions, data source. Status is what exists today.

### 3.1 Home

**Home (Teacher)** — *partial.* Purpose: what to do today. Who: Teacher. Actions: go to today's attendance for each section they are responsible for; see own timetable for today; see marks due (exams with a `marks_due_on` in the next 14 days, a fixed constant and not a setting, for their subjects). Data: `me/timetable`, `me/teaching`, attendance sheets for the day, exams with `marks_due_on`.

**Home (Principal)** — *partial.* Purpose: the institution at a glance. Who: Principal. Actions: open Insights, see today's attendance by section (sheets not yet submitted are shown), pending admissions. Data: attendance sheets for the day (new endpoint), admissions by status, fee collection today (after Finance).

**Home (Admin)** — *partial.* Purpose: setup progress and open tasks. Who: Admin, Super Admin. Actions: continue Year Setup, open admissions. Data: Year Setup status (new), admissions.

**Home (Accountant)** — *blocked.* Purpose: collections today and defaulters. Who: Accountant. Actions: collect payment, open dues. Data: fee entries and receipts (after Finance is committed).

**Home (Guardian, mobile)** — *blocked.* Purpose: each child's attendance, marks and dues. Who: Guardian. Data: guardian login (not built). Pending (see §9).

### 3.2 Daily

**Attendance** — *existing; changes in v4.* Purpose: mark a section's school day. Who: class teacher and subject teachers of the section (`attendance.mark`); anyone with `attendance.edit_late` can change any day. Actions: pick section and date; set status (present, absent, late, half day, leave) and remark per student; mark the rest present; save. The first save records when the sheet was submitted and by whom. Changes:

- Attendance is per day. The per-period option is not in v4.0.
- A holiday, a weekly off day (`weekly_off_days`, default Sunday) or a future date cannot be marked. The message names the reason.
- Leave is excluded from the attendance percentage (see §8, release note).

Data: `sections/{s}/attendance`, `me/teaching`, `attendance_sheets` (new).

**My timetable** — *existing.* Purpose: a teacher's week. Who: any teacher. Actions: view the week. Data: `me/timetable`.

**Substitutions** — *later (O16).* Purpose: a teacher covers another's period for one day. Who: Admin, Principal.

**Staff attendance** — *Phase 3.* Purpose: mark staff present or absent per day. Who: Admin, Principal (`staff.manage`). Data: `staff_attendance` (new). Per day.

### 3.3 People

**Students** — *existing.* Purpose: find and add students. Who: `student.view` (all) or `student.view_assigned` (own sections). Actions: search; filter by class and section; add a student with guardians and an optional section. Data: `students`, year structure.

**Student profile** — *existing.* Purpose: one student's record. Who: as above. Actions: edit details; link or unlink a guardian; enrol or change section; see attendance, scores (lifetime) and, from Phase 5, term results. Data: `students/{s}`, `students/{s}/attendance`, `students/{s}/scores`, enrollments.

**Staff** — *Phase 3.* Purpose: every non-student employee. Who: Admin, Principal (`staff.view`); editing needs `staff.manage`. Actions: add staff (name, phone, designation, department, joining date); link a login (required for anyone who teaches, O10); mark leaving date. Data: `staff` (new). The first list is created from existing users with the Teacher, Principal, Admin or Accountant role; Guardian, Student and Super Admin accounts are not copied.

**Guardians** — *partial.* Purpose: the people responsible for each student. Who: `student.manage`. Actions: see guardians for a student (in the profile today); add or unlink. A guardian list page is not planned for v4.0. Data: `guardians`, `guardian_student`.

**Admissions** — *existing.* Purpose: enquiries to admission. Who: `admission.manage`. Actions: record enquiry; approve, reject (with reason), withdraw; admit into a section with recorded consent. Data: `admissions`, year structure.

### 3.4 Assessment

**Terms** — *Phase 2.* Purpose: the parts of the academic year. Who: `academic.manage` to change, `academic.view` to read. Actions: rename terms, change their dates, add or remove terms within the rules for the institution type. Defaults per institution type (owner, round 2):

| Institution | Default terms |
| --- | --- |
| School | 3 terms |
| College | 2 semesters |
| Coaching | 1 "Full year" term |

Names and dates are editable per client. Default dates are an even split by months (accepted, §9). The Year Setup terms step shows the dates, and an admin must confirm them before exams can be created in that year. Data: `terms` (new).

**Exams** — *existing (API only; SPA is new, Phase 2).* Purpose: plan the year's exams. Who: `exam.view` (read), `exam.manage` (write). Actions: create exam with name, term, dates, maximum marks, and an optional marks due date (`marks_due_on`); set a subject's maximum for one class (paper); edit; delete (only if it has no marks). Data: `academic-years/{y}/exams`, `exams/{e}`, `exams/{e}/classes/{c}/subjects/{sub}/max-marks`.

**Marks entry** — *existing (API only; SPA is new, Phase 2).* Purpose: enter one subject's marks for one section. Who: `exam.manage` (any section) or `exam.enter_marks` as that section's subject teacher. Actions: enter marks from 0 to the paper's maximum, or mark absent; clear a mark; save the sheet. The first save records the sheet's submitter and time. Data: `exams/{e}/marks` GET and PUT, `exam_mark_sheets` (new). Saves in one bulk write.

**Results** — *partial.* Purpose: the outcome of an exam. Who: `exam.view`, plus the student view rules. Actions: view a class's results for one exam, all subjects, with each student's total and percentage. Data: `exams/{e}/marks` (one subject at a time today); a class results endpoint is new (Phase 2).

**Report cards** — *later.* Purpose: the printed term result. Needs grading scales, components, templates and a PDF job. Not in v4.0 scope.

### 3.5 Finance

Finance screens depend on the staged fee work being committed (analysis §1, row 2). Money is stored in paise. Gateway payments (Razorpay) are later (owner, round 2).

**Fee structures** — *blocked.* Purpose: what each class pays, per fee head and installment. Who: `fee.collect` to change, `fee.view` to read. Actions: create fee heads; set amounts per class per year; set installments. Data: fee heads and structures (structures are not yet in the code; `fee-heads` is staged).

**Dues** — *partial.* Purpose: what each student owes. Who: `fee.view`. Actions: post dues for a structure to students in a section or class. Data: `fee-dues` (POST), `students/{s}/fees` (staged).

**Collection** — *partial.* Purpose: take a payment. Who: `fee.collect`. Actions: pick a student; enter amount, method (cash, cheque, UPI, card), reference; record. Partial payments allowed; allocated to oldest dues first. Idempotent on a key so a double click does not double-post. Data: `enrollments/{e}/payments` (POST, staged).

**Receipts** — *partial.* Purpose: proof of payment. Who: `fee.view`. Actions: view and print a receipt. Receipt numbers are gapless per financial year. Data: `receipts/{r}` (staged). PDF is not built.

**Reversals** — *partial.* Purpose: refund or cheque bounce. Who: `fee.reverse`. Actions: reverse an entry with a reason; the ledger keeps both rows. Data: `fee-entries/{f}/reverse` (staged).

**Defaulters** — *blocked.* Purpose: students with dues past the due date. Who: `fee.view`. Actions: filter by class, section, days overdue; export. Data: report over fee balances (not built).

### 3.6 Insights

Insights show performance. Each number has a rule and a source (§5). Who sees what (owner, round 2; decision D16):

| Viewer | Student performance | Class performance | Teacher performance |
| --- | --- | --- | --- |
| Principal, Admin, Super Admin | every student | every section | every teacher |
| Class teacher | students in their class-teacher sections | their class-teacher sections | own numbers only |
| Subject teacher (not class teacher) | none | none | own numbers only |
| Accountant | none | none | none |

**Student performance** — *Phase 5.* Purpose: how one student is doing this term. Shows: attendance percentage for the term (leave excluded; §5.1); subject percentages for the term; trend per subject; section rank if the client turns it on (off by default); an at-risk flag using the client's thresholds (defaults in §5.1); the lifetime score out of 5 next to the term score (owner, round 2). Data: `student_term_summaries`, `student_subject_term_scores`, `students/{s}/attendance`.

**Class performance** — *Phase 5.* Purpose: how a section or class is doing. Shows: attendance by section and day range; subject averages per section; the at-risk list. Data: the same projections, by section.

**Teacher performance** — *Phase 5.* Purpose: a teacher's own work in the term. Shows only their own numbers (owner, round 2):

- attendance on time (sheets they were responsible for, §5.2);
- marks entered on time (exams with a due date, §5.2);
- periods per week;
- their own sections' subject averages, **without** comparison to other sections (§9, T1);
- their own attendance rate (needs staff attendance, Phase 3).

Principal and Admin see every teacher's numbers. Data: `teacher_term_summaries` (new), `attendance_sheets`, `exam_mark_sheets`, `timetable_entries`, `student_subject_term_scores`, `staff_attendance` (Phase 3).

### 3.7 Settings

**Year setup** — *partial.* Purpose: get a new year ready. Who: `academic.manage`. Shows the checklist in §4. Data: existing year, class, subject, period and holiday endpoints, plus a status endpoint (Phase 1).

**Classes and sections** — *partial.* Purpose: the structure of each class. Who: `academic.manage`. Actions: add, rename, change capacity, delete an empty class or section, set the class teacher (each change of class teacher is recorded with a date). Data: `classes`, `sections`, `class_teacher_assignments` (new). Editing an existing class or section needs a UI (the API exists).

**Subjects** — *existing.* Purpose: the subject list and who teaches each. Who: `academic.manage` to change. Data: `subjects`, `subjects/{s}/teachers`.

**Bell schedule** — *partial.* Purpose: the periods of the day. Who: `academic.manage`. Actions: add, edit, delete a period; mark breaks. A period in use cannot be deleted. Data: `periods`.

**Roles** — *blocked.* Purpose: what each role may do. Who: Super Admin (`role.manage`). Actions: change a role's permissions. Data: a permission write endpoint (not built; `roles` is read-only today).

**Users** — *existing.* Purpose: logins. Who: `user.manage`. Actions: create a user, set roles, set subjects a teacher teaches. Data: `users`, `roles`, `users/{u}/subjects`.

**Client settings** — *blocked (Phase 1, step 1.5).* Purpose: values that change how the institution works. Who: Admin (`settings.manage`). Keys and defaults:

| Setting | Default | What it does |
| --- | --- | --- |
| School timezone | Asia/Kolkata | used for every local time (cut-offs, school days) |
| Attendance cut-off time | 10:00 | a sheet saved by this time counts as on time |
| Attendance edit window (days) | 0 | how far back a teacher can change attendance |
| Weekly off days | Sunday | attendance cannot be marked on these days; they are excluded like holidays |
| Section rank | off | shows section rank on student performance |
| At-risk: attendance below | 75% | at-risk flag |
| At-risk: overall term percentage below | 35% | at-risk flag |
| At-risk: drop between two exams | 15 points | at-risk flag, in any subject |

Changing weekly off days does not change history: marks already saved stay, and no new attendance can be saved on the new off days.

## 4. Year Setup checklist

Shown on the Year Setup page and in the Admin home. Each step shows done, or what is missing. A step is done when its rule holds for the selected year.

| # | Step | Done when | Where to fix it |
| --- | --- | --- | --- |
| 1 | Open the year | the year exists and is current or open for setup | Year switcher, Make current, Open next year |
| 2 | Terms | the year has its terms, and an admin has confirmed their dates (defaults from the institution type, §3.4) | Terms |
| 3 | Classes and sections | every class has at least one section, with capacity | Classes and sections |
| 4 | Subjects per class | every class has at least one subject | Classes and sections |
| 5 | Class teachers | every section has a class teacher | Classes and sections |
| 6 | Subject teachers | every section subject has a teacher, and no teacher is over their workload | Subject teachers |
| 7 | Timetable | every section has a timetable covering each period it teaches | Timetable |
| 8 | Bell schedule | the year has periods and at least one teaching period | Bell schedule |
| 9 | Holidays and weekly off days | the year's holidays are added, and the weekly off days are set (default Sunday) | Academic setup, Client settings |
| 10 | Open admissions | an admissions round is open for the year | Admissions |

The checklist counts from existing data. It never blocks a step: an admin can open the next year while steps are open.

## 5. Performance definitions

Each metric has a formula, its sources, and when it is recomputed. Projections are rebuilt by queued jobs after commit and checked nightly. Projections are derived data: they can be deleted and rebuilt with no loss.

### 5.1 Student, per enrollment per term

**Attendance percentage** (leave excluded; late counts in full; half day counts as half):

```
attended = present × 1 + late × 1 + half_day × 0.5
marked   = present + late + half_day + absent        (leave is not counted)
percentage = attended ÷ marked × 100, one decimal; blank if marked = 0
```

Holidays and weekly off days are never in `marked`, because nothing can be marked on them. Source: `attendance_records`, `holidays`, the weekly off setting. Recomputed when a section's day is saved, and nightly.

**Subject percentage (scoped to the term)**

```
for each exam in the term with a mark for this subject, not absent:
    exam percentage = mark ÷ (paper maximum, or exam maximum) × 100
subject percentage = average of exam percentages, two decimals; blank if none
```

Source: `exam_marks`, `exams`, `exam_papers`. Recomputed on mark save (that section and subject), on a maximum change (the class's students), on exam date change, and nightly.

**Trend**

```
trend = latest exam percentage − previous exam percentage, same subject and term
```

Blank with fewer than two exams.

**Overall term percentage**: the average of the subject percentages (unweighted for v4.0).

**Section rank** (off by default): rank of the overall term percentage among the students of the section who have a value. Students without a value are unranked and shown as "—". Ties share a rank.

**At-risk flag**: true when attendance percentage is below 75%, or overall term percentage is below 35%, or any subject's trend is a drop of 15 points or more. All three are client settings (§3.7).

### 5.2 Teacher, per term

Measure only what the teacher controls. Each measure is shown with the count it is based on, and the items the teacher is not responsible for are reported separately.

| Measure | Formula | Source | Reported separately |
| --- | --- | --- | --- |
| Attendance on time | sheets the teacher submitted on time ÷ sheets they were responsible for. Responsible = for each section where they were class teacher on that day, each school day in the term up to today (not a holiday, not a weekly off day, in the school's timezone). On time = submitted by the teacher, on the school day, at or before the cut-off time | `attendance_sheets`, `class_teacher_assignments`, holidays, weekly off setting, school timezone | sheets marked by someone else; school days with no sheet by the end of the day |
| Marks entered on time | mark sheets the teacher entered by the deadline ÷ mark sheets they were responsible for. Responsible = each exam with a due date in the term, for each (section, subject) they teach where the class takes that subject. On time = entered on or before the due date, in the school's timezone | `exam_mark_sheets`, `exams.marks_due_on`, `section_subject_teachers` | sheets entered by someone else; sheets not entered |
| Periods per week | the teacher's periods on the timetable, breaks excluded, overrides included | `timetable_entries` where teacher_id | none |
| Own section averages | subject averages for the teacher's own sections, shown on their own page | `student_subject_term_scores` | none. No comparison with other sections is shown to teachers (§9, T1) |
| Own attendance | days present ÷ days marked, from the teacher's own staff record | `staff_attendance` (Phase 3) | none |

Teacher numbers are recomputed when a sheet is saved, a mark sheet is submitted, the timetable changes, or a class teacher changes, and nightly.

### 5.3 What the numbers do not measure

- Substitutes: attendance is credited to the person who submitted the sheet. Periods per week follow the timetable, so a substitute is not credited. A substitution model is later (O16).
- Mid-term section moves: attendance rows keep the section they were marked in, so a student's history is not moved to their new section.
- Holidays and weekly off days: never counted, because nothing can be marked on them.
- Absent students: attendance absent counts against the student. Leave is not counted either way. An absent exam mark is left out of the average, not counted as zero.

## 6. Edge cases the product must handle

| Case | Behaviour |
| --- | --- |
| Marking a day that is a holiday | refused, with the holiday's name |
| Marking a weekly off day | refused, with the weekly off day's name |
| Marking a future date | refused |
| Holiday added after a day was marked | refused; the day stays marked |
| Weekly off setting changed after marks exist | existing marks stay; no new marks on the new off days |
| Student moved to another section during the year | history stays in the old section; the new section starts from the move date |
| Class teacher changed mid-term | responsibility for on-time attendance follows the assignment day by day |
| Teacher with two subjects | counted once per section-day for attendance; subject averages are per subject |
| Exam with no term | left out of term results and listed on Year Setup |
| Exam with no due date | left out of the marks-on-time measure |
| Student with no marks in a term | no percentage; left out of averages and rank |
| Exam with marks | cannot be deleted |
| Period in use | cannot be deleted |
| Duplicate payment click | one payment only (idempotent key) |

## 7. Out of scope for v4.0

- Report cards and grading scales (later, after the Terms and score decisions in use).
- Guardian and student mobile logins (see §9).
- Promotion and transfer certificates (after report cards).
- Gateway payments (Razorpay), later (owner, round 2).
- Transport, library, hostel, payroll, inventory, communication.
- Per-period attendance (the `period` column is kept for it).

## 8. Changes to what exists

These behaviours change in v4. Users will notice them. The release notes are kept here, and in `docs/release-notes-v4.md` from the commit that changes leave counting (step 0.6).

- **Release note — attendance percentage.** Leave is no longer counted as an absence. A student's percentage rises where they have leave. Existing screens will show the new figure from the release.
- **Release note — attendance rules.** Weekly off days (default Sunday) can't be marked. Attendance sheets record who submitted them and when.
- **Release note — teacher performance.** Teachers see only their own numbers. Principal and Admin see comparisons.
- The navigation is regrouped into the six areas. Old paths redirect.
- The lifetime score out of 5 stays on the student profile for compatibility; term results are added beside it.
- Attendance records keep the section and academic year a student was in when marked.
- Exams belong to a term once terms exist.

## 9. Open items

### 9.1 Still open

- **Q14 — live clients.** Not answered again in round 3 ("[FILL IN]"). It decides how carefully the migration is rehearsed. Reply with client codes, or "none".
- **Guardian and student logins.** Not numbered. They are out of v4.0 scope. Raise them when the mobile stage is planned.

### 9.2 Confirmed in round 3

- **T1 — teacher section averages.** "Own numbers only, no peer ranking" is read as: a teacher sees their own sections' subject averages, with no comparison to other sections. Principal and Admin see the comparison. **Confirmed.** Teachers see their own sections' averages only; the comparison across sections is for Principal and Admin.
- **T2 — class-teacher history.** A `class_teacher_assignments` record keeps each change of class teacher with its date, so the on-time measure follows the right teacher day by day. **Accepted.** `sections.class_teacher_id` stays the current value; one action writes both.
- **T3 — default term dates.** Counts are from your answer. Default dates are proposed as an even split by months (school 4-month terms, college 6-month semesters, coaching one term). **Accepted.** The Year Setup terms step needs admin confirmation of the dates before exams can be created that year.
- **T4 — rule F, full text received.** Probes and tests use existing credentials only. Never create MySQL or Redis users, grant privileges, or use provisioning credentials outside the edunexus:client:* commands.
- **T5 — release notes.** **Accepted.** The notes stay in §8; `docs/release-notes-v4.md` is created in the commit that changes leave counting (step 0.6).
- **Constants and keys.** The 14-day window on the teacher home is a constant, not a setting. The setting key names in §3.7 are approved.

## 10. Decisions this spec relies on

| Decision | Answer |
| --- | --- |
| Attendance mode | per day; `period` column kept |
| Leave | excluded from the attendance percentage |
| Late and half day | 1 and 0.5 |
| Teacher numbers | own only, no peer ranking |
| Section rank | client setting, off by default |
| At-risk | attendance < 75%, overall < 35%, drop ≥ 15 points |
| Terms | school 3, college 2 semesters, coaching 1 "Full year" |
| Insights visibility | as in §3.6 |
| Lifetime score | kept beside term score |
| Teachers | need a login (v4.0) |
| Marks deadline | optional per exam |
| Attendance cut-off | 10:00 local, a client setting |
| School timezone | Asia/Kolkata, a client setting; stored times are UTC |
| Sundays | not holidays; weekly off days setting, default Sunday |
| Razorpay | later |
| Staff attendance | per day |
| Substitutions | later |
