# mod_oralinterview Dev Plan (Milestone 0)

## File Tree Vision
```
mod/oralinterview/
├── access.php                  # Moodle capability definitions
├── backup/
│   └── moodle2/                # Backup/restore definitions
├── classes/
│   ├── access.php              # helper methods for require_xxx
│   ├── audit.php               # audit logging service
│   ├── scoring.php             # final score calculation + summary
│   ├── email.php               # notifications + reminders
│   ├── privacy/
│   │   └── provider.php        # privacy API implementation
│   ├── renderer.php            # page rendering helpers
│   ├── task/
│   │   ├── reminder.php        # deadline reminder task
│   │   └── retention.php       # anonymization task
│   └── persistent/
│       ├── audit.php           # formal table wrappers
│       ├── candidate.php
│       ├── committee_member.php
│       ├── evaluation.php
│       ├── score.php
│       ├── session.php
│       ├── session_question.php
│       ├── template.php
│       └── template_question.php
├── classes/event/
│   ├── evaluation_reopened.php
│   ├── evaluation_submitted.php
│   ├── evaluation_started.php
│   ├── question_scored.php
│   ├── score_overridden.php
│   └── session_completed.php
├── classes/form/
│   ├── candidate_form.php
│   ├── session_form.php
│   ├── template_form.php
│   └── template_question_form.php
├── classes/output/
│   └── renderer.php
├── classes/task/               # scheduled task classes (reminders, retention)
├── completion.php
├── config.php
├── db/
│   ├── access.php
│   ├── events.php
│   ├── install.xml
│   ├── migrate.php             # future migrations stub
│   └── upgrade.php
├── evaluate.php                # committee evaluation UI
├── export.php                  # CSV export endpoint
├── index.php                   # course module index override
├── insert_instance.php         # add activity glue (optional)
├── lang/
│   ├── ar/oralinterview.php
│   └── en/oralinterview.php
├── lib.php                     # core activity implementation hooks
├── manage.php                  # HR overview
├── my.php                      # committee dashboard
├── template_edit.php
├── template_questions.php
├── templates/                  # Mustache templates
│   ├── manage.mustache
│   ├── my.mustache
│   ├── evaluate.mustache
│   └── export.mustache
├── version.php
└── view.php
```

## Database Schema (XMLDB Implementation)
Implemented nine additional tables plus an immutable audit log through `db/install.xml`. Each table matches its persistent wrapper:

1. `oralint_candidate` – stores external watchlist and exposes `fullname`, `email`, `phone`, masked `nationalid`, optional `linkeduserid`, plus auditing columns (`timecreated`, `timemodified`, `usermodified`). Index on `nationalid`.
2. `oralint_template` – templates per course with `name`, `jobtitle`, `description`, `status`, `maxscore`, and tracking columns. Indexed by `courseid`.
3. `oralint_template_q` – template question bank; each row includes `questiontext`, `rubric`, `maxscore`, `sortorder`, plus FKs to `oralint_template`. Indexed by `templateid` and `(templateid,sortorder)`.
4. `oralint_session` – sessions referencing course, activity, candidate, template plus timestamps, `requireall`, `status`, final aggregates (`finalscore`, `finalpercent`, `committeecount`, `questioncount`) and `formula_version`. Indexed by `courseid`, `candidateid`, `status`.
5. `oralint_session_q` – frozen question snapshot per session, storing `questiontext`, `rubric`, `maxscore`, `sortorder`, indexed by `sessionid`.
6. `oralint_committee` – committee roster per session with `userid`, `role`, `status`, linked to session via FK and indexed by session/user.
7. `oralint_eval` – per-user evaluation status, optional `finalscore`, `submitreason`, and auditing columns; indexes on `sessionid` and `userid`.
8. `oralint_score` – per-question scoring rows (`evaluationid`, `sessionqid`, `score`, `comment`) with indexes on evaluations and questions.
9. `oralint_audit` – immutable log with `action`, `entity`, `oldvalue`, `newvalue`, `reason`, `ip`, `crud`, timestamps; indexed by `sessionid` and `userid`.

Dependencies are tracked through Moodle’s XMLDB FKs where feasible (template/question/session snapshots/evaluations). `db/upgrade.php` stubs the future migration path. Persistence wrappers live in `classes/local/persistent/*` for each table.

