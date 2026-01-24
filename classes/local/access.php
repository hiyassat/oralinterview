<?php
namespace mod_oralinterview\local;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

class access {
    public static function require_manage($context) {
        require_capability('mod/oralinterview:manage', $context);
    }

    public static function require_evaluate($context) {
        require_capability('mod/oralinterview:evaluate', $context);
    }

    public static function require_viewall($context) {
        require_capability('mod/oralinterview:viewall', $context);
    }

    public static function require_export($context) {
        require_capability('mod/oralinterview:export', $context);
    }

    public static function assert_evaluation_owner($evalid, $userid) {
        global $DB;
        $record = $DB->get_record('oralint_eval', ['id' => $evalid], 'userid', MUST_EXIST);
        if ($record->userid !== $userid) {
            throw new moodle_exception('nopermission', 'error');
        }
    }
}
