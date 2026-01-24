<?php
require_once('../../config.php');

use html_table;
use html_writer;
use mod_oralinterview\event\evaluation_started;
use mod_oralinterview\event\question_scored;
use mod_oralinterview\event\evaluation_submitted;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\audit_service as audit_service;
use mod_oralinterview\local\scoring as scoring_service;
use stdClass;
use mod_oralinterview\local\scoring as scoring_service;
use stdClass;

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$evalid = required_param('evalid', PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_evaluate($context);

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid], '*', MUST_EXIST);
if ($evaluation->userid !== $USER->id) {
    require_capability('mod/oralinterview:viewown', $context);
    throw new moodle_exception('nopermission', 'error');
}

$session = $DB->get_record('oralint_session', ['id' => $evaluation->sessionid], '*', MUST_EXIST);
if ($evaluation->status === 'notstarted') {
    $event = evaluation_started::create([
        'objectid' => $evaluation->id,
        'context' => $context,
        'other' => ['sessionid' => $session->id]
    ]);
    $event->trigger();
}
$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $session->id], 'sortorder ASC');
$existing = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id], '', 'sessionqid,score,comment');

$scores = [];
foreach ($existing as $record) {
    $scores[$record->sessionqid] = $record;
}

$errors = [];
$action = '';
$scorespayload = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = isset($_POST['submit']) ? 'submit' : 'save';
    foreach ($questions as $question) {
        $key = 'score_' . $question->id;
        $commentkey = 'comment_' . $question->id;
        $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $score = $value === null || $value === '' ? null : (float)$value;
        $comment = clean_param($_POST[$commentkey] ?? '', PARAM_TEXT);
        if ($score === null && $action === 'submit') {
            $errors[] = get_string('evaluation_all_required', 'oralinterview');
            break;
        }
        if ($score !== null && ($score < 0 || $score > 10)) {
            $errors[] = get_string('evaluation_score_range', 'oralinterview');
            break;
        }
        if ($score !== null || $comment !== '') {
            $record = $scores[$question->id] ?? null;
            if ($record) {
                $record->score = $score;
                $record->comment = $comment;
                $record->timemodified = time();
                $record->usermodified = $USER->id;
                $DB->update_record('oralint_score', $record);
                $scoreid = $record->id;
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
                $scores[$question->id] = $newrec;
                $scoreid = $newrec->id;
            }
            $scorespayload[$question->id] = ['score' => $score, 'comment' => $comment];
            $event = question_scored::create([
                'objectid' => $scoreid,
                'context' => $context,
                'other' => ['sessionid' => $session->id, 'questionid' => $question->id, 'evalid' => $evaluation->id]
            ]);
            $event->trigger();
        }
    }
    if (empty($errors)) {
        $payload = !empty($scorespayload) ? json_encode($scorespayload) : null;
        audit_service::log('score_saved', 'evaluation', $session->id, $USER->id, null, $payload, $action);
        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        if ($action === 'submit') {
            $evaluation->status = 'submitted';
        } elseif ($evaluation->status === 'notstarted') {
            $evaluation->status = 'inprogress';
        }
        $DB->update_record('oralint_eval', $evaluation);
        if ($action === 'submit') {
            audit_service::log('evaluation_submitted', 'evaluation', $session->id, $USER->id, null, $payload, null);
            $event = evaluation_submitted::create([
                'objectid' => $evaluation->id,
                'context' => $context,
                'other' => ['sessionid' => $session->id]
            ]);
            $event->trigger();
        }
        scoring_service::compute_final_score($session->id);
        redirect(new moodle_url('/mod/oralinterview/my.php', ['id' => $cm->id]));
    }
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/evaluate.php', ['id' => $cm->id, 'evalid' => $evaluation->id]));
$PAGE->set_title(get_string('evaluate', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('evaluate', 'oralinterview'));

$table = new html_table();
$table->head = [get_string('question_text', 'oralinterview'), get_string('maxscore', 'oralinterview'), get_string('score', 'oralinterview'), get_string('comment', 'oralinterview')];

$buttonlabel = $evaluation->status === 'submitted' ? get_string('submitted', 'oralinterview') : get_string('submit', 'oralinterview');

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

$actionbuttons = html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'save', 'value' => get_string('save_draft', 'oralinterview'), 'class' => 'btn btn-secondary']) . ' ';
$actionbuttons .= html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'submit', 'value' => get_string('submit', 'oralinterview'), 'class' => 'btn btn-primary']);

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

if ($candidate) {
    echo $OUTPUT->heading(format_string($candidate->fullname));
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
echo $actionbuttons;
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
