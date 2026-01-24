<?php
require_once('../../config.php');

use html_table;
use html_writer;
use moodle_url;
use mod_oralinterview\local\access as access_helper;
use mod_oralinterview\local\scoring as scoring_service;

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$targetsessionid = optional_param('sessionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
access_helper::require_manage($context);

if ($action === 'recalculate' && $targetsessionid) {
    require_sesskey();
    require_capability('mod/oralinterview:recalculate', $context);
    scoring_service::compute_final_score($targetsessionid, $USER->id, true);
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
$PAGE->set_title(get_string('session_manage', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));

$sessions = $DB->get_records('oralint_session', ['courseid' => $course->id], 'interviewdate DESC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('session_manage', 'oralinterview'));
echo $OUTPUT->box(get_string('session_manage_hint', 'oralinterview'));

echo $OUTPUT->single_button(new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id]), get_string('session_add', 'oralinterview'));

$table = new html_table();
$table->head = [
    get_string('session_template', 'oralinterview'),
    get_string('session_candidate', 'oralinterview'),
    get_string('session_status', 'oralinterview'),
    get_string('session_progress', 'oralinterview'),
    get_string('session_finalscore', 'oralinterview'),
    get_string('session_actions', 'oralinterview')
];

if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('session_no_sessions', 'oralinterview'), 'notifymessage');
} else {
    foreach ($sessions as $session) {
        $template = $DB->get_record('oralint_template', ['id' => $session->templateid], '*', IGNORE_MISSING);
        $candidate = $DB->get_record('oralint_candidate', ['id' => $session->candidateid], '*', IGNORE_MISSING);
        $submitted = $DB->count_records_select('oralint_eval', 'sessionid = ? AND status = ?', [$session->id, 'submitted']);
        $needed = max(1, $session->committeecount ?: 0);
        $progress = html_writer::tag('span', $submitted . ' / ' . $needed);
        $statuslabel = get_string('session_status_' . ($session->status ?? 'draft'), 'oralinterview');
        $final = '-';
        if ($session->finalscore !== null) {
            $final = format_float($session->finalscore, 2, true) . ' (' . format_float($session->finalpercent ?? 0, 2, true) . '%)';
        }
        $actions = [
            html_writer::link(new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id, 'sessionid' => $session->id]), get_string('edit')),
            html_writer::link(
                new moodle_url('/mod/oralinterview/manage.php', [
                    'id' => $cm->id,
                    'action' => 'recalculate',
                    'sessionid' => $session->id,
                    'sesskey' => sesskey()
                ]),
                get_string('session_recalculate', 'oralinterview')
            ),
            html_writer::link(new moodle_url('/mod/oralinterview/override.php', ['id' => $cm->id, 'sessionid' => $session->id]), get_string('session_override', 'oralinterview')),
            html_writer::link(new moodle_url('/mod/oralinterview/reopen.php', ['id' => $cm->id, 'sessionid' => $session->id]), get_string('session_reopen', 'oralinterview')),
            html_writer::link(new moodle_url('/mod/oralinterview/lock.php', ['id' => $cm->id, 'sessionid' => $session->id, 'mode' => $session->status === 'locked' ? 'unlock' : 'lock']),
                $session->status === 'locked' ? get_string('session_unlock', 'oralinterview') : get_string('session_lock', 'oralinterview')),
            html_writer::link(new moodle_url('/mod/oralinterview/session_report.php', ['id' => $cm->id, 'sessionid' => $session->id]), get_string('session_report', 'oralinterview'))
        ];
        $table->data[] = [
            $template ? format_string($template->name) : '-',
            $candidate ? format_string($candidate->fullname) : '-',
            $statuslabel,
            $progress,
            $final,
            implode(' | ', $actions)
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
