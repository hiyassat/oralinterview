<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/form/template_form.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// Simplified permission check - temporarily disabled
// access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cm->id, 'templateid' => $templateid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('template_edit', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
$jobid = (int)($oralinterview->jobid ?? 0);
$jobtitle = '';
if ($jobid) {
    $jobtitle = (string)$DB->get_field('planning_ready_jobs', 'job_title', ['jobid' => $jobid], IGNORE_MISSING);
}
$jobtitle = trim($jobtitle) !== '' ? $jobtitle : ($jobid ? (string)$jobid : '');

$customdata = [
    'id' => $cm->id,
    'jobtitle' => $jobtitle,
];
$form = new mod_oralinterview_template_form(null, $customdata);

if ($templateid) {
    $record = $DB->get_record('oralint_template', ['id' => $templateid, 'courseid' => $course->id], '*', MUST_EXIST);
    $form->set_data((object)[
        'id' => $cm->id,
        'name' => $record->name,
        'jobtitle' => $jobtitle,
        'jobtitle_display' => $jobtitle,
        'description' => $record->description,
        'templateid' => $record->id
    ]);
} else {
    $form->set_data((object)[
        'id' => $cm->id,
        'jobtitle' => $jobtitle,
        'jobtitle_display' => $jobtitle,
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

if ($data = $form->get_data()) {
    $data->description = trim($data->description);
    // Enforce job title from activity, not from user input.
    $data->jobtitle = $jobtitle;
    
    if (!empty($data->templateid)) {
        // Update existing template
        $record = new stdClass();
        $record->id = $data->templateid;
        $record->name = $data->name;
        $record->jobtitle = $data->jobtitle;
        $record->jobid = (int)($oralinterview->jobid ?? 0);
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
        $record->jobid = (int)($oralinterview->jobid ?? 0);
        $record->description = $data->description;
        $record->status = 'draft';
        $record->maxscore = 10;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $templateid = $DB->insert_record('oralint_template', $record);
    }

    redirect(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

$PAGE->navbar->add(get_string('templates', 'oralinterview'));
if ($spacui) {
    oralinterview_spacui_header($cm, 'templates', get_string('template_edit', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('template_edit', 'oralinterview'));
}
$form->display();
if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
