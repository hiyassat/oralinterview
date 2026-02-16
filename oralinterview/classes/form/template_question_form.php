<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class mod_oralinterview_template_question_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        
        // Add hidden fields first
        $templateid = $this->_customdata['templateid'] ?? 0;
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);
        
        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);
        $mform->setDefault('templateid', $templateid);
        
        $mform->addElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);
        
        $mform->addElement('textarea', 'questiontext', get_string('question_text', 'oralinterview'), 'wrap="virtual" rows="5"');
        $mform->setType('questiontext', PARAM_RAW);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        $mform->addElement('textarea', 'rubric', get_string('question_rubric', 'oralinterview'));
        $mform->setType('rubric', PARAM_RAW);

        $mform->addElement('text', 'maxscore', get_string('question_maxscore', 'oralinterview'));
        $mform->setType('maxscore', PARAM_INT);
        $mform->setDefault('maxscore', 10);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);
        $mform->setDefault('action', 'add');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
