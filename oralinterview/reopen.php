<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER, $CFG;

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$evalid = optional_param('evalid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/oralinterview:reopen', $context);

$session = $DB->get_record('oralint_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);

$PAGE->set_url('/mod/oralinterview/reopen.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'evalid' => $evalid, 'spacui' => $spacui]);
$PAGE->set_title(get_string('session_reopen', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));
$PAGE->navbar->add(get_string('session_reopen', 'oralinterview'));

if ($spacui) {
    oralinterview_spacui_header($cm, 'manage', get_string('session_reopen', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('session_reopen', 'oralinterview'));
}

if (!$evalid) {
    $evaluations = $DB->get_records('oralint_eval', ['sessionid' => $sessionid], 'userid ASC');
    
    echo '<table class="generaltable">';
    echo '<thead><tr>';
    echo '<th>' . get_string('fullname') . '</th>';
    echo '<th>' . get_string('status') . '</th>';
    echo '<th>' . get_string('session_actions', 'oralinterview') . '</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($evaluations as $evaluation) {
        $user = $DB->get_record('user', ['id' => $evaluation->userid]);
        $actions = '<a class="btn btn-outline-secondary btn-sm" href="reopen.php?id=' . $cm->id . '&sessionid=' . $sessionid . '&evalid=' . $evaluation->id . '&spacui=' . (int)$spacui . '">' . get_string('session_reopen', 'oralinterview') . '</a>';
        echo '<tr>';
        echo '<td>' . fullname($user) . '</td>';
        echo '<td>' . get_string('evaluation_status_' . $evaluation->status, 'oralinterview') . '</td>';
        echo '<td>' . $actions . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    if ($spacui) {
        oralinterview_spacui_footer();
    } else {
        echo $OUTPUT->footer();
    }
    exit;
}

$evaluation = $DB->get_record('oralint_eval', ['id' => $evalid, 'sessionid' => $sessionid], '*', MUST_EXIST);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $reason = trim(required_param('reason', PARAM_TEXT));
    if ($reason === '') {
        $errors[] = get_string('reopen_reason_required', 'oralinterview');
    }
    if (empty($errors)) {
        $evaluation->status = 'notstarted';
        $evaluation->submitreason = $reason;
        $evaluation->timemodified = time();
        $evaluation->usermodified = $USER->id;
        $DB->update_record('oralint_eval', $evaluation);
        
        // Simplified logging - disabled for now
        // audit_service::log('evaluation_reopened', 'evaluation', $sessionid, $USER->id, null, null, $reason, 'u');
        
        // Simplified event trigger - disabled for now
        /*
        $event = evaluation_reopened::create([
            'objectid' => $evaluation->id,
            'context' => $context,
            'other' => ['sessionid' => $sessionid]
        ]);
        $event->trigger();
        */
        
        // Simplified scoring - disabled for now
        // scoring_service::compute_final_score($sessionid, $USER->id, true);
        
        redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
}

echo '<p>' . get_string('reopen_instruction', 'oralinterview') . '</p>';
echo '<form method="post" action="' . $PAGE->url . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
echo '<p>' . get_string('reopen_reason_label', 'oralinterview') . '</p>';
echo '<textarea name="reason" rows="4" cols="60" required>' . s($reason ?? '') . '</textarea>';
echo '<br><br>';
echo '<input type="submit" value="' . get_string('reopen_submit', 'oralinterview') . '" class="btn btn-primary" />';
echo '</form>';

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
