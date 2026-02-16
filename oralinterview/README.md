# Oral Interview activity

## Overview
`mod_oralinterview` is a Moodle 4.4 activity that lets HR teams manage external candidate interviews and committee scoring with a full audit trail, overrides, exports, reminders, and retention controls.

## Installation
1. Copy `mod_oralinterview` into your Moodle `mod/` directory (e.g., `/path/to/moodle/mod/mod_oralinterview`).
2. Run the Moodle upgrade via **Site administration > Notifications** or `php /path/to/moodle/admin/cli/upgrade.php`.
3. Configure global settings via **Site administration > Plugins > Activities > Oral Interview** (retention days, reminder lead time, and any additional options).
4. Add the activity to a course, turn editing on, and configure the new “Oral Interview” instance to fit your workflow.
5. (Optional) Ensure scheduled tasks such as `\mod_oralinterview\task\reminder` can run; trigger manually with `php admin/tool/task/cli/schedule_task.php --execute='\mod_oralinterview\task\reminder'`.

## Installation Manual
- **Purpose**: `mod_oralinterview` is a Moodle 4.4 activity tailored for HR-run oral interviews, offering scoring, exports, overrides, reminders, retention, and auditing.
- **Prerequisites**:
  - Moodle 4.4+ with compatible PHP, database, and web server.
  - Site administrator rights for installing plugins and running upgrades.
  - Optional: familiarity with Moodle scheduled tasks if you plan to customize reminders/retention.
- **Step-by-step**:
  1. Place the plugin folder inside Moodle's `mod/`.
  2. Run Moodle’s upgrade process to register tables/capabilities (`install.xml` defines schema).
  3. Configure settings under **Site administration > Plugins > Activities > Oral Interview**.
  4. Add the activity to a course and use the provided navigation links (“Manage interviews”, “My Interviews”) for workflow.
  5. Verify scheduled tasks (reminders, anonymization) via the CLI or scheduled task UI.
- **Post-install checks**:
  - Ensure HR vs. committee navigation links appear and role-based screens are gated.
  - Confirm exports (`export_session.php`) and reports (`session_report.php`) function as expected.
  - Review `oralint_audit` table to ensure scoring and overrides log correctly.
- **Roles & permissions**:
  - Managers/HR: `mod/oralinterview:manage`, `mod/oralinterview:export`, `mod/oralinterview:override`, `mod/oralinterview:reopen`, `mod/oralinterview:lock`, `mod/oralinterview:recalculate`.
  - Committee members: `mod/oralinterview:evaluate`, `mod/oralinterview:viewown`.
- **Testing**:
  - PHPUnit: `vendor/bin/phpunit -c admin/tool/phpunit/ tests/scoring_test.php`.
  - Scheduled tasks: `php admin/tool/task/cli/schedule_task.php --execute='\mod_oralinterview\task\reminder'`.
- **Maintenance & tasks**:
  - Audit logs in `oralint_audit` capture every score change, override, reopen, lock/unlock.
  - Scheduler tasks handle anonymization and reminder dispatch per settings.
  - Use `export_session.php` and session reports for periodic data reviews.
# oralinterview
# oralinterview
# oralinterview
# oralinterview
