<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/form/candidate_form.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$candidateid = optional_param('candidateid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// require_login may reset language; enforce Arabic in SPAC UI.
if ($spacui && function_exists('force_current_language')) {
    force_current_language('ar');
}
// Simplified permission check - temporarily disabled
// access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/candidate_edit.php', ['id' => $cm->id, 'candidateid' => $candidateid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('candidate_edit', 'oralinterview'));
$PAGE->set_heading($course->fullname);

// Get enrolled users for dropdown
$enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
$user_options = [0 => get_string('select_user', 'oralinterview') . '...']; // Add empty option
foreach ($enrolled_users as $user) {
    $user_options[$user->id] = fullname($user) . ' (' . $user->email . ')';
}

$customdata = [
    'id' => $cm->id,
    'enrolled_users' => $user_options
];
$form = new mod_oralinterview_candidate_form(null, $customdata);

if ($candidateid) {
    $candidate = $DB->get_record('oralint_candidate', ['id' => $candidateid], '*', MUST_EXIST);
    $form->set_data((object)[
        'id' => $cm->id,
        'fullname' => $candidate->fullname,
        'email' => $candidate->email,
        'phone' => $candidate->phone,
        'nationalid' => $candidate->nationalid,
        'linkeduserid' => $candidate->linkeduserid,
        'candidateid' => $candidate->id
    ]);
} else {
    $form->set_data((object)['id' => $cm->id]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

if ($data = $form->get_data()) {
    if (!empty($data->candidateid)) {
        // Update existing candidate
        $record = new stdClass();
        $record->id = $data->candidateid;
        
        // Get user data from user table
        $selected_user = $DB->get_record('user', ['id' => $data->selected_userid], 'firstname, lastname, email, idnumber');
        if ($selected_user) {
            $record->fullname = fullname($selected_user);
            $record->email = $selected_user->email;
            $record->phone = ''; // No phone field in mdl_user
            $record->nationalid = $selected_user->idnumber ?? '';
            $record->linkeduserid = $data->selected_userid;
        }
        
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->update_record('oralint_candidate', $record);
    } else {
        // Create new candidate
        $record = new stdClass();
        
        // Get user data from user table
        $selected_user = $DB->get_record('user', ['id' => $data->selected_userid], 'firstname, lastname, email, idnumber');
        if ($selected_user) {
            $record->fullname = fullname($selected_user);
            $record->email = $selected_user->email;
            $record->phone = ''; // No phone field in mdl_user
            $record->nationalid = $selected_user->idnumber ?? '';
            $record->linkeduserid = $data->selected_userid;
        }
        
        $record->status = 'active';
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $candidateid = $DB->insert_record('oralint_candidate', $record);
    }

    redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

$PAGE->navbar->add(get_string('candidates', 'oralinterview'), new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->navbar->add(get_string('candidate_edit', 'oralinterview'));

if ($spacui) {
    oralinterview_spacui_header($cm, 'candidates', get_string('candidate_edit', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('candidate_edit', 'oralinterview'));
}
$form->display();
if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
