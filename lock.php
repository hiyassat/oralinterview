<?php
require_once('../../config.php');

use html_writer;
use moodle_url;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\audit_service as audit_service;
use mod_oralinterview\local\scoring as scoring_service;

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$mode = optional_param('mode', 'lock', PARAM_ALPHA);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/oralinterview:lock', $context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$statuslabel = $mode === 'unlock' ? get_string('session_unlock', 'oralinterview') : get_string('session_lock', 'oralinterview');

echo $OUTPUT->header();
$PAGE->set_url(new moodle_url('/mod/oralinterview/lock.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'mode' => $mode]));
$PAGE->set_title($statuslabel);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($statuslabel);

echo $OUTPUT->heading($statuslabel);

echo $OUTPUT->box(get_string('lock_instruction', 'oralinterview'));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $reason = trim(required_param('reason', PARAM_TEXT));
    if ($reason === '') {
        $errors[] = get_string('lock_reason_required', 'oralinterview');
    }
    if (empty($errors)) {
        $session->status = $mode === 'unlock' ? 'active' : 'locked';
        $session->timemodified = time();
        $session->usermodified = $USER->id;
        $DB->update_record('oralint_session', $session);
        $action = $mode === 'unlock' ? 'session_unlocked' : 'session_locked';
        audit_service::log($action, 'session', $sessionid, $USER->id, null, null, $reason, 'u');
        scoring_service::compute_final_score($sessionid, $USER->id, true);
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('p', get_string('lock_reason_label', 'oralinterview'));
echo html_writer::tag('textarea', '', ['name' => 'reason', 'rows' => 4, 'cols' => 60, 'required' => 'required']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => $statuslabel, 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
