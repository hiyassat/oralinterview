<?php
namespace mod_oralinterview\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

class session_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $templates = $this->_customdata['templates'] ?? [];
        $candidates = $this->_customdata['candidates'] ?? [];
        $users = $this->_customdata['users'] ?? [];

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('select', 'templateid', get_string('session_template', 'oralinterview'), $templates);
        $mform->addRule('templateid', null, 'required', null, 'client');

        $mform->addElement('select', 'candidateid', get_string('session_candidate', 'oralinterview'), $candidates);
        $mform->addRule('candidateid', null, 'required', null, 'client');

        $mform->addElement('select', 'committee', get_string('session_committee', 'oralinterview'), $users, ['multiple' => 'multiple', 'size' => 10]);
        $mform->setDefault('committee', []);

        $mform->addElement('date_time_selector', 'interviewdate', get_string('session_interviewdate', 'oralinterview'));
        $mform->setDefault('interviewdate', time());

        $mform->addElement('date_time_selector', 'deadline', get_string('session_deadline', 'oralinterview'), ['optional' => false]);
        $mform->setDefault('deadline', time());

        $mform->addElement('advcheckbox', 'requireall', get_string('session_requireall', 'oralinterview'));
        $mform->setDefault('requireall', 1);

        $statusoptions = [
            'draft' => get_string('session_status_draft', 'oralinterview'),
            'active' => get_string('session_status_active', 'oralinterview')
        ];
        $mform->addElement('select', 'status', get_string('session_status', 'oralinterview'), $statusoptions);
        $mform->setDefault('status', 'draft');

        $mform->addElement('static', 'session_instruction', '', get_string('session_instruction', 'oralinterview'));

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
