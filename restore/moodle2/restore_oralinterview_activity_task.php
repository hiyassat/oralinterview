<?php
require_once($CFG->dirroot . '/backup/moodle2/restore_activity_task.class.php');
require_once($CFG->dirroot . '/mod/oralinterview/restore/moodle2/restore_oralinterview_structure_step.php');

defined('MOODLE_INTERNAL') || die();

class restore_oralinterview_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_oralinterview_structure_step('oralinterview_structure', 'oralinterview.xml'));
    }
}
