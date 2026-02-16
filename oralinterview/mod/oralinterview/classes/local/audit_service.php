<?php
namespace mod_oralinterview\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class audit_service {
    public static function log(
        string $action,
        string $entity,
        int $sessionid,
        int $userid,
        ?string $oldvalue = null,
        ?string $newvalue = null,
        ?string $reason = null,
        string $crud = 'u'
    ) {
        global $DB;

        $record = new stdClass();
        $record->sessionid = $sessionid;
        $record->userid = $userid;
        $record->action = $action;
        $record->entity = $entity;
        $record->oldvalue = $oldvalue;
        $record->newvalue = $newvalue;
        $record->reason = $reason;
        $record->crud = $crud;
        $record->ip = getremoteaddr();
        $record->timecreated = time();
        $DB->insert_record('oralint_audit', $record);
    }
}
