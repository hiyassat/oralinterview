<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');

$id = optional_param('id', 0, PARAM_INT);
if (!$id) {
    throw new moodle_exception('invalidcoursemodule');
}

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$can_manage = has_capability('mod/oralinterview:manage', $context);
// Committee member = assigned to at least one session in this activity.
$is_committee_member = $DB->record_exists_sql("
    SELECT 1
      FROM {oralint_committee} oc
      JOIN {oralint_session} s ON s.id = oc.sessionid
     WHERE s.oralinterviewid = :oralinterviewid
       AND oc.userid = :userid
", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);

// Committee members shouldn't manage templates/candidates. Send them straight to session list/evaluation.
if (!$can_manage && $is_committee_member) {
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/oralinterview/view.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('modulename', 'oralinterview'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulename', 'oralinterview'));

// Add navigation tabs
$tabs = [];
$tabrow = [];

// Main view tab
$tabrow[] = new tabobject('view', 
    new moodle_url('/mod/oralinterview/view.php', ['id' => $cm->id]),
    get_string('modulename', 'oralinterview')
);

// Manage tab (for managers) - temporarily enabled for demo
if ($can_manage) {
    $tabrow[] = new tabobject('manage', 
        new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id]),
        get_string('manage', 'oralinterview')
    );
}

// Templates tab - temporarily enabled for demo
if ($can_manage) {
    $tabrow[] = new tabobject('templates', 
        new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id]),
        get_string('templates', 'oralinterview')
    );
}

// Candidates tab - temporarily enabled for demo
if (has_capability('mod/oralinterview:managecandidates', $context)) {
    $tabrow[] = new tabobject('candidates', 
        new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id]),
        get_string('candidates', 'oralinterview')
    );
}

// My interviews tab removed (redundant with manage.php for committee members).

echo $OUTPUT->tabtree($tabrow, 'view');

// Main content
echo html_writer::div(
    get_string('manage_hint', 'oralinterview'),
    'generalbox boxaligncenter boxwidthwide'
);

echo $OUTPUT->footer();
