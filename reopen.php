<?php
require_once('../../config.php');

use core_user;
use html_table;
use html_writer;
use mod_oralinterview\event\evaluation_reopened;
use moodle_url;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\audit_service as audit_service;
use mod_oralinterview\local\scoring as scoring_service;

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/oralinterview:reopen', $context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/oralinterview/reopen.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evalid]));
$PAGE->set_title(get_string('session_reopen', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_reopen', 'oralinterview'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('session_reopen', 'oralinterview'));

if (!$evalid) {
    $evaluations = $DB->get_records('oralint_eval', ['sessionid' => $sessionid], 'userid ASC');
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('status', 'oralinterview'), get_string('session_actions', 'oralinterview')];
    foreach ($evaluations as $evaluation) {
        $user = core_user::get_user($evaluation->userid);
        $actions = html_writer::link(new moodle_url('/mod/oralinterview/reopen.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evaluation->id]), get_string('session_reopen', 'oralinterview'));
        $table->data[] = [fullname($user), get_string('evaluation_status_' . $evaluation->status, 'oralinterview'), $actions];
    }
    echo html_writer::table($table);
    echo $OUTPUT->footer();
    exit;
}

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid, 'sessionid' => $sessionid], '*', MUST_EXIST);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $reason = trim(required_param('reason', PARAM_TEXT));
    if ($reason === '') {
        $errors[] = get_string('reopen_reason_required', 'oralinterview');
    }
    if (empty($errors)) {
        $evaluation->status = 'notstarted';
        $evaluation->submitreason = $reason;
        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        $DB->update_record('oralint_eval', $evaluation);
        audit_service::log('evaluation_reopened', 'evaluation', $sessionid, $USER->id, null, null, $reason, 'u');
        $event = evaluation_reopened::create([
            'objectid' => $evaluation->id,
            'context' => $context,
            'other' => ['sessionid' => $sessionid]
        ]);
        $event->trigger();
        scoring_service::compute_final_score($sessionid, $USER->id, true);
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo html_writer::tag('p', get_string('reopen_instruction', 'oralinterview'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('p', get_string('reopen_reason_label', 'oralinterview'));
echo html_writer::tag('textarea', s($reason ?? ''), ['name' => 'reason', 'rows' => 4, 'cols' => 60, 'required' => 'required']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('reopen_submit', 'oralinterview'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
