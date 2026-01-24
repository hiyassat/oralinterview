<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/oralinterview/classes/form/template_question_form.php');

use mod_oralinterview\form\template_question_form;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\template_question as template_question_persistent;

$id = required_param('id', PARAM_INT);
$templateid = required_param('templateid', PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$template = $DB->get_record('oralint_template', ['id' => $templateid, 'courseid' => $course->id], '*', MUST_EXIST);

if ($action === 'delete' && $questionid) {
    require_sesskey();
    $record = template_question_persistent::get_instance($questionid);
    if ($record->get('templateid') === $templateid) {
        $record->delete();
    }
    redirect(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
}

$form = new template_question_form();
$defaultdata = (object)['templateid' => $templateid];

if ($questionid) {
    $question = $DB->get_record('oralint_template_q', ['id' => $questionid, 'templateid' => $templateid], '*', MUST_EXIST);
    $defaultdata->questiontext = $question->questiontext;
    $defaultdata->rubric = $question->rubric;
    $defaultdata->maxscore = $question->maxscore;
    $defaultdata->questionid = $question->id;
}

$form->set_data($defaultdata);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $data->questiontext = trim($data->questiontext);
    $data->rubric = trim($data->rubric);
    $data->maxscore = (int)$data->maxscore;

    if (!empty($data->questionid)) {
        $question = template_question_persistent::get_instance($data->questionid);
        $question->set('questiontext', $data->questiontext);
        $question->set('rubric', $data->rubric);
        $question->set('maxscore', $data->maxscore);
        $question->set('timemodified', time());
        $question->set('usermodified', $USER->id);
        $question->update();
    } else {
        $count = $DB->count_records('oralint_template_q', ['templateid' => $templateid]);
        template_question_persistent::create((object)[
            'templateid' => $templateid,
            'questiontext' => $data->questiontext,
            'rubric' => $data->rubric,
            'maxscore' => $data->maxscore ?: 10,
            'sortorder' => $count + 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id
        ]);
    }

    redirect(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cm->id, 'templateid' => $templateid]));
$PAGE->set_title(get_string('templatequestions', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('templates', 'oralinterview'), new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('templatequestions', 'oralinterview'));

$questions = $DB->get_records('oralint_template_q', ['templateid' => $templateid], 'sortorder ASC, id ASC');
$renderer = $PAGE->get_renderer('mod_oralinterview');

echo $OUTPUT->header();
echo $renderer->render_template_questions($template, $questions, $cm->id);
$form->display();
echo $OUTPUT->footer();
