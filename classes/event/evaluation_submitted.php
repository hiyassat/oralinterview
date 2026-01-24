<?php
namespace mod_oralinterview\event;

defined('MOODLE_INTERNAL') || die();

class evaluation_submitted extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'oralint_eval';
    }

    public static function get_name() {
        return get_string('event_evaluation_submitted', 'oralinterview');
    }

    public function get_description() {
        return "Evaluation {$this->objectid} submitted for session {$this->other['sessionid']}";
    }
}
