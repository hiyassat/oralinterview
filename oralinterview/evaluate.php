<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/local/scoring.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

if (!$sessionid && !$evalid) {
    echo "Error: Both sessionid and evalid are missing. Please access this page from the Sessions list.";
    exit;
}

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check if user is a committee member for this session
$is_committee_member = false;
if ($sessionid) {
    $is_committee_member = $DB->record_exists('oralint_committee', [
        'sessionid' => $sessionid,
        'userid' => $USER->id
    ]);
}

// Allow access if committee member or has evaluate capability
if ($is_committee_member || has_capability('mod/oralinterview:evaluate', $context)) {
    require_login();
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
} else {
    require_login($course, true, $cm);
}

// Ensure Arabic is forced after login as well (require_login may reset language to user preference).
if ($spacui && function_exists('force_current_language')) {
    force_current_language('ar');
}

// Get session
$session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);

// Locked sessions: no one can evaluate until unlocked.
if (($session->status ?? '') === 'locked') {
    throw new moodle_exception('sessionlocked', 'oralinterview');
}

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

$candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);

// Ensure session questions exist (stable snapshot).
// IMPORTANT: Do NOT delete/recreate session questions during evaluation, or saved scores will point to old IDs.
$questions = $DB->get_records('oralint_session_q', ['sessionid' => $session->id], 'sortorder ASC');
if (empty($questions)) {
    $template_questions = $DB->get_records('oralint_template_q', ['templateid' => $session->templateid], 'sortorder ASC');
    foreach ($template_questions as $tq) {
        $sq = new stdClass();
        $sq->sessionid = $sessionid;
        $sq->questionid = $tq->questionid;
        $sq->questiontext = $tq->questiontext;
        $sq->rubric = $tq->rubric;
        $sq->maxscore = $tq->maxscore;
        $sq->sortorder = $tq->sortorder;
        $sq->timecreated = time();
        $sq->timemodified = time();
        $sq->usermodified = $USER->id;
        $DB->insert_record('oralint_session_q', $sq);
    }
    $questions = $DB->get_records('oralint_session_q', ['sessionid' => $session->id], 'sortorder ASC');

    // Fallback: if template had no questions, build from job's exam_interview_competencies + question_type_mapping (new logic).
    if (empty($questions)) {
        require_once(__DIR__ . '/lib_template_questions.php');
        $template = $DB->get_record('oralint_template', ['id' => $session->templateid], 'jobid', IGNORE_MISSING);
        $jobid = $template ? (int)($template->jobid ?? 0) : 0;
        if (!$jobid) {
            $oi = $DB->get_record('oralinterview', ['id' => $session->oralinterviewid], 'jobid', IGNORE_MISSING);
            $jobid = $oi ? (int)($oi->jobid ?? 0) : 0;
        }
        if ($jobid > 0) {
            $tree = oralinterview_get_competency_indicator_tree($jobid);
            $seen = [];
            $sortorder = 0;
            foreach ($tree as $comp) {
                foreach ($comp['indicators'] as $ind) {
                    $indid = (int)$ind['id'];
                    $ind_questions = oralinterview_get_questions_for_indicator($indid);
                    foreach ($ind_questions as $q) {
                        $qid = (int)$q->id;
                        if (isset($seen[$qid])) {
                            continue;
                        }
                        $seen[$qid] = true;
                        $sq = new stdClass();
                        $sq->sessionid = $sessionid;
                        $sq->questionid = $qid;
                        $sq->questiontext = $q->questiontext ?? '';
                        $sq->rubric = '';
                        $sq->maxscore = 10;
                        $sq->sortorder = ++$sortorder;
                        $sq->timecreated = time();
                        $sq->timemodified = time();
                        $sq->usermodified = $USER->id;
                        $DB->insert_record('oralint_session_q', $sq);
                    }
                }
            }
            $questions = $DB->get_records('oralint_session_q', ['sessionid' => $session->id], 'sortorder ASC');
            if (!empty($questions)) {
                $DB->set_field('oralint_session', 'questioncount', count($questions), ['id' => $session->id]);
            }
        }
    }
}

