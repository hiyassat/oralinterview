<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/local/access.php');
require_once(__DIR__ . '/classes/local/persistent/candidate.php');
require_once(__DIR__ . '/lib_enrollment.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\candidate as candidate_persistent;

global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SESSION;

$id = required_param('id', PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// Check if user is a committee member for any session in this activity
$is_committee_member = $DB->record_exists_sql("
    SELECT 1
    FROM {oralint_committee} oc
    JOIN {oralint_session} s ON s.id = oc.sessionid
    WHERE s.oralinterviewid = :oralinterviewid
    AND oc.userid = :userid
", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);

$can_manage = has_capability('mod/oralinterview:manage', $context);
$can_manage_candidates = has_capability('mod/oralinterview:managecandidates', $context);
$can_view = has_capability('mod/oralinterview:view', $context);

// Candidates management should be restricted to managers/candidate managers only.
if (!$can_manage && !$can_manage_candidates) {
    require_login($course, true, $cm);
    throw new moodle_exception('nopermission', 'error');
}

// Remove candidate from this interview (deletes their session(s) + dependent data).
$action = optional_param('action', '', PARAM_ALPHA);
$candidateid = optional_param('candidateid', 0, PARAM_INT);
if (($action === 'remove' || $action === 'delete') && $candidateid) {
    require_sesskey();
    // Only managers/candidate managers can remove.
    if (!$can_manage && !$can_manage_candidates) {
        throw new moodle_exception('nopermission', 'error');
    }
    // Find all sessions for this interview + candidate.
    $sessions_to_delete = $DB->get_records('oralint_session', [
        'oralinterviewid' => $cm->instance,
        'candidateid' => $candidateid
    ], '', 'id');
    foreach ($sessions_to_delete as $s) {
        $sid = (int)$s->id;
        // Delete scores -> evaluations.
        $evals = $DB->get_records('oralint_eval', ['sessionid' => $sid], '', 'id');
        if ($evals) {
            $evalids = array_map('intval', array_keys($evals));
            $DB->delete_records_list('oralint_score', 'evaluationid', $evalids);
            $DB->delete_records_list('oralint_eval', 'id', $evalids);
        }
        // Delete committee, session questions, audit, session.
        $DB->delete_records('oralint_committee', ['sessionid' => $sid]);
        $DB->delete_records('oralint_session_q', ['sessionid' => $sid]);
        $DB->delete_records('oralint_audit', ['sessionid' => $sid]);
        $DB->delete_records('oralint_session', ['id' => $sid]);
    }

    $SESSION->oralinterview_success = get_string('candidate_removed', 'oralinterview');
    redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

// Sync candidates from exam (test_session_assignments for this job's quiz/session).
// Support both GET and POST for action (form posts action=sync_from_exam).
$sync_from_post = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'sync_from_exam');
if (($action === 'sync_from_exam' || $sync_from_post) && ($can_manage || $can_manage_candidates)) {
    if (function_exists('error_log')) {
        error_log('OralInterview sync: request detected cm=' . $cm->id . ' instance=' . $cm->instance);
    }
    require_sesskey();
    $oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], 'id, jobid', MUST_EXIST);
    $jobid = (int) ($oralinterview->jobid ?? 0);
    $redirect_params = ['id' => $cm->id, 'spacui' => $spacui];
    if ($jobid > 0) {
        require_once(__DIR__ . '/spacui.php');
        oralinterview_get_question_set_template($cm, $course);
        $result = oralinterview_sync_candidates_from_exam((int) $cm->instance, (int) $course->id, $jobid, (int) $cm->id);
        $msg = get_string('sync_candidates_done', 'oralinterview', (object) [
            'synced' => $result['synced'],
            'skipped' => $result['skipped'],
        ]);
        if (!empty($result['errors'])) {
            $msg .= ' ' . implode(' ', $result['errors']);
        }
        $SESSION->oralinterview_success = $msg;
        $redirect_params['sync_added'] = $result['synced'];
        $redirect_params['sync_skipped'] = $result['skipped'];
        if (!empty($result['errors'])) {
            $redirect_params['sync_err'] = 1;
        }
    } else {
        $SESSION->oralinterview_success = get_string('sync_candidates_no_job', 'oralinterview');
        $redirect_params['sync_err'] = 1;
    }
    if (function_exists('error_log')) {
        error_log('OralInterview sync: redirect added=' . ($redirect_params['sync_added'] ?? 0) . ' skipped=' . ($redirect_params['sync_skipped'] ?? 0));
    }
    redirect(new moodle_url('/mod/oralinterview/candidates.php', $redirect_params));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->set_title(get_string('candidates', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('candidates', 'oralinterview'));

if ($spacui) {
    oralinterview_spacui_header($cm, 'candidates', get_string('candidates', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('candidates', 'oralinterview'));
}

// Show sync/action result: from session (after redirect) or from URL params (so it's never lost).
$sync_added = optional_param('sync_added', -1, PARAM_INT);
$sync_skipped = optional_param('sync_skipped', -1, PARAM_INT);
$sync_err = optional_param('sync_err', 0, PARAM_INT);
if (!empty($SESSION->oralinterview_success)) {
    $flashmsg = $SESSION->oralinterview_success;
    unset($SESSION->oralinterview_success);
    echo $OUTPUT->notification($flashmsg, 'notifysuccess');
} elseif ($sync_added >= 0 || $sync_skipped >= 0 || $sync_err) {
    if ($sync_err) {
        $flashmsg = get_string('sync_candidates_no_frame', 'oralinterview') . ' ' . get_string('sync_candidates_no_users', 'oralinterview');
    } else {
        $flashmsg = get_string('sync_candidates_done', 'oralinterview', (object) [
            'synced' => $sync_added >= 0 ? $sync_added : 0,
            'skipped' => $sync_skipped >= 0 ? $sync_skipped : 0,
        ]);
    }
    echo '<div id="oralinterview-sync-result" class="alert alert-success" role="alert" style="margin:1em 0;">' . s($flashmsg) . '</div>';
}

// Get all candidates (global pool) - ensure uniqueness at database level
$candidates = $DB->get_records_sql("
    SELECT DISTINCT c.*
    FROM {oralint_candidate} c
    ORDER BY c.fullname ASC
");

// Get existing sessions for this oral interview activity to show assigned candidates
$sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->instance], 'interviewdate DESC');

// Filter sessions for committee members - only show sessions they're assigned to
if ($is_committee_member && !$can_manage) {
    $user_sessions = $DB->get_records_sql("
        SELECT s.*
        FROM {oralint_session} s
        JOIN {oralint_committee} oc ON oc.sessionid = s.id
        WHERE s.oralinterviewid = :oralinterviewid
        AND oc.userid = :userid
        ORDER BY s.interviewdate DESC
    ", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);
    $sessions = $user_sessions;
}
// Sessions for this activity: each row needs candidate + session (one row per session).
$sessions_with_candidates = [];
foreach ($sessions as $session) {
    if (!$session->candidateid) {
        continue;
    }
    $candidate = isset($candidates[$session->candidateid]) ? $candidates[$session->candidateid] : $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
    if ($candidate) {
        $sessions_with_candidates[] = (object)['session' => $session, 'candidate' => $candidate];
    }
}

// Get templates for this specific oral interview activity
$templates = $DB->get_records_menu('oralint_template', ['oralinterviewid' => $cm->id], 'name ASC', 'id, name');
$errors = [];
$previewrows = [];
$previewsummary = null;
$previewtoken = '';
$importsummary = null;

function oralinterview_mask_nationalid($value) {
    $value = trim((string)$value);
    $length = strlen($value);
    if ($length === 0) {
        return '';
    }
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

function oralinterview_parse_csv($filepath) {
    $rows = [];
    $summary = ['total' => 0, 'invalid' => 0];
    if (!is_readable($filepath)) {
        return ['rows' => [], 'summary' => $summary];
    }
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        return ['rows' => [], 'summary' => $summary];
    }
    $header = fgetcsv($handle);
    $hasheader = false;
    if ($header !== false) {
        $lower = array_map('trim', array_map('strtolower', $header));
        if (in_array('fullname', $lower) && in_array('nationalid', $lower)) {
            $hasheader = true;
        } else {
            rewind($handle);
        }
    }
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 0) {
            continue;
        }
        $summary['total']++;
        $row = array_pad($row, 4, '');
        $fullname = trim($row[0]);
        $email = trim($row[1]);
        $phone = trim($row[2]);
        $nationalid = trim($row[3]);
        if ($fullname === '' || $nationalid === '') {
            $summary['invalid']++;
            continue;
        }
        $rows[] = compact('fullname', 'email', 'phone', 'nationalid');
    }
    fclose($handle);
    return ['rows' => $rows, 'summary' => $summary];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    if (!empty($_POST['preview']) && !empty($_FILES['csvfile'])) {
        $file = $_FILES['csvfile'];
        if (!empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $result = oralinterview_parse_csv($file['tmp_name']);
            if (!empty($result['rows'])) {
                $previewrows = $result['rows'];
                $previewsummary = $result['summary'];
                $previewtoken = bin2hex(random_bytes(12));
                $SESSION->oralinterview_csvpreview = ($SESSION->oralinterview_csvpreview ?? []);
                $SESSION->oralinterview_csvpreview[$previewtoken] = $previewrows;
                if (count($SESSION->oralinterview_csvpreview) > 5) {
                    array_shift($SESSION->oralinterview_csvpreview);
                }
            } else {
                $errors[] = get_string('csv_error_empty', 'oralinterview');
            }
        } else {
            $errors[] = get_string('csv_error_upload', 'oralinterview');
        }
    } elseif (!empty($_POST['confirmimport']) && !empty($_POST['token'])) {
        $token = preg_replace('/[^a-f0-9]/', '', $_POST['token']);
        $store = $SESSION->oralinterview_csvpreview ?? [];
        if (!empty($store[$token])) {
            $rows = $store[$token];
            unset($SESSION->oralinterview_csvpreview[$token]);
            $imported = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                if ($DB->record_exists('oralint_candidate', ['nationalid' => $row['nationalid']])) {
                    $skipped++;
                    continue;
                }
                candidate_persistent::create((object)[
                    'fullname' => $row['fullname'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'nationalid' => $row['nationalid'],
                    'status' => 'active',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id
                ]);
                $imported++;
            }
            $importsummary = ['imported' => $imported, 'skipped' => $skipped];
            $candidates = $DB->get_records('oralint_candidate', null, 'fullname ASC');
        }
    }
}

$formurl = new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]);

