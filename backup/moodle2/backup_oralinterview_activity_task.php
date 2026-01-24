<?php
require_once($CFG->dirroot . '/backup/moodle2/backup_activity_task.class.php');
require_once($CFG->dirroot . '/mod/oralinterview/backup/moodle2/backup_oralinterview_structure_step.php');

defined('MOODLE_INTERNAL') || die();

class backup_oralinterview_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new backup_oralinterview_structure_step('oralinterview_structure', 'oralinterview.xml'));
    }
}
