<?php
namespace mod_oralinterview\local\persistent;

use core_persistent;

defined('MOODLE_INTERNAL') || die();

class committee_member extends core_persistent {
    protected static function define_table_name() {
        return 'oralint_committee';
    }

    protected static function define_properties() {
        return [
            'sessionid' => ['type' => PARAM_INT],
            'userid' => ['type' => PARAM_INT],
            'role' => ['type' => PARAM_TEXT, 'default' => 'member'],
            'status' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