// Header already printed for SPAC UI.
if (!$spacui) {
    echo $OUTPUT->header();
}
if ($importsummary) {
    echo $OUTPUT->notification(get_string('import_summary', 'oralinterview', $importsummary), 'notifysuccess');
}
foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'notifymessage');
}

// Actions: Sync from exam (auto-enroll is default; keep sync for emergencies). Add Candidate + Import CSV commented out.
$actionshtml = '';
if ($can_manage || $can_manage_candidates) {
    // إضافة مرشح - معطّل لأن التسجيل التلقائي يعمل. استعد للتشغيل عند الحاجة.
    // $actionshtml = html_writer::link(
    //     new moodle_url('/mod/oralinterview/add_candidate.php', ['id' => $cm->id, 'spacui' => $spacui]),
    //     get_string('add_candidate', 'oralinterview'),
    //     ['class' => 'btn btn-primary', 'style' => 'margin-right: 20px;']
    // );
    $oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], 'jobid', IGNORE_MISSING);
    if ($oralinterview && (int) ($oralinterview->jobid ?? 0) > 0) {
        $syncbtnlabel = get_string('sync_candidates_from_exam', 'oralinterview');
        if (strpos($syncbtnlabel, '[[') !== false || $syncbtnlabel === 'sync_candidates_from_exam') {
            $syncbtnlabel = 'مزامنة من الاختبار';
        }
        $syncformurl = new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]);
        $actionshtml .= html_writer::start_tag('form', [
            'id' => 'oralinterview-sync-form',
            'action' => $syncformurl->out(false),
            'method' => 'post',
            'style' => 'display: inline; margin-right: 20px;'
        ]);
        $actionshtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $actionshtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'sync_from_exam']);
        $actionshtml .= html_writer::tag('button', $syncbtnlabel, ['type' => 'submit', 'class' => 'btn btn-outline-primary', 'title' => 'للطوارئ عند فشل التسجيل التلقائي']);
        $actionshtml .= html_writer::end_tag('form');
        // مزامنة من الاختبار - للطوارئ عند فشل التسجيل التلقائي (Sync from exam - for emergencies if auto-enroll fails)
        $syncurl = new moodle_url('/mod/oralinterview/candidates.php', [
            'id' => $cm->id, 'action' => 'sync_from_exam', 'sesskey' => sesskey(), 'spacui' => $spacui
        ]);
        $actionshtml .= ' <a href="' . $syncurl->out(false) . '" class="btn btn-outline-secondary" onclick="return confirm(\'مزامنة المرشحين من الاختبار؟\');" title="للطوارئ عند فشل التسجيل التلقائي">' . s($syncbtnlabel) . ' (رابط)</a>';
    }
}

