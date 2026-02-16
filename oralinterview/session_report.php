<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/local/access.php');

use core_user;
use mod_oralinterview\local\access as access_helper;

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
$PAGE->set_url(new moodle_url('/mod/oralinterview/session_report.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('session_report', 'oralinterview'));
$PAGE->set_heading($course->fullname);

// Allow session report for managers/exporters and assigned committee members.
$can_export = has_capability('mod/oralinterview:export', $context) || has_capability('mod/oralinterview:manage', $context);
$is_session_committee = $DB->record_exists('oralint_committee', ['sessionid' => $sessionid, 'userid' => $USER->id]);
if (!$can_export && !$is_session_committee) {
    throw new moodle_exception('nopermission', 'error');
}

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$template = $DB->get_record('oralint_template', ['id' => $session->templateid], '*', IGNORE_MISSING);
$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $session->id], 'sortorder ASC');
$evaluations = $DB->get_records('oralint_eval', ['sessionid' => $session->id], 'userid ASC');
$evaluationids = array_map(fn($row) => $row->id, $evaluations);
$scores = $evaluationids ? $DB->get_records_list('oralint_score', 'evaluationid', $evaluationids, '', 'evaluationid,sessionqid,score,comment') : [];

$scoremap = [];
foreach ($scores as $score) {
    $scoremap[$score->evaluationid][$score->sessionqid] = $score;
}

$questionAverages = [];
$memberRows = [];
foreach ($evaluations as $evaluation) {
    $user = core_user::get_user($evaluation->userid);
    $total = 0;
    $rows = [];
    foreach ($questions as $question) {
        $record = $scoremap[$evaluation->id][$question->id] ?? null;
        // If the evaluation was submitted but a score row is missing, treat it as 0 (not N/A).
        $scorevalue = $record ? (float)$record->score : null;
        if (!$record && $evaluation->status === 'submitted') {
            $scorevalue = 0.0;
        }

        if ($scorevalue !== null) {
            $total += $scorevalue;
            $questionAverages[$question->id][] = $scorevalue;
        }
        $rows[] = [
            'question' => format_text($question->questiontext, FORMAT_HTML),
            'score' => ($scorevalue !== null) ? format_float($scorevalue, 2) : get_string('notapplicable', 'oralinterview'),
            'comment' => $record ? format_text($record->comment, FORMAT_TEXT) : ''
        ];
    }
    $memberRows[] = [
        'user' => fullname($user),
        'status' => get_string('evaluation_status_' . $evaluation->status, 'oralinterview'),
        'total' => format_float($total, 2),
        'details' => $rows
    ];
}

function oralinterview_mask_nationalid($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/session_report.php', ['id' => $cm->id, 'sessionid' => $sessionid]));
$PAGE->set_title(get_string('session_report', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_report', 'oralinterview'));

if ($spacui) {
    $hide_home_link = true;
    require_once($CFG->dirroot . '/includes/header_exam.php');
    echo '<div class="container my-5" dir="rtl">';
    echo '<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">';
    echo '<h3 class="m-0">' . s(get_string('session_report', 'oralinterview')) . '</h3>';
    echo '</div>';
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('session_report', 'oralinterview'));
}

echo $OUTPUT->box(get_string('session_template', 'oralinterview') . ': ' . ($template ? format_string($template->name) : '-'));
if ($candidate) {
    echo $OUTPUT->box(get_string('session_candidate', 'oralinterview') . ': ' . format_string($candidate->fullname) . ' (' . get_string('candidate_nationalid', 'oralinterview') . ': ' . oralinterview_mask_nationalid($candidate->nationalid) . ')');
}

echo html_writer::tag('h3', get_string('session_finalscore', 'oralinterview') . ': ' . ($session->finalscore !== null ? format_float($session->finalscore, 2) . ' (' . format_float($session->finalpercent ?? 0, 2) . '%)' : get_string('notapplicable', 'oralinterview')));

$buttonbar = html_writer::link(new moodle_url('/mod/oralinterview/export_session.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'includecomments' => 1]), get_string('export_session', 'oralinterview'), ['class' => 'btn btn-secondary']) . ' ';
$buttonbar .= html_writer::link(new moodle_url('/mod/oralinterview/export_session.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'includecomments' => 0]), get_string('export_scores_only', 'oralinterview'), ['class' => 'btn btn-secondary']);

echo html_writer::div($buttonbar, 'mb-3');

$questiontable = new html_table();
$questiontable->head = [get_string('question_text', 'oralinterview'), get_string('maxscore', 'oralinterview'), get_string('average', 'oralinterview')];
foreach ($questions as $question) {
    $average = '';
    if (!empty($questionAverages[$question->id])) {
        $average = format_float(array_sum($questionAverages[$question->id]) / count($questionAverages[$question->id]), 2);
    }
    $questiontable->data[] = [format_text($question->questiontext, FORMAT_HTML), $question->maxscore, $average];
}
echo html_writer::table($questiontable);

foreach ($memberRows as $member) {
    echo html_writer::start_tag('div', ['class' => 'card mb-3']);
    echo html_writer::tag('h4', $member['user'] . ' - ' . $member['status']);
    $membertable = new html_table();
    $membertable->head = [get_string('question_text', 'oralinterview'), get_string('score', 'oralinterview'), get_string('comment', 'oralinterview')];
    foreach ($member['details'] as $detail) {
        $membertable->data[] = [$detail['question'], $detail['score'], $detail['comment']];
    }
    echo html_writer::table($membertable);
    echo html_writer::end_tag('div');
}

if ($spacui) {
    echo '</div>';
    require_once($CFG->dirroot . '/includes/footer_exam.php');
} else {
    echo $OUTPUT->footer();
}
