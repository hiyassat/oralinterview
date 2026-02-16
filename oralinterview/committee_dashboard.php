<?php
/**
 * Committee Member Dashboard
 * Shows all sessions where the user is assigned as committee member
 * Works even if user is not enrolled in the course
 */

require_once('../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

require_login();

$PAGE->set_url(new moodle_url('/mod/oralinterview/committee_dashboard.php'));
$PAGE->set_title('My Interview Assignments');
$PAGE->set_heading('My Interview Assignments');
$PAGE->set_context(context_system::instance());

// Get all sessions where user is a committee member
$sessions = $DB->get_records_sql("
    SELECT s.*, 
           t.name as templatename,
           c.fullname as candidatename,
           co.fullname as coursename,
           co.id as courseid,
           cm.id as cmid,
           e.id as evalid,
           e.status as evalstatus
    FROM {oralint_committee} oc
    JOIN {oralint_session} s ON s.id = oc.sessionid
    JOIN {oralint_template} t ON t.id = s.templateid
    LEFT JOIN {oralint_candidate} c ON c.id = s.candidateid
    JOIN {course_modules} cm ON cm.instance = s.oralinterviewid AND cm.module = (SELECT id FROM {modules} WHERE name = 'oralinterview')
    JOIN {course} co ON co.id = s.courseid
    LEFT JOIN {oralint_eval} e ON e.sessionid = s.id AND e.userid = :userid
    WHERE oc.userid = :userid2
    ORDER BY s.interviewdate DESC
", ['userid' => $USER->id, 'userid2' => $USER->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading('My Interview Assignments');

if (empty($sessions)) {
    echo $OUTPUT->notification('You are not assigned as a committee member for any interview sessions.', 'notifymessage');
} else {
    $table = new html_table();
    $table->head = [
        'Course',
        'Template',
        'Candidate',
        'Interview Date',
        'Deadline',
        'Session Status',
        'Evaluation Status',
        'Actions'
    ];
    $table->attributes['class'] = 'generaltable';
    
    foreach ($sessions as $session) {
        $interviewdate = $session->interviewdate ? userdate($session->interviewdate, '%Y-%m-%d %H:%M') : '-';
        $deadline = $session->deadline ? userdate($session->deadline, '%Y-%m-%d %H:%M') : '-';
        
        $evalstatus = $session->evalstatus ?? 'notstarted';
        $statuslabel = ucfirst($evalstatus);
        
        $actionurl = new moodle_url('/mod/oralinterview/evaluate.php', [
            'id' => $session->cmid,
            'sessionid' => $session->id,
            'evalid' => $session->evalid ?? 0
        ]);
        
        $actiontext = $evalstatus === 'submitted' ? 'View' : 'Evaluate';
        $actions = html_writer::link($actionurl, $actiontext, ['class' => 'btn btn-primary btn-sm']);
        
        $table->data[] = [
            format_string($session->coursename),
            format_string($session->templatename),
            format_string($session->candidatename ?? '-'),
            $interviewdate,
            $deadline,
            ucfirst($session->status),
            $statuslabel,
            $actions
        ];
    }
    
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
