<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class mod_oralinterview_candidate_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $enrolled_users = $this->_customdata['enrolled_users'] ?? [];
        
        // Add hidden fields first
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);
        
        // User selection dropdown - this is all we need
        $mform->addElement('select', 'selected_userid', get_string('select_enrolled_user', 'oralinterview'), $enrolled_users);
        $mform->setType('selected_userid', PARAM_INT);
        $mform->setDefault('selected_userid', 0);
        $mform->addRule('selected_userid', null, 'required', null, 'client');
        
        $mform->addElement('hidden', 'candidateid');
        $mform->setType('candidateid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
