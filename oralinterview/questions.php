<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Managers only.
$can_manage = has_capability('mod/oralinterview:manage', $context);
if (!$can_manage) {
    throw new moodle_exception('nopermission', 'error');
}

// Resolve the interview question set (internal template).
$tpl = oralinterview_get_question_set_template($cm, $course);

// Go to the existing question selection workflow using the internal template.
redirect(new moodle_url('/mod/oralinterview/template_questions_advanced.php', [
    'id' => $cm->id,
    'templateid' => $tpl->id,
    'step' => 2,
    'jobid' => (int)$DB->get_field('oralinterview', 'jobid', ['id' => $cm->instance], IGNORE_MISSING),
    'spacui' => (int)$spacui,
]));

