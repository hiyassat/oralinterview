<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class mod_oralinterview_template_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        
        // Add hidden fields first
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);
        
        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);
        
        $mform->addElement('text', 'name', get_string('template_name', 'oralinterview'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'jobtitle', get_string('template_jobtitle', 'oralinterview'));
        $mform->setType('jobtitle', PARAM_TEXT);

        $mform->addElement('textarea', 'description', get_string('template_description', 'oralinterview'), 'wrap="virtual" rows="5"');
        $mform->setType('description', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
