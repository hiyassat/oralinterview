<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class mod_oralinterview_session_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $templates = $this->_customdata['templates'] ?? [];
        $candidates = $this->_customdata['candidates'] ?? [];
        $users = $this->_customdata['users'] ?? [];
        
        // Add hidden fields first
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('select', 'templateid', get_string('session_template', 'oralinterview'), $templates);
        $mform->addRule('templateid', null, 'required', null, 'client');

        $mform->addElement('select', 'candidateid', get_string('session_candidate', 'oralinterview'), $candidates);
        $mform->addRule('candidateid', null, 'required', null, 'client');

        // Committee members field - always show dropdown with available users
        if (!empty($users)) {
            $mform->addElement('select', 'committee', get_string('session_committee', 'oralinterview'), $users, ['multiple' => 'multiple', 'size' => 5]);
            $mform->setDefault('committee', []);
            $mform->addHelpButton('committee', 'session_committee', 'oralinterview');
        } else {
            // Fallback: show current user as default committee member
            global $USER;
            $current_user = [($USER->id ?? 1) => ($USER->firstname ?? 'Admin') . ' ' . ($USER->lastname ?? 'User')];
            $mform->addElement('select', 'committee', get_string('session_committee', 'oralinterview'), $current_user, ['multiple' => 'multiple', 'size' => 3]);
            $mform->setDefault('committee', [$USER->id ?? 1]);
            $mform->addHelpButton('committee', 'session_committee', 'oralinterview');
        }

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
