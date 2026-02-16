<?php
require_once($CFG->dirroot . '/backup/moodle2/backup_nested_element.class.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_structure_step.class.php');

defined('MOODLE_INTERNAL') || die();

class backup_oralinterview_structure_step extends backup_structure_step {
    protected function define_structure() {
        $root = new backup_nested_element('oralinterview', ['id'], ['name', 'intro', 'introformat']);
        return $this->prepare_activity_structure($root);
    }
}
