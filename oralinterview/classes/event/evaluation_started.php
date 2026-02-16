<?php
namespace mod_oralinterview\event;

defined('MOODLE_INTERNAL') || die();

class evaluation_started extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'oralint_eval';
    }

    public static function get_name() {
        return get_string('event_evaluation_started', 'oralinterview');
    }

    public function get_description() {
        return "Evaluation {$this->objectid} started by user {$this->userid} for session {$this->other['sessionid']}";
    }

    public function get_url() {
        return new \moodle_url('/mod/oralinterview/evaluate.php', ['id' => $this->contextinstanceid, 'evalid' => $this->objectid]);
    }
}