1. `oralint_candidate` (external candidate master)
   - `id` (pk)
   - `fullname`, `email`, `phone`, `nationalid` (masked output)
   - `linkeduserid` (optional, allows mapping a Moodle user)
   - `timecreated`, `timemodified`, `usermodified`
   - Index on `nationalid` for imports.

2. `oralint_template`
   - `id`, `courseid`, `name`, `jobtitle`, `description`, `status` (draft/active/archived)
   - `timecreated`, `timemodified`, `usermodified`
   - `maxscore` default 10 per question scope.

3. `oralint_template_q`
   - `id`, `templateid`, `questiontext`, `rubric`, `maxscore` (`default` 10), `sortorder`
   - `timecreated`, `timemodified`, `usermodified`
   - Index on `templateid` + `sortorder`.

4. `oralint_session`
   - `id`, `courseid`, `oralinterviewid`, `candidateid`, `templateid`, `interviewdate`, `deadline`, `requireall` (bool)
   - `status` (draft/active/completed/locked)
   - `finalscore`, `finalpercent`, `committeecount`, `questioncount`, `formula_version`
   - `timecreated`, `timemodified`, `usermodified`
   - Indexes: `courseid`, `candidateid`, `status`.

5. `oralint_session_q`
   - `id`, `sessionid`, `questiontext`, `rubric`, `maxscore`, `sortorder`
   - Snapshots when session activated.
   - Index on `sessionid` + `sortorder`.

6. `oralint_committee`
   - `id`, `sessionid`, `userid`, `role` (member/lead)
   - `status` (invited/accepted)
   - `timecreated`, `timemodified`
   - Unique key (`sessionid`, `userid`).

7. `oralint_eval`
   - `id`, `sessionid`, `userid`, `status` (notstarted/inprogress/submitted/locked), `finalscore`, `submitreason`
   - `timemodified`, `timecreated`, `usermodified`
   - Index on (`sessionid`, `userid`).

8. `oralint_score`
   - `id`, `evaluationid`, `sessionqid`, `score` (0-10), `comment`
   - `timecreated`, `timemodified`, `usermodified`
   - Index on `evaluationid`, `sessionqid`.

9. `oralint_audit`
   - `id`, `sessionid`, `userid`, `action` (score_saved/override/reopen/export/lock), `entity` (score/evaluation/session)
   - `oldvalue`, `newvalue`, `reason`, `ip`, `crud`, `timecreated`
   - Immutable log for every state change.

## Capabilities & Role Mapping
| Capability | Purpose | HR/Admin Roles | Committee Roles |
|------------|---------|----------------|-----------------|
| `mod/oralinterview:addinstance` | Add activity | Manager, HR | - |
| `mod/oralinterview:manage` | Full HR management | Manager, HR | - |
| `mod/oralinterview:managecandidates` | Candidate CRUD & import | Manager, HR | - |
| `mod/oralinterview:evaluate` | Submit scores | - | Interviewer, Lead |
| `mod/oralinterview:viewown` | View own assigned sessions | - | Interviewer, Lead |
| `mod/oralinterview:viewall` | HR view sessions | Manager, HR | - |
| `mod/oralinterview:export` | CSV/PDF export | Manager, HR | - |
| `mod/oralinterview:lock` | Lock session | Manager, HR | - |
| `mod/oralinterview:recalculate` | Recompute final score | Manager, HR | - |
| `mod/oralinterview:override` | Override scores/reopen | Manager, HR | - |
| `mod/oralinterview:reopen` | Reopen submitted eval | Manager, HR | - |

## Page-by-Page UX Flow
### HR Screens
1. `manage.php` (Dashboard)
   - Shows summary widgets (active sessions, pending evaluations, overdue deadlines)
   - Filters by course, candidate, status
   - Actions: Activate session (snapshot questions), Recalculate final, Open report PDF/CSV, Lock/Unlock session.
2. `templates.php` + `template_edit.php` + `template_questions.php`
   - List templates, statuses.
   - Form for name, jobtitle, description.
   - Question sub-page for CRUD on question text, rubric, maxscore.
3. `candidates.php` + `candidate_edit.php`
   - List of external candidates; nationalid masked except partial for HR.
   - Import CSV: preview rows, validate duplicates, map columns.
