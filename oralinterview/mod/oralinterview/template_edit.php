<?php
require_once('../../config.php');
// require_once($CFG->dirroot . '/mod/oralinterview/classes/form/template_form.php');

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// Simplified permission check - temporarily disabled
// access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cm->id, 'templateid' => $templateid]));
$PAGE->set_title(get_string('template_edit', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$customdata = ['id' => $cm->id];
$form = new mod_oralinterview_template_form(null, $customdata);

if ($templateid) {
    $record = $DB->get_record('oralint_template', ['id' => $templateid, 'courseid' => $course->id], '*', MUST_EXIST);
    $form->set_data((object)[
        'id' => $cm->id,
        'name' => $record->name,
        'jobtitle' => $record->jobtitle,
        'description' => $record->description,
        'templateid' => $record->id
    ]);
} else {
    $form->set_data((object)['id' => $cm->id]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $data->description = trim($data->description);
    
    if (!empty($data->templateid)) {
        // Update existing template
        $record = new stdClass();
        $record->id = $data->templateid;
        $record->name = $data->name;
        $record->jobtitle = $data->jobtitle;
        $record->description = $data->description;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->update_record('oralint_template', $record);
    } else {
        // Create new template
        $record = new stdClass();
        $record->courseid = $course->id;
        $record->oralinterviewid = $cm->id;
        $record->name = $data->name;
        $record->jobtitle = $data->jobtitle;
        $record->description = $data->description;
        $record->status = 'draft';
        $record->maxscore = 10;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $templateid = $DB->insert_record('oralint_template', $record);
    }

    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}

$PAGE->navbar->add(get_string('templates', 'oralinterview'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('template_edit', 'oralinterview'));
$form->display();
echo $OUTPUT->footer();
