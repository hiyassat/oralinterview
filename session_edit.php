<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/oralinterview/classes/form/session_form.php');

use mod_oralinterview\form\session_form;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\session as session_persistent;
use mod_oralinterview\local\session_manager;

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$templates = $DB->get_records_menu('oralint_template', ['courseid' => $course->id], 'name ASC', 'id, name');
$candidates = $DB->get_records_menu('oralint_candidate', ['status' => 'active'], 'fullname ASC', 'id, fullname');
$enrolledusers = get_enrolled_users($context, 'mod/oralinterview:evaluate');
$committeeoptions = [];
foreach ($enrolledusers as $user) {
    $committeeoptions[$user->id] = fullname($user);
}

if (empty($templates)) {
    notice(get_string('session_no_templates', 'oralinterview'), new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]));
}
if (empty($candidates)) {
    notice(get_string('session_no_candidates', 'oralinterview'), new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]));
}

$customdata = [
    'templates' => $templates,
    'candidates' => $candidates,
    'users' => $committeeoptions
];
$form = new session_form(null, $customdata);

$defaults = [];
if ($sessionid) {
    $session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
    $defaults = [
        'sessionid' => $session->id,
        'templateid' => $session->templateid,
        'candidateid' => $session->candidateid,
        'committee' => session_manager::get_committee_userids($session->id),
        'interviewdate' => $session->interviewdate,
        'deadline' => $session->deadline,
        'requireall' => $session->requireall,
        'status' => $session->status
    ];
    $form->set_data((object)$defaults);
} else {
    $form->set_data((object)['status' => 'draft']);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $committeeids = array_map('intval', (array)($data->committee ?? []));
    if (empty($committeeids)) {
        print_error('session_committee_required', 'oralinterview');
    }

    if (!empty($data->sessionid)) {
        $session = session_persistent::get_instance($data->sessionid);
        $session->set('templateid', $data->templateid);
        $session->set('candidateid', $data->candidateid);
        $session->set('interviewdate', $data->interviewdate);
        $session->set('deadline', $data->deadline);
        $session->set('requireall', $data->requireall ? 1 : 0);
        $session->set('status', $data->status);
        $session->set('timemodified', time());
        $session->set('usermodified', $USER->id);
        $session->update();
        $sessionid = $session->get('id');
    } else {
        $session = session_persistent::create((object)[
            'courseid' => $course->id,
            'oralinterviewid' => $cm->id,
            'templateid' => $data->templateid,
            'candidateid' => $data->candidateid,
            'interviewdate' => $data->interviewdate,
            'deadline' => $data->deadline,
            'requireall' => $data->requireall ? 1 : 0,
            'status' => $data->status,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id
        ]);
        $sessionid = $session->get('id');
    }

    session_manager::assign_committee($sessionid, $committeeids);

    if ($data->status === 'active') {
        session_manager::ensure_session_questions($sessionid);
    }

    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id, 'sessionid' => $sessionid]));
$PAGE->set_title(get_string('session_manage', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));
$PAGE->navbar->add(get_string('session_add', 'oralinterview'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('session_add', 'oralinterview'));
$form->display();
echo $OUTPUT->footer();