// استيراد CSV - معطّل لأن التسجيل التلقائي يعمل. استعد للتشغيل عند الحاجة. (Import CSV - disabled, auto-enroll used)
// $importhtml = html_writer::start_tag('form', array('action' => $formurl, 'method' => 'post', 'enctype' => 'multipart/form-data', 'style' => 'display: inline-flex; align-items: center; margin-left: 10px;'));
// $importhtml .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'preview', 'value' => 1));
// $importhtml .= html_writer::empty_tag('input', array('type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required', 'class' => 'form-control form-control-sm', 'style' => 'width: auto; margin-right: 10px;'));
// $importhtml .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('import_csv', 'oralinterview'), 'class' => 'btn btn-secondary btn-sm'));
// $importhtml .= html_writer::end_tag('form');

echo html_writer::div('<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">' . $actionshtml . '</div>', 'oralint-actions mb-4');

// Single unified candidates table - only show assigned candidates
$candidatestable = new html_table();
$candidatestable->head = [
    get_string('candidate_fullname', 'oralinterview'),
    get_string('candidate_email', 'oralinterview'),
    get_string('interview_date', 'oralinterview'),
    get_string('status', 'oralinterview'),
    get_string('actions', 'oralinterview')
];
$candidatestable->attributes['class'] = 'table table-striped';

