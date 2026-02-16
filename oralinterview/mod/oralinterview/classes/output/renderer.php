<?php
namespace mod_oralinterview\output;

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

class renderer extends plugin_renderer_base {
    public function render_templates_list(array $templates, int $cmid) {
        $rows = [];
        foreach ($templates as $template) {
            $rows[] = [
                'name' => format_string($template->name),
                'jobtitle' => format_string($template->jobtitle),
                'status' => $template->status,
                'questionslink' => $this->output->action_link(
                    new \moodle_url('/mod/oralinterview/template_questions.php', ['id' => $cmid, 'templateid' => $template->id]),
                    get_string('questions', 'oralinterview')
                )->out(false),
                'editlink' => $this->output->action_link(
                    new \moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cmid, 'templateid' => $template->id]),
                    get_string('edit')
                )->out(false),
            ];
        }
        return $this->render_from_template('mod/oralinterview/templates_list', [
            'templates' => $rows,
            'addtemplateurl' => (new \moodle_url('/mod/oralinterview/template_edit.php', ['id' => $cmid]))->out(false),
            'addtemplate' => get_string('addtemplate', 'oralinterview'),
            'heading' => get_string('templates', 'oralinterview'),
        ]);
    }

    public function render_template_questions($template, array $questions, int $cmid) {
        $rows = [];
        foreach ($questions as $question) {
            $rows[] = [
                'text' => format_text($question->questiontext, FORMAT_HTML),
                'rubric' => format_text($question->rubric, FORMAT_HTML),
                'maxscore' => $question->maxscore,
                'editlink' => (new \moodle_url('/mod/oralinterview/template_questions.php', [
                    'id' => $cmid,
                    'templateid' => $template->id,
                    'questionid' => $question->id
                ]))->out(false),
                'deletelink' => (new \moodle_url('/mod/oralinterview/template_questions.php', [
                    'id' => $cmid,
                    'templateid' => $template->id,
                    'action' => 'delete',
                    'questionid' => $question->id,
                    'sesskey' => sesskey()
                ]))->out(false),
            ];
        }
        return $this->render_from_template('mod/oralinterview/template_questions', [
            'template' => (object)[
                'name' => format_string($template->name),
                'jobtitle' => format_string($template->jobtitle)
            ],
            'questions' => $rows,
            'addquestion' => get_string('addquestion', 'oralinterview'),
            'heading' => get_string('templatequestions', 'oralinterview'),
        ]);
    }
}