4. `session_edit.php` + `session_questions.php`
   - Form binding template + candidate + committee user selectors.
   - Activation (draft → active) triggers question snapshot and evaluation records.
5. `export.php`
   - HR selects session, csv options (include comments, include masked fields), downloads.

### Committee Screens
1. `my.php`
   - Lists assigned sessions filtered by status, with call-to-action to evaluate.
2. `evaluate.php`
   - Shows session summary, candidate (masked nationalid), list of session questions.
   - Each row: question text, rubric, score (0..10), optional comment.
   - Draft save button (permits partial), final submit button (all questions scored) with confirmation.
   - On submit, lock evaluation for user; session final computed when criteria met.

## Calculation Rules & Edge Cases
- `FinalScore = SUM(scores across all submitted evaluations) / (N * Q)` where N = committee count, Q = number of session snapshot questions (maxscore assumed 10 per question). This produces normalized 0..10 result.
- `FinalPercent = (FinalScore / MaxScorePerQuestion) * 100` (maxscore is config per question, default 10).
- On override: store HR `reason`, old values, new values in `oralint_audit` and recalc final immediately.
- `Requireall` flag: when true, final result is locked until all committee `oralint_eval.status = submitted`. When false HR can recalc with partial data.
- Drift safeguards: question snapshot prevents future edits from altering stored evaluations.
- Missing data: treat unsaved score as `NULL`; submission blocked if any question lacks score.
- Percent rounding: keep 2 decimals.

## Events to Trigger
- `evaluation_started` (when committee opens evaluate.php first time)
- `question_scored` (each individual question save)
- `evaluation_submitted` (when committee submits complete attempt)
- `evaluation_reopened` (HR reopens evaluation)
- `score_overridden` (HR override change)
- `session_completed` (when final score locked and session marked completed)

## Settings
- **Site-level (probably in `settings.php`)**
  - `oralinterview/requirecomments` (boolean: require comments for scores)
  - `oralinterview/masknationalid` (boolean: mask sensitive data by default)
  - `oralinterview/retentiondays` (integer: retention before anonymization)
  - `oralinterview/messagingreminder` (days before deadline for reminders)
- **Instance-level (per module)**
  - `enable_percent_display` (bool)
  - `allow_export_comments` (bool) per course module.

## Security & Compliance Checklist
- [ ] Require `require_login()` + `require_capability()` at every page entry.
- [ ] Inspect `sesskey` for all POST/submit actions (score save, submit, import, overrides, exports).
- [ ] Capability checks aligned so only HR sees candidate data and exports; members limited to `viewown` and `evaluate`.
- [ ] Committee never sees other committee scores or comments (enforce by filtering `oralint_score` by evaluation id and `userid`).
- [ ] Audit log immutable: insert-only `oralint_audit`, log old/new values + reason + ip.
- [ ] Candidate nationalid masked except partial display for HR; anonymization task removes after retention.
- [ ] Privacy provider declares external candidate data, audit logs, evaluation data; supports export/delete of committee actions.
- [ ] CSV export sanitizes strings, obeys mask settings, enforces `require_capability('mod/oralinterview:export')`.
- [ ] All data mutations use Moodle `$DB` layer + persistent classes.
- [ ] Session activation snapshots question data to avoid template drift.
- [ ] Notifications (message API) triggered via `message_send` for assignment/reminder actions; use `classes/event` for logging/triggers.
- [ ] Use `moodleform` + mustache templates for consistent UIs; avoid direct HTML across pages.
- [ ] Provide backup/restore for candidates, templates, sessions, evaluations to preserve data.
- [ ] Scheduled tasks enforce retention/anonymization and deadline reminders; tasks must respect `siteconfig`.

## Done Criteria (Milestone 0)
## Project Status (Milestone 12)
- Events created, privacy provider implemented, scheduled tasks + notifications present.
- Reporting/exporting completed; override/reopen/lock with audit trail now functional.
- Unit test added (`tests/scoring_test.php`), README & settings documented.
- `mod/oralinterview/DEVPLAN.md` exists with all required sections.
- File tree enumerated; DB schema defined with all fields/relationships.
- Capabilities mapped with role expectations.
- UX flow defined for HR vs committee, including CSV export and evaluation restrictions.
- Calculation rules, events, settings, and security checklist documented.
