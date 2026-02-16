<?php
/**
 * Interview template questions – setup by job using mdl_exam_interview_competencies.
 *
 * Flow: Step 1 = select job → Step 2 = add/remove questions (competency → indicator → questions).
 *
 * @package   mod_oralinterview
 */

require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
require_once(__DIR__ . '/lib_template_questions.php');

oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER, $SESSION;

$id          = required_param('id', PARAM_INT);
$templateid  = required_param('templateid', PARAM_INT);
$step        = optional_param('step', 1, PARAM_INT);
$jobid       = optional_param('jobid', 0, PARAM_INT);
$action      = optional_param('action', '', PARAM_ALPHANUMEXT);
$questionid  = optional_param('questionid', 0, PARAM_INT);
$spacui      = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

if (!has_capability('mod/oralinterview:manage', $context)) {
    throw new moodle_exception('nopermission', 'error');
}

$oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
if (!empty($oralinterview->jobid)) {
    $jobid = (int) $oralinterview->jobid;
}

$template = $DB->get_record('oralint_template', ['id' => $templateid, 'oralinterviewid' => $cm->id], '*', MUST_EXIST);

// ---- Actions: add/remove question ----
if ($action === 'add' && $questionid > 0) {
    require_sesskey();
    $existing = $DB->get_record('oralint_template_q', ['templateid' => $templateid, 'questionid' => $questionid]);
    if (!$existing) {
        $question = $DB->get_record('question', ['id' => $questionid]);
        if ($question) {
            $DB->insert_record('oralint_template_q', (object)[
                'templateid'   => $templateid,
                'questionid'   => $questionid,
                'questiontext' => $question->questiontext,
                'rubric'       => '',
                'maxscore'     => 10,
                'sortorder'    => 0,
                'timecreated'  => time(),
                'timemodified' => time(),
                'usermodified' => $USER->id,
            ]);
        }
    }
    redirect(oralinterview_template_questions_return_url($id, $templateid, $jobid, (bool)$spacui));
}

if ($action === 'remove' && $questionid > 0) {
    require_sesskey();
    $DB->delete_records('oralint_template_q', ['templateid' => $templateid, 'questionid' => $questionid]);
    redirect(oralinterview_template_questions_return_url($id, $templateid, $jobid, (bool)$spacui));
}

// ---- Action: generate by weights ----
if ($action === 'generate_by_weights' && $jobid > 0) {
    require_sesskey();
    $total = (int) optional_param('total_questions', 0, PARAM_INT);
    $weights_raw = optional_param_array('weights', [], PARAM_FLOAT);
    $weights = [];
    foreach ($weights_raw as $cid => $w) {
        $weights[(int) $cid] = max(0, (float) $w);
    }
    $res = oralinterview_generate_template_questions_by_weights($templateid, $jobid, $total, $USER->id, $weights, (int)$course->id);
    $url = oralinterview_template_questions_return_url($id, $templateid, $jobid, (bool)$spacui);
    if (!empty($res['errors'])) {
        $SESSION->oralinterview_error = implode(' ', $res['errors']);
    } else {
        $SESSION->oralinterview_success = get_string('generate_by_weights_done', 'oralinterview', $res['added']);
    }
    redirect($url);
}

// ---- Step 2: ensure we have jobid and update template (name = job title + date) ----
if ($step == 2 && $jobid > 0) {
    $template->jobid = $jobid;
    $template->name = oralinterview_template_auto_name($jobid);
    $template->jobtitle = oralinterview_get_job_display_name($jobid);
    $template->timemodified = time();
    $template->usermodified = $USER->id;
    $DB->update_record('oralint_template', $template);
}

$PAGE->set_url('/mod/oralinterview/template_questions_advanced.php', [
    'id' => $id, 'templateid' => $templateid, 'step' => $step, 'jobid' => $jobid,
]);
$PAGE->set_title(get_string('template_questions', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

if ($spacui) {
    oralinterview_spacui_header($cm, 'templates', get_string('template_questions', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('template_questions', 'oralinterview'));
}

// Flash messages from generate_by_weights.
if (!empty($SESSION->oralinterview_success)) {
    echo '<div class="alert alert-success mb-3">' . s($SESSION->oralinterview_success) . '</div>';
    unset($SESSION->oralinterview_success);
}
if (!empty($SESSION->oralinterview_error)) {
    echo '<div class="alert alert-danger mb-3">' . s($SESSION->oralinterview_error) . '</div>';
    unset($SESSION->oralinterview_error);
}

// Interview name section: template name, job title, and exam frame weights (exam_percent / interview_percent) when available.
echo '<div class="card mb-4"><div class="card-body">';
echo '<h5 class="card-title">' . format_string($template->name) . '</h5>';
echo '<p class="text-muted mb-0">' . format_string($template->jobtitle) . '</p>';
if ($jobid > 0) {
    require_once(__DIR__ . '/lib_enrollment.php');
    $frame = oralinterview_get_exam_frame_display_for_job($jobid, (int)$course->id);
    if ($frame) {
        $exam = isset($frame->exam_percent) ? (int)$frame->exam_percent : null;
        $interview = isset($frame->interview_percent) ? (int)$frame->interview_percent : null;
        if ($exam !== null && $interview !== null) {
            echo '<p class="mb-0 mt-2 small text-secondary"><strong>' . s(oralinterview_format_exam_frame_weights($exam, $interview)) . '</strong></p>';
        }
        $sel = isset($frame->interview_selection) ? strtolower((string)$frame->interview_selection) : '';
        $top_n = isset($frame->interview_top_n) ? (int)$frame->interview_top_n : 0;
        if ($sel === 'top' && $top_n > 0) {
            echo '<p class="mb-0 small text-secondary">' . s(oralinterview_format_exam_frame_top_n($top_n)) . '</p>';
        }
    }
}
echo '</div></div>';

// ---- Step 1: Job selection ----
if ($step == 1) {
    if ($jobid > 0) {
        redirect(new moodle_url('/mod/oralinterview/template_questions_advanced.php', [
            'id' => $id, 'templateid' => $templateid, 'step' => 2, 'jobid' => $jobid,
        ]));
    }
    echo oralinterview_template_questions_render_step1_jobs($id, $templateid, (bool)$spacui, oralinterview_get_approved_jobs());
}

// ---- Step 2: Questions (competency → indicator → questions from exam_interview_competencies) ----
if ($step == 2 && $jobid > 0) {
    echo oralinterview_template_questions_render_step2_questions($id, $templateid, $jobid, $cm, (bool)$spacui);
}

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
