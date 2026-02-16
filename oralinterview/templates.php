<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

// Templates are not exposed in the SPAC UI. Redirect to the interview questions page.
$spacui = optional_param('spacui', 0, PARAM_BOOL);
$id = required_param('id', PARAM_INT);
redirect(new moodle_url('/mod/oralinterview/questions.php', ['id' => $id, 'spacui' => $spacui]));

defined('MOODLE_INTERNAL') || die();

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$can_manage = has_capability('mod/oralinterview:manage', $context);
if (!$can_manage) {
    throw new moodle_exception('nopermission', 'error');
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/templates.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->set_title(get_string('templates', 'oralinterview'));
$PAGE->set_heading($course->fullname);

$templates = $DB->get_records('oralint_template', ['oralinterviewid' => $cm->id]);
$show_add_button = $can_manage;

if ($spacui) {
    oralinterview_spacui_header($cm, 'templates', get_string('templates', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('templates', 'oralinterview'));
}

// Header actions.
if ($show_add_button) {
    echo html_writer::start_div('oralint-header mb-3 d-flex gap-2 flex-wrap');
    echo html_writer::link(
        new moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cm->id, 'spacui' => $spacui]),
        get_string('addtemplate', 'oralinterview'),
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
}

if ($templates) {
    echo html_writer::start_div('oralint-templates-list');

    foreach ($templates as $template) {
        $status = $template->status ?? 'draft';
        $badgeclass = ($status === 'published') ? 'bg-success' : 'bg-secondary';

        echo html_writer::start_div('card mb-3 shadow-sm border-0');
        echo html_writer::start_div('card-body');

        echo html_writer::start_div('d-flex justify-content-between align-items-start flex-wrap gap-2');

        echo html_writer::start_div('flex-grow-1');
        echo html_writer::tag('h5', format_string($template->name), ['class' => 'card-title mb-1']);
        echo html_writer::tag('div', format_string($template->jobtitle), ['class' => 'text-muted mb-2']);
        echo html_writer::tag('span', s($status), ['class' => 'badge ' . $badgeclass]);
        echo html_writer::end_div();

        echo html_writer::start_div('d-flex gap-2');
        echo html_writer::link(
            new moodle_url('/mod/oralinterview/template_questions_advanced.php', ['id' => $cm->id, 'templateid' => $template->id, 'spacui' => $spacui]),
            get_string('questions', 'oralinterview'),
            ['class' => 'btn btn-sm btn-outline-primary']
        );
        if ($show_add_button) {
            echo html_writer::link(
                new moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cm->id, 'templateid' => $template->id, 'spacui' => $spacui]),
                get_string('edit'),
                ['class' => 'btn btn-sm btn-outline-secondary']
            );
        }
        echo html_writer::end_div();

        echo html_writer::end_div(); // flex
        echo html_writer::end_div(); // body
        echo html_writer::end_div(); // card
    }

    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('notemplates', 'oralinterview'), 'alert alert-info');
}

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}

