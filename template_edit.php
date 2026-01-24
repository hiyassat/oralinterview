<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/oralinterview/classes/form/template_form.php');

use mod_oralinterview\form\template_form;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\template as template_persistent;

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cm->id, 'templateid' => $templateid]));
$PAGE->set_title(get_string('template_edit', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$form = new template_form();

if ($templateid) {
    $record = $DB->get_record('oralint_template', ['id' => $templateid, 'courseid' => $course->id], '*', MUST_EXIST);
    $form->set_data((object)[
        'name' => $record->name,
        'jobtitle' => $record->jobtitle,
        'description' => $record->description,
        'templateid' => $record->id
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $data->description = trim($data->description);
    $data->usermodified = $USER->id;
    $data->timemodified = time();

    if (!empty($data->templateid)) {
        $template = template_persistent::get_instance($data->templateid);
        $template->set('name', $data->name);
        $template->set('jobtitle', $data->jobtitle);
        $template->set('description', $data->description);
        $template->set('timemodified', $data->timemodified);
        $template->set('usermodified', $data->usermodified);
        $template->update();
    } else {
        template_persistent::create((object)[
            'courseid' => $course->id,
            'name' => $data->name,
            'jobtitle' => $data->jobtitle,
            'description' => $data->description,
            'status' => 'draft',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $data->usermodified
        ]);
    }

    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}

$PAGE->navbar->add(get_string('templates', 'oralinterview'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('template_edit', 'oralinterview'));
$form->display();
echo $OUTPUT->footer();
