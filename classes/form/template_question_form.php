<?php
namespace mod_oralinterview\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

class template_question_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('textarea', 'questiontext', get_string('question_text', 'oralinterview'), 'wrap="virtual" rows="5"');
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        $mform->addElement('textarea', 'rubric', get_string('question_rubric', 'oralinterview'));
        $mform->setType('rubric', PARAM_TEXT);

        $mform->addElement('text', 'maxscore', get_string('question_maxscore', 'oralinterview'));
        $mform->setType('maxscore', PARAM_INT);
        $mform->setDefault('maxscore', 10);

        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);
        $mform->addElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
