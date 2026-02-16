<?php
require_once('../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// This page is redundant: committee members can use manage.php (it already filters to their sessions).
redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));

// Check if user is a committee member for any session in this activity
$is_committee_member = $DB->record_exists_sql("
    SELECT 1
    FROM {oralint_committee} oc
    JOIN {oralint_session} s ON s.id = oc.sessionid
    WHERE s.oralinterviewid = :oralinterviewid
    AND oc.userid = :userid
", ['oralinterviewid' => $cm->id, 'userid' => $USER->id]);

// Allow access if:
// 1. User is enrolled in course (normal case)
// 2. User is a committee member (even if not enrolled)
// 3. User has evaluate capability
if ($is_committee_member || has_capability('mod/oralinterview:evaluate', $context)) {
    // Allow access without requiring enrollment
    require_login();
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
} else {
    // Normal course enrollment check
    require_login($course, true, $cm);
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/my.php', ['id' => $cm->id]));
$PAGE->set_title(get_string('myinterviews', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('myinterviews', 'oralinterview'));

$sql = "SELECT e.*, s.templateid, s.candidateid, s.status as sessionstatus, t.name as templatename, c.fullname AS candidatename
          FROM {oralint_eval} e
          JOIN {oralint_session} s ON s.id = e.sessionid
          LEFT JOIN {oralint_template} t ON t.id = s.templateid
          LEFT JOIN {oralint_candidate} c ON c.id = s.candidateid
          WHERE e.userid = :userid AND s.courseid = :courseid
          ORDER BY s.interviewdate DESC";
$params = ['userid' => $USER->id, 'courseid' => $course->id];
$evaluations = $DB->get_records_sql($sql, $params);

$table = new html_table();
$table->head = [
    get_string('session_template', 'oralinterview'),
    get_string('session_candidate', 'oralinterview'),
    get_string('session_status', 'oralinterview'),
    get_string('status', 'oralinterview'),
    get_string('session_actions', 'oralinterview')
];

foreach ($evaluations as $evaluation) {
    $status = get_string('evaluation_status_' . $evaluation->status, 'oralinterview');
    $linktext = get_string($evaluation->status === 'submitted' ? 'view' : 'evaluate', 'oralinterview');
    $actions = html_writer::link(new moodle_url('/mod/oralinterview/evaluate.php', ['id' => $cm->id, 'evalid' => $evaluation->id]), $linktext);
    $table->data[] = [
        format_string($evaluation->templatename ?? '-'),
        format_string($evaluation->candidatename ?? '-'),
        get_string('session_status_' . $evaluation->sessionstatus, 'oralinterview'),
        $status,
        $actions
    ];
}

echo $OUTPUT->header();
if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noassigned', 'oralinterview'));
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
