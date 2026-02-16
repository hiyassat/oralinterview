<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/oralinterview:override', $context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);

$PAGE->set_url(new moodle_url('/mod/oralinterview/override.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evalid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('session_override', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'), new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->navbar->add(get_string('session_override', 'oralinterview'));

if (!$evalid) {
    $evaluations = $DB->get_records('oralint_eval', ['sessionid' => $sessionid], 'userid ASC');
    
    if ($spacui) {
        oralinterview_spacui_header($cm, 'manage', get_string('session_override', 'oralinterview'));
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('session_override', 'oralinterview'));
    }

    echo '<table class="generaltable">';
    echo '<thead><tr>';
    echo '<th>' . get_string('fullname') . '</th>';
    echo '<th>' . get_string('status') . '</th>';
    echo '<th>' . get_string('session_actions', 'oralinterview') . '</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($evaluations as $evaluation) {
        $user = $DB->get_record('user', ['id' => $evaluation->userid]);
        $actions = '<a class="btn btn-outline-secondary btn-sm" href="override.php?id=' . $cm->id . '&sessionid=' . $sessionid . '&evalid=' . $evaluation->id . '&spacui=' . (int)$spacui . '">' . get_string('override_evaluation', 'oralinterview') . '</a>';
        echo '<tr>';
        echo '<td>' . fullname($user) . '</td>';
        echo '<td>' . get_string('evaluation_status_' . $evaluation->status, 'oralinterview') . '</td>';
        echo '<td>' . $actions . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    if ($spacui) {
        oralinterview_spacui_footer();
    } else {
        echo $OUTPUT->footer();
    }
    exit;
}

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid, 'sessionid' => $sessionid], '*', MUST_EXIST);
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $sessionid], 'sortorder ASC');
$existing = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id], '', 'id,sessionqid,score,comment,evaluationid');

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
            if ($record && isset($record->id)) {
                $oldvalue = json_encode(['score' => $record->score, 'comment' => $record->comment]);
                // Update existing record
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
        
        // Simplified logging - disabled for now
        // audit_service::log('score_overridden', 'evaluation', $sessionid, $USER->id, null, json_encode($payload), $reason, 'u');
        
        // Simplified event trigger - disabled for now
        /*
        $event = score_overridden::create([
            'objectid' => $evaluation->id,
            'context' => $context,
            'other' => ['sessionid' => $sessionid, 'evalid' => $evaluation->id]
        ]);
        $event->trigger();
        */
        
        // Simplified scoring - disabled for now
        // scoring_service::compute_final_score($sessionid, $USER->id, true);
        
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }
}

if ($spacui) {
    oralinterview_spacui_header($cm, 'manage', get_string('override_evaluation', 'oralinterview'));
} else {
    echo $OUTPUT->header();
}

echo $OUTPUT->heading(get_string('override_evaluation', 'oralinterview') . ': ' . ($candidate ? format_string($candidate->fullname) : 'Unknown Candidate'));

echo '<form method="post" action="">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<div class="mb-3">';
echo '<label for="reason" class="form-label">' . get_string('override_reason', 'oralinterview') . ':</label>';
echo '<textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>';
echo '</div>';
echo '<table class="generaltable">';
echo '<thead><tr>';
echo '<th>' . get_string('questiontext', 'oralinterview') . '</th>';
echo '<th>' . get_string('maxscore', 'oralinterview') . '</th>';
echo '<th>' . get_string('score', 'oralinterview') . '</th>';
echo '<th>' . get_string('comment', 'oralinterview') . '</th>';
echo '</tr></thead><tbody>';

foreach ($questions as $question) {
    $existingscore = $scores[$question->id] ?? null;
    $scorevalue = $existingscore ? $existingscore->score : '';
    $commentvalue = $existingscore ? $existingscore->comment : '';
    
    echo '<tr>';
    echo '<td>' . format_text($question->questiontext, FORMAT_HTML) . '</td>';
    echo '<td>' . $question->maxscore . '</td>';
    echo '<td><input type="number" min="0" max="' . $question->maxscore . '" step="0.1" name="score_' . $question->id . '" value="' . s($scorevalue) . '" class="form-control" /></td>';
    echo '<td><textarea name="comment_' . $question->id . '" rows="2" class="form-control">' . s($commentvalue) . '</textarea></td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '<div class="mt-3">';
echo '<input type="submit" value="' . get_string('submit', 'oralinterview') . '" class="btn btn-primary" />';
echo '</div>';
echo '</form>';

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
