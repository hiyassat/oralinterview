<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$templateid = required_param('templateid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Get template
$template = $DB->get_record('oralint_template', ['id' => $templateid, 'oralinterviewid' => $cm->id], '*', MUST_EXIST);

// Handle adding/removing questions from template
if ($action === 'add' && $questionid) {
    require_sesskey();
    
    // Check if question is already in template
    $existing = $DB->get_record('oralint_template_q', ['templateid' => $templateid, 'questionid' => $questionid]);
    if (!$existing) {
        // Get question details from question bank
        $question = $DB->get_record('question', ['id' => $questionid]);
        if ($question) {
            $template_question = (object)[
                'templateid' => $templateid,
                'questionid' => $questionid,
                'questiontext' => $question->questiontext,
                'rubric' => '',
                'maxscore' => 10,
                'sortorder' => $DB->count_records('oralint_template_q', ['templateid' => $templateid]) + 1,
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => $USER->id
            ];
            $DB->insert_record('oralint_template_q', $template_question);
        }
    }
    redirect(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
}

if ($action === 'remove' && $questionid) {
    require_sesskey();
    $DB->delete_records('oralint_template_q', ['templateid' => $templateid, 'questionid' => $questionid]);
    redirect(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
$PAGE->set_title(get_string('templatequestions', 'oralinterview'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('templatequestions', 'oralinterview'));

// Template info
echo '<div class="card mb-3">
        <div class="card-body">
            <h5>' . format_string($template->name) . '</h5>
            <p class="text-muted">' . format_string($template->jobtitle) . '</p>
        </div>
    </div>';

// Get interview questions from question bank
$interview_questions = $DB->get_records_sql("
    SELECT q.*, qtm.question_type
    FROM {question} q
    LEFT JOIN {question_type_mapping} qtm ON qtm.question_id = q.id
    WHERE qtm.question_type = 'interview'
    ORDER BY q.name ASC
");

// Get current template questions
$template_questions = $DB->get_records('oralint_template_q', ['templateid' => $templateid], 'sortorder ASC');
$template_question_ids = array_column($template_questions, 'questionid');

echo '<div class="row">';

// Available Questions Column
echo '<div class="col-md-6">
        <h5>' . get_string('available_interview_questions', 'oralinterview') . '</h5>
        <div class="list-group" style="max-height: 400px; overflow-y: auto;">';

if ($interview_questions) {
    foreach ($interview_questions as $question) {
        if (!in_array($question->id, $template_question_ids)) {
            echo '<div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Question #' . $question->id . ' - ' . format_string($question->name) . '</h6>
                            <small class="text-muted">' . 
                                (strlen(strip_tags($question->questiontext)) > 0 ? 
                                    substr(strip_tags($question->questiontext), 0, 100) . (strlen(strip_tags($question->questiontext)) > 100 ? '...' : '') : 
                                    'No question text available') . 
                            '</small>
                        </div>
                        <a href="' . (new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid, 'action' => 'add', 'questionid' => $question->id, 'sesskey' => sesskey()]))->out() . '" 
                           class="btn btn-sm btn-primary">+</a>
                    </div>
                </div>';
        }
    }
} else {
    echo '<div class="list-group-item text-muted">' . get_string('no_interview_questions', 'oralinterview') . '</div>';
}

echo '</div>
    </div>';

// Template Questions Column
echo '<div class="col-md-6">
        <h5>' . get_string('template_questions', 'oralinterview') . '</h5>
        <div class="list-group" style="max-height: 400px; overflow-y: auto;">';

if ($template_questions) {
    foreach ($template_questions as $tq) {
        $question = $DB->get_record('question', ['id' => $tq->questionid]);
        if ($question) {
            echo '<div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">' . format_string($question->name) . '</h6>
                            <small class="text-muted">Max Score: ' . $tq->maxscore . '</small>
                        </div>
                        <a href="' . (new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid, 'action' => 'remove', 'questionid' => $question->id, 'sesskey' => sesskey()]))->out() . '" 
                           class="btn btn-sm btn-danger" onclick="return confirm(\'Remove this question from template?\')">×</a>
                    </div>
                </div>';
        }
    }
} else {
    echo '<div class="list-group-item text-muted">' . get_string('no_template_questions', 'oralinterview') . '</div>';
}

echo '</div>
    </div>';

echo '</div>'; // End row

echo $OUTPUT->footer();
