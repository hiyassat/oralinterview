<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/oralinterview/classes/form/candidate_form.php');

use mod_oralinterview\form\candidate_form;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\candidate as candidate_persistent;

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$candidateid = optional_param('candidateid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/candidate_edit.php', ['id' => $cm->id, 'candidateid' => $candidateid]));
$PAGE->set_title(get_string('candidate_edit', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('candidates', 'oralinterview'), new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('candidate_edit', 'oralinterview'));

$form = new candidate_form();
if ($candidateid) {
    $candidate = $DB->get_record('oralint_candidate', ['id' => $candidateid], '*', MUST_EXIST);
    $form->set_data((object)[
        'fullname' => $candidate->fullname,
        'email' => $candidate->email,
        'phone' => $candidate->phone,
        'nationalid' => $candidate->nationalid,
        'linkeduserid' => $candidate->linkeduserid,
        'candidateid' => $candidate->id
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    if (!empty($data->candidateid)) {
        $candidate = candidate_persistent::get_instance($data->candidateid);
        $candidate->set('fullname', $data->fullname);
        $candidate->set('email', $data->email);
        $candidate->set('phone', $data->phone);
        $candidate->set('nationalid', $data->nationalid);
        $candidate->set('linkeduserid', $data->linkeduserid);
        $candidate->set('timemodified', time());
        $candidate->set('usermodified', $USER->id);
        $candidate->update();
    } else {
        candidate_persistent::create((object)[
            'fullname' => $data->fullname,
            'email' => $data->email,
            'phone' => $data->phone,
            'nationalid' => $data->nationalid,
            'linkeduserid' => $data->linkeduserid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id
        ]);
    }
    redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('candidate_edit', 'oralinterview'));
$form->display();
echo $OUTPUT->footer();
