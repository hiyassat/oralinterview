<?php
namespace mod_oralinterview\event;

defined('MOODLE_INTERNAL') || die();

class score_overridden extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'oralint_score';
    }

    public static function get_name() {
        return get_string('event_score_overridden', 'oralinterview');
    }

    public function get_description() {
        return "Scores for evaluation {$this->other['evalid']} were overridden by user {$this->userid}";
    }
}
