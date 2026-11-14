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
