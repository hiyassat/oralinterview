<?php
namespace mod_oralinterview\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

class candidate_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'fullname', get_string('candidate_fullname', 'oralinterview'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('candidate_email', 'oralinterview'));
        $mform->setType('email', PARAM_TEXT);

        $mform->addElement('text', 'phone', get_string('candidate_phone', 'oralinterview'));
        $mform->setType('phone', PARAM_TEXT);

        $mform->addElement('text', 'nationalid', get_string('candidate_nationalid', 'oralinterview'));
        $mform->setType('nationalid', PARAM_TEXT);

        $mform->addElement('text', 'linkeduserid', get_string('candidate_linkeduser', 'oralinterview'));
        $mform->setType('linkeduserid', PARAM_INT);
        $mform->addElement('hidden', 'candidateid');
        $mform->setType('candidateid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
