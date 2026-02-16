<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/local/scoring.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();
global $DB, $PAGE, $OUTPUT, $USER, $CFG;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$targetsessionid = optional_param('sessionid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Job scope for this activity (used to validate templates/sessions).
$oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
$jobid = (int)($oralinterview->jobid ?? 0);

// Check capabilities (needed early for recalculate check)
$can_manage = has_capability('mod/oralinterview:manage', $context);
$is_committee_member = $DB->record_exists_sql("
    SELECT 1
    FROM {oralint_committee} oc
    JOIN {oralint_session} s ON s.id = oc.sessionid
    WHERE s.oralinterviewid = :oralinterviewid
    AND oc.userid = :userid
", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);

if ($action === 'recalculate' && $targetsessionid) {
    require_sesskey();
    // Only managers can recalculate
    if (!$can_manage) {
        throw new moodle_exception('nopermission', 'error');
    }

    // Recalculate using the scoring service (updates oralint_session finalscore/finalpercent).
    $result = \mod_oralinterview\local\scoring::compute_final_score($targetsessionid, $USER->id, true);
    if ($result && isset($result->finalscore)) {
        $SESSION->oralinterview_success = get_string('session_recalculate_done', 'oralinterview', [
            'score' => format_float($result->finalscore, 2, true),
            'percent' => format_float($result->finalpercent ?? 0, 2, true),
        ]);
    } else {
        $SESSION->oralinterview_error = get_string('session_recalculate_nothing', 'oralinterview');
    }
    
redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

$PAGE->set_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]);
$PAGE->set_title(get_string('session_manage', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));

// Get sessions - filter by committee membership if user is not a manager
if ($can_manage) {
    // Managers see all sessions
    $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->instance], 'interviewdate DESC');
} else if ($is_committee_member) {
    // Committee members see only sessions they're assigned to
    $sessions = $DB->get_records_sql("
        SELECT s.*
        FROM {oralint_session} s
        JOIN {oralint_committee} oc ON oc.sessionid = s.id
        WHERE s.oralinterviewid = :oralinterviewid
        AND oc.userid = :userid
        ORDER BY s.interviewdate DESC
    ", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);
} else {
    // Regular users see all sessions (if they have view capability)
    $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->instance], 'interviewdate DESC');
}

if ($spacui) {
    oralinterview_spacui_header($cm, 'manage', get_string('session_manage', 'oralinterview'));
} else {
    echo $OUTPUT->header();
}

// Show debug messages from session creation
global $SESSION;
if (!empty($SESSION->oralinterview_success)) {
    echo $spacui
        ? '<div class="alert alert-success">' . s($SESSION->oralinterview_success) . '</div>'
        : $OUTPUT->notification($SESSION->oralinterview_success, 'notifysuccess');
    unset($SESSION->oralinterview_success);
}
if (!empty($SESSION->oralinterview_error)) {
    echo $spacui
        ? '<div class="alert alert-danger">' . s($SESSION->oralinterview_error) . '</div>'
        : $OUTPUT->notification($SESSION->oralinterview_error, 'notifyerror');
    unset($SESSION->oralinterview_error);
}
// Intentionally do not show debug banners in production UI.
if (!$spacui) {
    echo $OUTPUT->heading(get_string('session_manage', 'oralinterview'));
    echo $OUTPUT->box(get_string('session_manage_hint', 'oralinterview'));
}
if ($jobid) {
    $jobline = get_string('jobid', 'oralinterview') . ': ' . s($jobid);
    echo $spacui ? '<div class="alert alert-info">' . $jobline . '</div>' : $OUTPUT->notification($jobline, 'notifymessage');
}

// إضافة جلسة - معطّل لأن التسجيل التلقائي ينشئ الجلسات. استعد للتشغيل عند الحاجة. (Add Session - disabled, auto-enroll creates sessions)
// if ($can_manage) {
//     echo '<a href="session_edit.php?id=' . $cm->id . '&spacui=' . (int)$spacui . '" class="btn btn-primary mb-3">' . get_string('session_add', 'oralinterview') . '</a>';
// }

