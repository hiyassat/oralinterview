<?php
namespace mod_oralinterview\event;

defined('MOODLE_INTERNAL') || die();

class session_completed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'oralint_session';
    }

    public static function get_name() {
        return get_string('event_session_completed', 'oralinterview');
    }

    public function get_description() {
        return "Session {$this->objectid} completed with final score {$this->other['finalscore']}";
    }
}
