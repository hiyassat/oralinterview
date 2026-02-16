<?php
require_once('../../config.php');

global $DB, $PAGE, $USER, $CFG;

require_login();
// This UI should always render in Arabic (site uses Arabic dashboard header).
force_current_language('ar');

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/mod/oralinterview/committee_index.php'));
$PAGE->set_title(get_string('myinterviews', 'oralinterview'));
$PAGE->set_heading(get_string('myinterviews', 'oralinterview'));

// Use the system custom header/footer (hide Moodle navbar, match dashboard design).
$hide_home_link = true;
require_once($CFG->dirroot . '/includes/header_exam.php');

// All oral interview activities where current user is an assigned committee member for at least 1 session.
$sql = "
    SELECT DISTINCT
        cm.id AS cmid,
        oi.id AS oralinterviewid,
        oi.name AS activityname,
        c.id AS courseid,
        c.fullname AS coursename
      FROM {oralint_committee} oc
      JOIN {oralint_session} s ON s.id = oc.sessionid
      JOIN {oralinterview} oi ON oi.id = s.oralinterviewid
      JOIN {course} c ON c.id = oi.course
      JOIN {modules} m ON m.name = 'oralinterview'
      JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = oi.id
     WHERE oc.userid = :userid
     ORDER BY c.fullname ASC, oi.name ASC
";
$activities = $DB->get_records_sql($sql, ['userid' => $USER->id]);

?>
<div class="container my-5" dir="rtl">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="m-0"><?= s(get_string('myinterviews', 'oralinterview')) ?></h3>
    </div>

    <?php if (empty($activities)): ?>
        <div class="alert alert-info text-center">
            <?= s(get_string('noassigned', 'oralinterview')) ?>
        </div>
    <?php else: ?>
        <div class="row g-3 justify-content-center">
            <?php foreach ($activities as $a):
                $manageurl = new moodle_url('/mod/oralinterview/manage.php', ['id' => $a->cmid]);
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2"><?= format_string($a->coursename) ?></div>
                            <h5 class="card-title mb-3"><?= format_string($a->activityname) ?></h5>
                            <a class="btn btn-primary btn-sm" href="<?= $manageurl->out() ?>">
                                <?= s(get_string('manage', 'oralinterview')) ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once($CFG->dirroot . '/includes/footer_exam.php');

