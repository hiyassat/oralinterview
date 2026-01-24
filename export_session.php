<?php
require_once('../../config.php');

use core_user;
use mod_oralinterview\local\access as access_helper;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$includecomments = optional_param('includecomments', 1, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_export($context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$template = $DB->get_record('oralint_template', ['id' => $session->templateid], '*', IGNORE_MISSING);
$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $sessionid]);
$evaluations = $DB->get_records('oralint_eval', ['sessionid' => $sessionid]);
$evaluationids = array_map(fn($row) => $row->id, $evaluations);
$scoreRecords = $evaluationids ? $DB->get_records_list('oralint_score', 'evaluationid', $evaluationids, '', 'evaluationid,sessionqid,score,comment') : [];

$scoremap = [];
foreach ($scoreRecords as $score) {
    $scoremap[$score->evaluationid][$score->sessionqid] = $score;
}

$filename = 'oralinterview_session_' . $sessionid . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$fp = fopen('php://output', 'w');

fputcsv($fp, [get_string('session_candidate', 'oralinterview'), $candidate ? $candidate->fullname : '']);
fputcsv($fp, [get_string('session_template', 'oralinterview'), $template ? $template->name : '']);
fputcsv($fp, [get_string('session_finalscore', 'oralinterview'), $session->finalscore !== null ? format_float($session->finalscore, 2) : '']);
fputcsv($fp, []);

$header = [
    get_string('question_text', 'oralinterview'),
    get_string('committee_member', 'oralinterview'),
    get_string('score', 'oralinterview')
];
if ($includecomments) {
    $header[] = get_string('comment', 'oralinterview');
}
fputcsv($fp, $header);

foreach ($evaluations as $evaluation) {
    $user = core_user::get_user($evaluation->userid);
    foreach ($questions as $question) {
        $record = $scoremap[$evaluation->id][$question->id] ?? null;
        $row = [
            format_text($question->questiontext, FORMAT_HTML),
            fullname($user),
            $record ? format_float($record->score, 2) : ''
        ];
        if ($includecomments) {
            $row[] = $record ? clean_param($record->comment, PARAM_TEXT) : '';
        }
        fputcsv($fp, $row);
    }
}
fclose($fp);
exit;
