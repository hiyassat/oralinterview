<?php
require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('mod/oralinterview:view', $context);

$url = new moodle_url('/mod/oralinterview/index.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('modulenameplural', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$PAGE->navigation->add(get_string('modulenameplural', 'oralinterview'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'oralinterview'));

echo $OUTPUT->footer();
