You are Cursor acting as a senior Moodle core developer + senior full-stack engineer (PHP 8.1+, Moodle 4.4 APIs, MySQL/PostgreSQL). Build a production-grade Moodle 4.4 activity module plugin named mod_oralinterview that implements an Oral Interview / Selection Committee scoring system with EXTERNAL candidates (not Moodle users). You must be extremely detailed, do not skip steps, and follow Moodle coding style and architecture.

IMPORTANT RULES:
- Target Moodle 4.4 (PHP 8.1+).
- Candidate is external: stored in plugin table. Candidate has NO Moodle login.
- Committee members + HR/Admin are Moodle users.
- Committee members must NOT see other members’ scores/comments.
- Provide full audit trail for all score changes and admin overrides (immutable log table).
- Use Moodle APIs: XMLDB install.xml, capabilities, events, privacy API, backup/restore, mustache templates, moodleform, $DB access layer, sesskey, require_login, require_capability.
- Do NOT use Workshop/Quiz reuse. This is a custom mod_ plugin.

WORKFLOW SUMMARY:
- HR creates Templates (question sets).
- HR creates Candidates (external) (supports CSV import).
- HR creates Interview Sessions (template + candidate + committee list).
- On session activation: snapshot template questions into session_questions (freeze Q).
- Each committee member opens their assigned session, sees questions, enters score 0..10 for each question, optionally comment, then submits. Locked after submit.
- When all members submitted (or as per config), system computes Final Score:
  Final = SUM(all scores) / (N * Q)  (normalized 0..10) + optionally percent
- HR can view full breakdown and export (CSV required; PDF optional).
- HR can override with reason; overrides must log old/new values in audit log.
- Privacy: mask nationalid, allow retention/anonymization.

========================================================
MILESTONE 0 — PROJECT SETUP & TECH PLAN (NO CODING YET)
========================================================
Goal:
- Produce a clear plan + file tree + DB schema design + UI map + permissions map for Moodle 4.4.

Tasks:
0.1 Decide plugin structure:
- Create mod_oralinterview plugin (activity module).
- Define the main pages: view.php, evaluate.php, my.php, manage.php, candidates.php, templates.php, session_edit.php, template_edit.php, export.php, etc.
0.2 Define capabilities (db/access.php) and who has them.
0.3 Define all DB tables and relationships (install.xml plan).
0.4 Define events list and when to trigger each.
0.5 Define settings (site-level + instance-level).
0.6 Define “Done criteria” per milestone.

Deliverable at end of Milestone 0:
- A document in the repo named /mod/oralinterview/DEVPLAN.md containing:
  - File tree (complete list of files to be created)
  - DB schema (tables, fields, keys, indexes)
  - Capabilities list with roles mapping (HR/Admin vs Committee)
  - Page-by-page UX flow (HR screens + committee screens)
  - Calculation rules + edge cases
  - Security checklist
No code changes beyond creating the empty plugin folder and DEVPLAN.md.

========================================================
MILESTONE 1 — SKELETON MOODLE PLUGIN (BOOTSTRAP)
========================================================
Goal:
- Installable Moodle 4.4 activity module skeleton that appears in “Add an activity”.

Tasks:
1.1 Create required plugin files:
- version.php (declare moodle 4.4 requirements and PHP 8.1)
- lib.php (stubs for add/update/delete, features, grade stubs)
- mod_form.php (basic instance form)
- db/install.xml (empty minimal table: oralinterview instance)
- db/access.php (capabilities stub)
- lang/en/oralinterview.php + lang/ar/oralinterview.php (minimum strings)
- view.php (landing page with require_login + context)
- index.php (list instances)
- styles.css (optional)
1.2 Ensure plugin installs without errors.

Deliverable at end of Milestone 1:
- Plugin installs on Moodle 4.4.
- HR can add “Oral Interview” activity to a course.
- view.php loads and displays a placeholder page.
- Capabilities exist (even if not fully used yet).
- Language strings load (English + Arabic).

========================================================
MILESTONE 2 — DATABASE IMPLEMENTATION (FULL XMLDB)
========================================================
Goal:
- Implement all required DB tables and upgrade strategy.

