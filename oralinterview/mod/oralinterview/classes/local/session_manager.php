<?php
namespace mod_oralinterview\local;

use stdClass;
use mod_oralinterview\\local\\notification;

defined('MOODLE_INTERNAL') || die();

class session_manager {
    public static function assign_committee(int $sessionid, array $userids) {
        global $DB;
        $DB->delete_records('oralint_committee', ['sessionid' => $sessionid]);
        $DB->delete_records('oralint_eval', ['sessionid' => $sessionid]);
        $inserted = 0;
        $time = time();
        foreach ($userids as $userid) {
            $record = new stdClass();
            $record->sessionid = $sessionid;
            $record->userid = (int)$userid;
            $record->role = 'member';
            $record->status = 'assigned';
            $record->timecreated = $time;
            $record->timemodified = $time;
            $DB->insert_record('oralint_committee', $record);

            $eval = new stdClass();
            $eval->sessionid = $sessionid;
            $eval->userid = (int)$userid;
            $eval->status = 'notstarted';
            $eval->timecreated = $time;
            $eval->timemodified = $time;
            $DB->insert_record('oralint_eval', $eval);
            $inserted++;
            notification::send_assignment($sessionid, (int)$userid);
        }
        $DB->set_field('oralint_session', 'committeecount', $inserted, ['id' => $sessionid]);
    }

    public static function ensure_session_questions(int $sessionid) {
        global $DB;
        $existing = $DB->count_records('oralint_session_q', ['sessionid' => $sessionid]);
        if ($existing > 0) {
            return;
        }
        $session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);
        $questions = $DB->get_records('oralint_template_q', ['templateid' => $session->templateid], 'sortorder ASC, id ASC');
        $time = time();
        $added = 0;
        foreach ($questions as $question) {
            $record = new stdClass();
            $record->sessionid = $sessionid;
            $record->questiontext = $question->questiontext;
            $record->rubric = $question->rubric;
            $record->maxscore = $question->maxscore;
            $record->sortorder = $question->sortorder ?: ($added + 1);
            $record->timecreated = $time;
            $record->timemodified = $time;
            $DB->insert_record('oralint_session_q', $record);
            $added++;
        }
        $DB->set_field('oralint_session', 'questioncount', $added, ['id' => $sessionid]);
    }

    public static function get_committee_userids(int $sessionid): array {
        global $DB;
        $records = $DB->get_records('oralint_committee', ['sessionid' => $sessionid], '', 'userid');
        return array_map('intval', array_column($records, 'userid'));
    }
}