foreach ($sessions_with_candidates as $row) {
    $session = $row->session;
    $candidate = $row->candidate;
    $actions = '';
        
        // Edit button - only for managers
        if ($can_manage) {
            $actions .= html_writer::link(
                new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id, 'sessionid' => $session->id, 'spacui' => $spacui]),
                get_string('edit_session', 'oralinterview'),
                ['class' => 'btn btn-sm btn-outline-primary me-2']
            );
        }

        // Remove candidate from this interview (managers/candidate managers).
        if ($can_manage || $can_manage_candidates) {
            $actions .= html_writer::link(
                new moodle_url('/mod/oralinterview/candidates.php', [
                    'id' => $cm->id,
                    'action' => 'remove',
                    'candidateid' => $candidate->id,
                    'sesskey' => sesskey(),
                    'spacui' => $spacui
                ]),
                get_string('candidate_remove', 'oralinterview'),
                [
                    'class' => 'btn btn-sm btn-outline-danger me-2',
                    'onclick' => "return confirm('" . addslashes(get_string('candidate_remove_confirm', 'oralinterview')) . "');"
                ]
            );
        }
        
        // Evaluate button - show if user is committee member for this session OR has manage capability
        $is_session_committee = $DB->record_exists('oralint_committee', [
            'sessionid' => $session->id,
            'userid' => $USER->id
        ]);
        if ($is_session_committee || $can_manage) {
            $actions .= html_writer::link(
                new moodle_url('/mod/oralinterview/evaluate.php', ['id' => $cm->id, 'sessionid' => $session->id, 'spacui' => $spacui]),
                get_string('evaluate', 'oralinterview'),
                ['class' => 'btn btn-sm btn-success']
            );
        }
        
    $candidatestable->data[] = [
        format_string($candidate->fullname),
        format_string($candidate->email),
        $session->interviewdate ? userdate($session->interviewdate, '%Y-%m-%d %H:%M') : '-',
        $session->status,
        $actions
    ];
}

if (empty($candidatestable->data)) {
    echo html_writer::div(get_string('nocandidates', 'oralinterview'), 'alert alert-info');
} else {
    echo html_writer::table($candidatestable);
}

if (!empty($previewrows)) {
    $previewtable = new html_table();
    $previewtable->head = [
        get_string('candidate_fullname', 'oralinterview'),
        get_string('candidate_email', 'oralinterview'),
        get_string('candidate_phone', 'oralinterview'),
        get_string('candidate_nationalid', 'oralinterview')
    ];
    foreach ($previewrows as $row) {
        $previewtable->data[] = [
            $row['fullname'],
            $row['email'],
            $row['phone'],
            oralinterview_mask_nationalid($row['nationalid'])
        ];
    }
    echo html_writer::div(get_string('import_preview_headline', 'oralinterview'), 'h5');
    echo html_writer::div(get_string('import_preview_hint', 'oralinterview'), 'text-muted mb-2');
    if ($previewsummary) {
        echo html_writer::div(get_string('import_preview_summary', 'oralinterview', $previewsummary), 'text-muted mb-2');
    }
    echo html_writer::table($previewtable);
    echo html_writer::start_tag('form', ['action' => $formurl, 'method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmimport', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $previewtoken]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('import_confirm', 'oralinterview'), 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
