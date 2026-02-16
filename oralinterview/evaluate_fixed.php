<?php
require_once('../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);

if (!$sessionid && !$evalid) {
    echo "Error: Both sessionid and evalid are missing. Please access this page from the Sessions list.";
    exit;
}

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Get session
$session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);

// Create evaluation record if it doesn't exist
if (!$evalid) {
    $existing = $DB->get_record('oralint_eval', ['sessionid' => $sessionid, 'userid' => $USER->id]);
    if (!$existing) {
        $evaluation = new stdClass();
        $evaluation->sessionid = $sessionid;
        $evaluation->userid = $USER->id;
        $evaluation->status = 'notstarted';
        $evaluation->timecreated = time();
        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        $evalid = $DB->insert_record('oralint_eval', $evaluation);
    } else {
        $evalid = $existing->id;
    }
}

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid], '*', MUST_EXIST);

// Create session questions from template if they don't exist
$session_questions = $DB->get_records('oralint_session_q', ['sessionid' => $sessionid]);
if (empty($session_questions)) {
    $template_questions = $DB->get_records('oralint_template_q', ['templateid' => $session->templateid]);
    foreach ($template_questions as $tq) {
        $sq = new stdClass();
        $sq->sessionid = $sessionid;
        $sq->questiontext = $tq->questiontext;
        $sq->rubric = $tq->rubric;
        $sq->maxscore = $tq->maxscore;
        $sq->sortorder = $tq->sortorder;
        $sq->timecreated = time();
        $sq->timemodified = time();
        $sq->usermodified = $USER->id;
        $DB->insert_record('oralint_session_q', $sq);
    }
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
        if ($score !== null && ($score < 0 || $score > $question->maxscore)) {
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
            } else {
                $newrec = new stdClass();
                $newrec->evaluationid = $evaluation->id;
                $newrec->sessionqid = $question->id;
                $newrec->score = $score;
                $newrec->comment = $comment;
                $newrec->timecreated = time();
                $newrec->timemodified = time();
                $newrec->usermodified = $USER->id;
                $DB->insert_record('oralint_score', $newrec);
            }
        }
    }
    if (empty($errors)) {
        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        if ($action === 'submit') {
            $evaluation->status = 'submitted';
        } elseif ($evaluation->status === 'notstarted') {
            $evaluation->status = 'inprogress';
        }
        $DB->update_record('oralint_eval', $evaluation);
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
    }
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/evaluate.php', ['id' => $cm->id, 'sessionid' => $sessionid]));
$PAGE->set_title(get_string('evaluate', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'), new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('evaluate', 'oralinterview'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evaluate', 'oralinterview') . ': ' . ($candidate ? format_string($candidate->fullname) : 'Unknown Candidate'));

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo '<form method="post" action="">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
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
echo '<input type="submit" name="save" value="' . get_string('save_draft', 'oralinterview') . '" class="btn btn-secondary" /> ';
echo '<input type="submit" name="submit" value="' . get_string('submit', 'oralinterview') . '" class="btn btn-primary" />';
echo '</div>';
echo '</form>';

echo $OUTPUT->footer();
?>
