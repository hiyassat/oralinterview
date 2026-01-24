<?php
namespace mod_oralinterview\task;

use core\task\scheduled_task;

use mod_oralinterview\local\notification;

defined('MOODLE_INTERNAL') || die();

class reminder extends scheduled_task {
    public function get_name() {
        return get_string('task_reminder', 'oralinterview');
    }

    public function execute() {
        global $DB;
        $days = (int)get_config('oralinterview', 'messagingreminder');
        if ($days <= 0) {
            $days = 2;
        }
        $now = time();
        $threshold = $now + ($days * DAYSECS);
        $sessions = $DB->get_records_select('oralint_session', 'deadline BETWEEN ? AND ? AND status = ?', [$now, $threshold, 'active']);
        foreach ($sessions as $session) {
            $committee = $DB->get_records('oralint_committee', ['sessionid' => $session->id]);
            foreach ($committee as $member) {
                notification::send_deadline_reminder($session->id, $member->userid);
            }
        }
    }
}