// Load existing scores. Include id, otherwise update_record() fails.
$existing = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id], '', 'id,evaluationid,sessionqid,score,comment');

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
        
        // On submit, require all questions to have a score.
        if ($action === 'submit' && $score === null) {
            $errors[] = get_string('evaluation_all_required', 'oralinterview');
            break;
        }

        // Allow partial scoring on save - only validate if score is provided
        if ($score !== null && ($score < 0 || $score > $question->maxscore)) {
            $errors[] = get_string('evaluation_score_range', 'oralinterview');
            break;
        }
        if ($score !== null || $comment !== '') {
            // oralint_score.score is NOT NULL in schema.
            // If the user only left a comment, treat the score as 0.
            $scoreToStore = ($score === null) ? 0.0 : $score;
            $record = $scores[$question->id] ?? null;
            if ($record) {
                $record->score = $scoreToStore;
                $record->comment = $comment;
                $record->timemodified = time();
                $record->usermodified = $USER->id;
                $DB->update_record('oralint_score', $record);
            } else {
                $newrec = new stdClass();
                $newrec->evaluationid = $evaluation->id;
                $newrec->sessionqid = $question->id;
                $newrec->score = $scoreToStore;
                $newrec->comment = $comment;
                $newrec->timecreated = time();
                $newrec->timemodified = time();
                $newrec->usermodified = $USER->id;
                $DB->insert_record('oralint_score', $newrec);
            }
        }
    }
    if (empty($errors)) {
        // Refresh scores map after possible inserts/updates so form redisplay and submit score calc is accurate.
        $existing = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id], '', 'id,evaluationid,sessionqid,score,comment');
        $scores = [];
        foreach ($existing as $record) {
            $scores[$record->sessionqid] = $record;
        }

        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        if ($action === 'submit') {
            $evaluation->status = 'submitted';
        } elseif ($evaluation->status === 'notstarted') {
            $evaluation->status = 'inprogress';
        }
        $DB->update_record('oralint_eval', $evaluation);

        // Update session final score (only computes when enough committee submissions exist).
        if ($action === 'submit') {
            \mod_oralinterview\local\scoring::compute_final_score($sessionid, $USER->id, false);
        }

        // Tell the user what happened.
        if (!isset($SESSION)) {
            global $SESSION;
        }
        $SESSION->oralinterview_success = ($action === 'submit')
            ? get_string('evaluation_submitted', 'oralinterview')
            : get_string('evaluation_saved', 'oralinterview');
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/evaluate.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('evaluate', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'), new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->navbar->add(get_string('evaluate', 'oralinterview'));

if ($spacui) {
    oralinterview_spacui_header($cm, 'manage', get_string('evaluate', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('evaluate', 'oralinterview') . ': ' . ($candidate ? format_string($candidate->fullname) : 'Unknown Candidate'));
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $spacui ? '<div class="alert alert-danger">' . s($error) . '</div>' : $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-xl-10">';
echo '<div class="card border-0 shadow-sm">';
echo '<div class="card-body p-4">';

echo '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">';
echo '<div>';
echo '<div class="fw-semibold">' . s($candidate ? fullname($candidate) : '—') . '</div>';
echo '<div class="text-muted small">#' . (int)$session->id . ' • ' . s(get_string('interview_date', 'oralinterview')) . ': ' . ($session->interviewdate ? userdate($session->interviewdate, '%Y-%m-%d %H:%M') : '—') . '</div>';
echo '</div>';
echo '</div>';

echo '<form method="post" action="" class="mt-3">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<div class="table-responsive">';
echo '<table class="table table-striped align-middle">';
echo '<thead><tr>';
echo '<th style="min-width: 320px;">' . get_string('questiontext', 'oralinterview') . '</th>';
echo '<th class="text-nowrap" style="width: 120px;">' . get_string('maxscore', 'oralinterview') . '</th>';
echo '<th class="text-nowrap" style="width: 140px;">' . get_string('score', 'oralinterview') . '</th>';
echo '<th style="min-width: 260px;">' . get_string('comment', 'oralinterview') . '</th>';
echo '</tr></thead><tbody>';

foreach ($questions as $question) {
    $existingscore = $scores[$question->id] ?? null;
    $scorevalue = $existingscore ? $existingscore->score : '';
    $commentvalue = $existingscore ? $existingscore->comment : '';
    
    echo '<tr>';
    echo '<td>' . format_text($question->questiontext, FORMAT_HTML, ['context' => $context]) . '</td>';
    echo '<td class="text-center">' . format_float($question->maxscore, 2, true) . '</td>';
    echo '<td><input type="number" min="0" max="' . $question->maxscore . '" step="0.1" name="score_' . $question->id . '" value="' . s($scorevalue) . '" class="form-control" /></td>';
    echo '<td><textarea name="comment_' . $question->id . '" rows="2" class="form-control" placeholder="' . s(get_string('comment', 'oralinterview')) . '">' . s($commentvalue) . '</textarea></td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

echo '<div class="d-flex justify-content-end gap-2 mt-3">';
echo '<button type="submit" name="save" value="1" class="btn btn-outline-secondary">' . get_string('save_draft', 'oralinterview') . '</button>';
echo '<button type="submit" name="submit" value="1" class="btn btn-primary">' . get_string('submit', 'oralinterview') . '</button>';
echo '</div>';
echo '</form>';

echo '</div></div></div></div>';

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
?>
