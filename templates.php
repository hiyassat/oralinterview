<?php
require_once('../../config.php');

use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\output\renderer;

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
$PAGE->set_title(get_string('templates', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$templates = $DB->get_records('oralint_template', ['courseid' => $course->id]);
$renderer = $PAGE->get_renderer('mod_oralinterview');

echo $OUTPUT->header();
echo $renderer->render_templates_list($templates, $cm->id);
echo $OUTPUT->footer();