Tasks:
2.1 Implement db/install.xml with all tables:
- oralint_candidate
- oralint_template
- oralint_template_q
- oralint_session
- oralint_session_q (snapshot)
- oralint_committee
- oralint_eval
- oralint_score
- oralint_audit
Include:
- timecreated/timemodified/usermodified where relevant
- indexes for performance: sessionid, candidateid, userid, status
- foreign keys (where feasible with Moodle XMLDB)
2.2 Add db/upgrade.php for future changes (initial stub but correct format).
2.3 Add classes/local/persistent/* or classes/data/* for each table using Moodle persistent pattern (recommended):
- candidate.php, template.php, template_question.php, session.php, session_question.php, committee_member.php, evaluation.php, score.php, audit.php

Deliverable at end of Milestone 2:
- Fresh install creates all tables.
- A developer can run basic $DB insert/select using the persistent classes.
- Documented schema in DEVPLAN.md updated with final XML fields.

========================================================
MILESTONE 3 — CAPABILITIES, SECURITY, NAVIGATION
========================================================
Goal:
- Enforce access control properly across pages.

Tasks:
3.1 Finalize db/access.php:
- mod/oralinterview:addinstance
- mod/oralinterview:manage
- mod/oralinterview:managecandidates
- mod/oralinterview:evaluate
- mod/oralinterview:viewown
- mod/oralinterview:viewall
- mod/oralinterview:export
- mod/oralinterview:lock
- mod/oralinterview:recalculate
- mod/oralinterview:override
- mod/oralinterview:reopen
3.2 Implement helper functions in classes/local/access.php:
- require_manage()
- require_evaluate()
- require_viewall()
- assert_evaluation_owner($evalid, $USER->id)
3.3 Add navigation items:
- For HR: “Manage”, “Templates”, “Candidates”, “Reports”
- For committee: “My Interviews”

Deliverable at end of Milestone 3:
- Committee cannot access HR pages.
- Committee cannot access other members’ evaluation records (IDOR protected).
- HR can access all pages.
- All POST actions require sesskey.

========================================================
MILESTONE 4 — HR: TEMPLATES MANAGEMENT (CRUD)
========================================================
Goal:
- HR can create/edit templates and questions (no Moodle Question Bank).

Tasks:
4.1 Create templates.php (list/search templates)
4.2 Create template_edit.php with moodleform:
- name, jobtitle, description
4.3 Create template_questions.php:
- add/edit/delete question text, rubric, sortorder, maxscore (default 10)
- ordering UI (simple up/down is ok; drag-drop optional later)
4.4 Render with mustache templates + renderer class.

Deliverable at end of Milestone 4:
- HR can create a template, add 10 questions, reorder them, edit, delete.
- Data saved in oralint_template and oralint_template_q.

========================================================
MILESTONE 5 — HR: CANDIDATE MANAGEMENT (EXTERNAL) + CSV IMPORT
========================================================
Goal:
- HR can manage external candidates.

Tasks:
5.1 candidates.php list page with filters (name/email/nationalid masked)
5.2 candidate_edit.php moodleform:
- fullname, email, phone, nationalid, optional linkeduserid
5.3 CSV import:
- upload CSV: fullname,email,phone,nationalid
- validate, show preview, import results summary
5.4 Masking:
- nationalid must be masked everywhere except HR (and even HR sees partially in lists).

Deliverable at end of Milestone 5:
- HR can create and import candidates.
- Committee cannot see candidate nationalid.
- Candidate records stored in oralint_candidate.

========================================================
MILESTONE 6 — HR: SESSION CREATION + COMMITTEE ASSIGNMENT + SNAPSHOT
========================================================
Goal:
- HR can create sessions and assign committee members; snapshot questions when session starts.

Tasks:
6.1 session_edit.php moodleform:
- choose template
- choose candidate
- choose committee users (multi-select)
- interview date, deadline
- requireall flag
6.2 When session moves from draft -> active:
- snapshot template questions into oralint_session_q
- create oralint_eval rows for each committee member with status notstarted
6.3 manage.php dashboard:
- list sessions + status
- show committee members and progress

Deliverable at end of Milestone 6:
- HR can create a session and assign 3 committee members.
- Snapshot is created and remains fixed even if template is edited later.
- Committee members see the session in “My Interviews”.

========================================================
MILESTONE 7 — COMMITTEE: MY INTERVIEWS + EVALUATION UI (CORE)
========================================================
Goal:
- Build the evaluation UI exactly as needed.

Tasks:
7.1 my.php for committee:
- list assigned sessions (notstarted/inprogress/submitted)
7.2 evaluate.php:
- loads evaluation attempt for current user and session
- displays questions (either one-by-one or list; pick best and implement cleanly)
- score input integer 0..10 + optional comment
- save draft (updates oralint_score lines)
- submit:
  - verify all questions have scores
  - mark evaluation submitted + lock
7.3 Ensure committee cannot see:
- other members’ totals
- final score (until allowed)
- any confidential HR-only fields
7.4 Add autosave optional (nice-to-have).

Deliverable at end of Milestone 7:
- Committee member can complete evaluation and submit.
- After submit, editing is blocked unless HR reopens.
- Scores stored in oralint_score and status in oralint_eval.

========================================================
MILESTONE 8 — FINAL SCORE CALCULATION + SESSION COMPLETION
========================================================
Goal:
- Compute final score after committee completion.

Tasks:
8.1 ScoringService::compute_final_score($sessionid):
- N = count(committee members included)
- Q = count(session questions)
- Sum = sum of all score rows for submitted evaluations
- Final = Sum / (N*Q)
- Percent = (Final/maxscore)*100
8.2 Trigger calculation:
- when last member submits (if requireall)
- or allow HR “Recalculate” button
8.3 Store snapshot of N, Q, formula version in session row.
8.4 Show final score on HR session report page.

Deliverable at end of Milestone 8:
- System computes final correctly and stores it.
- HR sees final score and breakdown.
- If requireall=true, final remains pending until all submitted.

========================================================
MILESTONE 9 — AUDIT LOG + OVERRIDE/REOPEN/LOCKING
========================================================
Goal:
- Government-grade audit for every modification.

Tasks:
9.1 AuditService:
- log(action, entity, oldvalue, newvalue, reason, sessionid, userid)
9.2 Log on:
- score save
- submit
- reopen
- override
- committee changes
9.3 HR override UI:
- HR can edit a member’s score lines only with reason
- old/new recorded in oralint_audit
9.4 Reopen evaluation:
- HR can reopen submitted attempt with reason
- committee member can re-submit
9.5 Lock session:
- once completed, lock unless HR unlocks with reason

Deliverable at end of Milestone 9:
- Any change is traceable with who/when/why.
- Override requires reason.
- Reopen works and recalculation updates final score.

========================================================
MILESTONE 10 — REPORTING + EXPORT (CSV REQUIRED)
========================================================
Goal:
- HR reports and exports.

Tasks:
10.1 report.php / session_report.php:
- session header (candidate, job, date)
- committee list + status
- per member totals (HR only)
- per question breakdown
- final score + formula
10.2 Export CSV:
- export_session.php?sessionid=...
- include configuration to include/exclude comments
- ensure sensitive fields masked based on setting
10.3 Optional PDF export (only if time permits).

Deliverable at end of Milestone 10:
- HR can export a complete session report to CSV.
- Data is correct and confidentiality settings applied.

========================================================
MILESTONE 11 — EVENTS, PRIVACY API, BACKUP/RESTORE, TASKS, NOTIFICATIONS
========================================================
Goal:
- Make it a “real” Moodle module.

Tasks:
11.1 Events:
- classes/event/evaluation_started.php
- question_scored.php
- evaluation_submitted.php
- evaluation_reopened.php
- session_completed.php
- score_overridden.php
11.2 Privacy:
- classes/privacy/provider.php
- metadata + export + delete/anonymize
11.3 Backup/restore:
- backup/moodle2/*
- restore/moodle2/*
11.4 Scheduled tasks:
- reminders before deadline
- retention/anonymization after retention period
11.5 Notifications:
- use Moodle message API to notify committee assignment and reminders.

Deliverable at end of Milestone 11:
- Events appear in Moodle logs.
- Privacy API passes basic compliance checks.
- Backup/restore works at least for configuration + session skeleton.
- Reminders task runs.
- Notifications send.

========================================================
MILESTONE 12 — TESTING + HARDENING + DOCUMENTATION
========================================================
Goal:
- Ensure quality and maintainability.

Tasks:
12.1 PHPUnit tests for:
- compute_final_score correctness
- access control (IDOR checks)
12.2 Behat tests (optional but preferred):
- HR creates template/session
- committee scores/submits
- final score computed
12.3 Security review checklist.
12.4 Admin/Install documentation:
- README.md: install steps + roles setup + usage
- DEVPLAN.md updated with final status

Deliverable at end of Milestone 12:
- Tests run.
- Documentation clear.
- Plugin is ready for staging deployment.

========================================================
OUTPUT FORMAT REQUIREMENTS FOR YOU (Cursor)
========================================================
At the end of each milestone, you must produce:
1) A short “What changed” summary.
2) A list of files created/modified.
3) A checklist of acceptance criteria with PASS/FAIL.
4) Any manual steps needed in Moodle to verify milestone.

Proceed milestone by milestone. Do NOT jump ahead. Start with Milestone 0 now.
this
