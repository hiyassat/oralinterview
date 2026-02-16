<?php
require_once($CFG->dirroot . '/backup/moodle2/restore_nested_element.class.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_structure_step.class.php');

defined('MOODLE_INTERNAL') || die();

class restore_oralinterview_structure_step extends restore_structure_step {
    protected function define_structure() {
        $root = new restore_nested_element('oralinterview', ['id'], ['name', 'intro', 'introformat']);
        return $this->prepare_activity_structure($root);
    }
}