echo '<div class="table-responsive">';
echo '<table class="' . ($spacui ? 'table table-striped align-middle' : 'generaltable') . '">';
echo '<thead><tr>';
echo '<th>' . get_string('session_candidate', 'oralinterview') . '</th>';
echo '<th>' . get_string('session_status', 'oralinterview') . '</th>';
echo '<th>' . get_string('session_progress', 'oralinterview') . '</th>';
echo '<th>' . get_string('session_finalscore', 'oralinterview') . '</th>';
echo '<th>' . get_string('session_actions', 'oralinterview') . '</th>';
echo '</tr></thead><tbody>';

if (empty($sessions)) {
    echo '</tbody></table></div>';
    if ($spacui) {
        echo '<div class="alert alert-info mt-3">لم يتم إنشاء أي جلسات بعد.</div>';
        echo '<div class="d-flex gap-2 flex-wrap">';
        echo '<a class="btn btn-outline-secondary" href="' . (new moodle_url('/interviews/index.php'))->out() . '">رجوع</a>';
        echo '</div>';
    } else {
        echo $OUTPUT->notification(get_string('session_no_sessions', 'oralinterview'), 'notifymessage');
    }
} else {
    foreach ($sessions as $session) {
        $candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
        $submitted = $DB->count_records_select('oralint_eval', 'sessionid = ? AND status = ?', [$session->id, 'submitted']);
        $needed = max(1, $DB->count_records('oralint_committee', ['sessionid' => $session->id]));
        $progress = '<span>' . $submitted . ' / ' . $needed . '</span>';
        $statuskey = 'session_status_' . ($session->status ?? 'draft');
        if (get_string_manager()->string_exists($statuskey, 'oralinterview')) {
            $statuslabel = get_string($statuskey, 'oralinterview');
        } else {
            $statuslabel = s($session->status ?? 'draft');
        }
        $final = '-';
        if ($session->finalscore !== null) {
            $final = format_float($session->finalscore, 2, true) . ' (' . format_float($session->finalpercent ?? 0, 2, true) . '%)';
        }
        
        // Check if user is committee member for this specific session
        $is_session_committee = $DB->record_exists('oralint_committee', [
            'sessionid' => $session->id,
            'userid' => $USER->id
        ]);
        
        $actions = '<div class="d-flex flex-wrap gap-2 justify-content-end">';
        
        // Evaluate button - only when session is not locked.
        if (($session->status ?? '') !== 'locked' && ($is_session_committee || $can_manage)) {
            $actions .= '<a href="evaluate.php?id=' . $cm->id . '&sessionid=' . $session->id . '&spacui=' . (int)$spacui . '" class="btn btn-primary btn-sm">' . get_string('session_evaluate', 'oralinterview') . '</a>';
        }
        
        // Management actions - only for managers
        if ($can_manage) {
            $actions .= '<a href="session_edit.php?id=' . $cm->id . '&sessionid=' . $session->id . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . get_string('edit_session', 'oralinterview') . '</a>';
            // Commented out for now – restore if needed later.
            // $actions .= '<a href="manage.php?id=' . $cm->id . '&action=recalculate&sessionid=' . $session->id . '&sesskey=' . sesskey() . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . get_string('session_recalculate', 'oralinterview') . '</a>';
            // $actions .= '<a href="override.php?id=' . $cm->id . '&sessionid=' . $session->id . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . get_string('session_override', 'oralinterview') . '</a>';
            // $actions .= '<a href="reopen.php?id=' . $cm->id . '&sessionid=' . $session->id . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . get_string('session_reopen', 'oralinterview') . '</a>';
            $actions .= '<a href="lock.php?id=' . $cm->id . '&sessionid=' . $session->id . '&mode=' . ($session->status === 'locked' ? 'unlock' : 'lock') . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . ($session->status === 'locked' ? get_string('session_unlock', 'oralinterview') : get_string('session_lock', 'oralinterview')) . '</a>';
        }
        
        // Report – commented out for now; restore if needed later.
        // if ($can_manage || $is_session_committee) {
        //     $actions .= '<a href="session_report.php?id=' . $cm->id . '&sessionid=' . $session->id . '&spacui=' . (int)$spacui . '" class="btn btn-outline-secondary btn-sm">' . get_string('session_report', 'oralinterview') . '</a>';
        // }
        $actions .= '</div>';
        
        echo '<tr>';
        echo '<td>' . ($candidate ? format_string($candidate->fullname) : '-') . '</td>';
        echo '<td>' . $statuslabel . '</td>';
        echo '<td>' . $progress . '</td>';
        echo '<td>' . $final . '</td>';
        echo '<td>' . $actions . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';

}

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
