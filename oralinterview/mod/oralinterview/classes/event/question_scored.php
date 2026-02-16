<?php
namespace mod_oralinterview\event;

defined('MOODLE_INTERNAL') || die();

class question_scored extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'oralint_score';
    }

    public static function get_name() {
        return get_string('event_question_scored', 'oralinterview');
    }

    public function get_description() {
        return "Question {$this->other['questionid']} scored for evaluation {$this->other['evalid']} by user {$this->userid}";
    }
}
