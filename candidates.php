<?php
require_once('../../config.php');

use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\persistent\candidate as candidate_persistent;

global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SESSION;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

$PAGE->set_url(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]));
$PAGE->set_title(get_string('candidates', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('candidates', 'oralinterview'));

$candidates = $DB->get_records('oralint_candidate', null, 'fullname ASC');
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

$formurl = new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]);

echo $OUTPUT->header();
if ($importsummary) {
    echo $OUTPUT->notification(get_string('import_summary', 'oralinterview', $importsummary), 'notifysuccess');
}
foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'notifymessage');
}

$actionshtml = html_writer::link(new moodle_url('/mod/oralinterview/candidate_edit.php', ['id' => $cm->id]),
    get_string('candidate_add', 'oralinterview'), ['class' => 'btn btn-primary']);
$importhtml = html_writer::start_tag('form', ['action' => $formurl, 'method' => 'post', 'enctype' => 'multipart/form-data', 'class' => 'oralint-csv-form']);
$importhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'preview', 'value' => 1]);
$importhtml .= html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
$importhtml .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('import_csv', 'oralinterview')]);
$importhtml .= html_writer::end_tag('form');

echo html_writer::div($actionshtml . $importhtml, 'oralint-actions mb-3');

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

$coursetable = new html_table();
$coursetable->head = [
    get_string('candidate_fullname', 'oralinterview'),
    get_string('candidate_email', 'oralinterview'),
    get_string('candidate_phone', 'oralinterview'),
    get_string('candidate_nationalid', 'oralinterview'),
    get_string('candidate_actions', 'oralinterview')
];
foreach ($candidates as $candidate) {
    $actions = html_writer::link(new moodle_url('/mod/oralinterview/candidate_edit.php', ['id' => $cm->id, 'candidateid' => $candidate->id]),
        get_string('candidate_edit', 'oralinterview'));
    $coursetable->data[] = [
        format_string($candidate->fullname),
        format_string($candidate->email),
        format_string($candidate->phone),
        oralinterview_mask_nationalid($candidate->nationalid),
        $actions
    ];
}
if (empty($coursetable->data)) {
    echo html_writer::div(get_string('nocandidates', 'oralinterview'), 'alert alert-info');
} else {
    echo html_writer::table($coursetable);
}

echo $OUTPUT->footer();
