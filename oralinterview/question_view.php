<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);      // Course module id.
$qid = required_param('qid', PARAM_INT);    // Question id.
$jobid = optional_param('jobid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_URL);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/oralinterview:manage', $context);

$question = $DB->get_record('question', ['id' => $qid], '*', MUST_EXIST);

// Fetch competency names for this question (new logic: exam_interview_competencies + question_type_mapping + question_categories).
$competencynames = [];
if ($jobid > 0) {
    require_once(__DIR__ . '/lib_template_questions.php');
    if (oralinterview_table_exists('question_type_mapping') && oralinterview_table_exists('exam_interview_competencies')) {
        $indicator_ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT qtm.competency_category_id FROM {question_type_mapping} qtm
              WHERE qtm.question_id = :qid AND qtm.question_type IN ('interview', 'both')",
            ['qid' => $qid]
        );
        if (!empty($indicator_ids)) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_map('intval', $indicator_ids), SQL_PARAMS_NAMED);
            $params = ['jobid' => $jobid] + $inparams;
            $compids = $DB->get_fieldset_sql(
                "SELECT DISTINCT eic.competencyid FROM {exam_interview_competencies} eic
                  WHERE eic.jobid = :jobid AND eic.indicatorid $insql AND eic.selected_for = 'interview'",
                $params
            );
            if (!empty($compids)) {
                foreach (array_unique($compids) as $cid) {
                    $n = $DB->get_field('question_categories', 'name', ['id' => (int)$cid], IGNORE_MISSING);
                    if ($n !== false && $n !== '') {
                        $competencynames[] = $n;
                    }
                }
            }
        }
    }
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/question_view.php', ['id' => $cm->id, 'qid' => $qid, 'jobid' => $jobid]));
$PAGE->set_title(get_string('viewquestion', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

if ($spacui) {
    oralinterview_spacui_header($cm, 'templates', get_string('viewquestion', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('viewquestion', 'oralinterview'));
}

if (!empty($returnurl)) {
    echo html_writer::div(
        html_writer::link(new moodle_url($returnurl), get_string('back')),
        'mb-3'
    );
}

$meta = [];
if ($jobid) {
    $meta[] = html_writer::tag('strong', get_string('competency', 'oralinterview') . ': ') .
        format_string(!empty($competencynames) ? implode(', ', $competencynames) : get_string('notapplicable', 'oralinterview'));
}
if (!empty($question->qtype)) {
    $meta[] = html_writer::tag('strong', 'Type: ') . s($question->qtype);
}
if (isset($question->defaultmark)) {
    $meta[] = html_writer::tag('strong', 'Default mark: ') . s($question->defaultmark);
}

echo html_writer::div(
    html_writer::tag('h4', 'Question #' . (int)$question->id . ' - ' . format_string($question->name), ['class' => 'mb-2']) .
    (!empty($meta) ? html_writer::div(implode(' | ', $meta), 'text-muted mb-3') : ''),
    'card card-body mb-3'
);

echo html_writer::div(
    format_text($question->questiontext ?? '', $question->questiontextformat ?? FORMAT_HTML, ['context' => $context]),
    'card card-body'
);

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}

