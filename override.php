<?php
require_once('../../config.php');

use core_user;
use html_table;
use html_writer;
use mod_oralinterview\event\score_overridden;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\audit_service as audit_service;
use mod_oralinterview\local\scoring as scoring_service;
use moodle_url;
use stdClass;

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/oralinterview:override', $context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);

echo $OUTPUT->header();
$PAGE->set_url(new moodle_url('/mod/oralinterview/override.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evalid]));
$PAGE->set_title(get_string('session_override', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_override', 'oralinterview'));

if (!$evalid) {
    $evaluations = $DB->get_records('oralint_eval', ['sessionid' => $sessionid], 'userid ASC');
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('status', 'oralinterview'), get_string('session_actions', 'oralinterview')];
    foreach ($evaluations as $evaluation) {
        $user = core_user::get_user($evaluation->userid);
        $actions = html_writer::link(new moodle_url('/mod/oralinterview/override.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evaluation->id]), get_string('session_override', 'oralinterview'));
        $table->data[] = [fullname($user), get_string('evaluation_status_' . $evaluation->status, 'oralinterview'), $actions];
    }
    echo html_writer::table($table);
    echo $OUTPUT->footer();
    exit;
}

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid, 'sessionid' => $sessionid], '*', MUST_EXIST);
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $sessionid], 'sortorder ASC');
$existing = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id], '', 'sessionqid,score,comment');

$scores = [];
foreach ($existing as $record) {
    $scores[$record->sessionqid] = $record;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $reason = trim(required_param('reason', PARAM_TEXT));
    if ($reason === '') {
        $errors[] = get_string('override_reason_required', 'oralinterview');
    }
    $payload = [];
    if (empty($errors)) {
        foreach ($questions as $question) {
            $key = 'score_' . $question->id;
            $commentkey = 'comment_' . $question->id;
            $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $score = $value === null || $value === '' ? null : (float)$value;
            $comment = clean_param($_POST[$commentkey] ?? '', PARAM_TEXT);
            $record = $scores[$question->id] ?? null;
            $oldvalue = null;
            if ($record) {
                $oldvalue = json_encode(['score' => $record->score, 'comment' => $record->comment]);
                $record->score = $score;
                $record->comment = $comment;
                $record->timemodified = time();
                $record->usermodified = $USER->id;
                $DB->update_record('oralint_score', $record);
            } else {
                $newrec = new stdClass();
                $newrec->evaluationid = $evaluation->id;
                $newrec->sessionqid = $question->id;
                $newrec->score = $score;
                $newrec->comment = $comment;
                $newrec->timecreated = time();
                $newrec->timemodified = time();
                $newrec->usermodified = $USER->id;
                $newrec->id = $DB->insert_record('oralint_score', $newrec);
                $record = $newrec;
            }
            $payload[$question->id] = ['score' => $score, 'comment' => $comment];
        }
        $evaluation->status = 'submitted';
        $evaluation->usermodified = $USER->id;
        $evaluation->timemodified = time();
        $evaluation->submitreason = $reason;
        $DB->update_record('oralint_eval', $evaluation);
        audit_service::log('score_overridden', 'evaluation', $sessionid, $USER->id, null, json_encode($payload), $reason, 'u');
        $event = score_overridden::create([
            'objectid' => $evaluation->id,
            'context' => $context,
            'other' => ['sessionid' => $sessionid, 'evalid' => $evaluation->id]
        ]);
        $event->trigger();
        scoring_service::compute_final_score($sessionid, $USER->id, true);
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
    }
}

$table = new html_table();
$table->head = [get_string('question_text', 'oralinterview'), get_string('maxscore', 'oralinterview'), get_string('score', 'oralinterview'), get_string('comment', 'oralinterview')];

foreach ($questions as $question) {
    $existingscore = $scores[$question->id] ?? null;
    $scorevalue = $existingscore ? $existingscore->score : '';
    $commentvalue = $existingscore ? $existingscore->comment : '';
    $scoreinput = html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => 0,
        'max' => 10,
        'step' => 0.1,
        'name' => 'score_' . $question->id,
        'value' => $scorevalue,
        'class' => 'oralint-score-input'
    ]);
    $commentinput = html_writer::tag('textarea', s($commentvalue), ['name' => 'comment_' . $question->id, 'rows' => 2]);
    $table->data[] = [format_text($question->questiontext, FORMAT_HTML), $question->maxscore, $scoreinput, $commentinput];
}

echo $OUTPUT->heading(get_string('session_override', 'oralinterview'));
if ($candidate) {
    echo $OUTPUT->heading(format_string($candidate->fullname), 3);
}
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
echo html_writer::tag('p', get_string('override_reason_hint', 'oralinterview'));
echo html_writer::tag(
    'textarea',
    s($reason ?? ''),
    ['name' => 'reason', 'rows' => 4, 'cols' => 60, 'required' => 'required']
);
echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'override', 'value' => get_string('session_override', 'oralinterview'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
