<?php
$require_once('../../config.php');

use html_table;
use html_writer;
use moodle_url;
$use mod_oralinterview\local\access as access_helper;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_evaluate($context);

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
