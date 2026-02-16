<?php
namespace mod_oralinterview\task;

use core\task\scheduled_task;

use mod_oralinterview\local\audit_service;

defined('MOODLE_INTERNAL') || die();

class retention extends scheduled_task {
    public function get_name() {
        return get_string('task_retention', 'oralinterview');
    }

    public function execute() {
        global $DB;
        $days = (int)get_config('oralinterview', 'retentiondays');
        if ($days <= 0) {
            $days = 365;
        }
        $cutoff = time() - ($days * DAYSECS);
        $candidates = $DB->get_records_select('oralint_candidate', 'timecreated <= ? AND status <> ?', [$cutoff, 'anonymized']);
        foreach ($candidates as $candidate) {
            $oldvalue = json_encode(['nationalid' => $candidate->nationalid]);
            $candidate->nationalid = null;
            $candidate->email = null;
            $candidate->phone = null;
            $candidate->status = 'anonymized';
            $candidate->timemodified = time();
            $DB->update_record('oralint_candidate', $candidate);
            audit_service::log('candidate_anonymized', 'candidate', 0, 0, $oldvalue, null, 'retention', 'u');
        }
    }
}
