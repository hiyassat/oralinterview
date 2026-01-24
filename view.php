<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');

$id = optional_param('id', 0, PARAM_INT);
if (!$id) {
    throw new moodle_exception('invalidcoursemodule');
}

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/oralinterview:view', $context);

$PAGE->set_url('/mod/oralinterview/view.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('modulename', 'oralinterview'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulename', 'oralinterview'));
echo $OUTPUT->box(get_string('pluginadministration', 'oralinterview'));
echo $OUTPUT->footer();
