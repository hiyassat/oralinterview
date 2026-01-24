<?php
namespace mod_oralinterview\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

class template_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('template_name', 'oralinterview'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'jobtitle', get_string('template_jobtitle', 'oralinterview'));
        $mform->setType('jobtitle', PARAM_TEXT);

        $mform->addElement('textarea', 'description', get_string('template_description', 'oralinterview'), 'wrap="virtual" rows="5"');
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
