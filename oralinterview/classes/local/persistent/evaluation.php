<?php
namespace mod_oralinterview\local\persistent;

use \core\persistent;

defined('MOODLE_INTERNAL') || die();

class evaluation extends \core\persistent {
    protected static function define_table_name() {
        return 'oralint_eval';
    }

    protected static function define_properties() {
        return [
            'sessionid' => ['type' => PARAM_INT],
            'userid' => ['type' => PARAM_INT],
            'status' => ['type' => PARAM_TEXT, 'default' => 'notstarted'],
            'finalscore' => ['type' => PARAM_RAW, 'null' => NULL_ALLOWED],
            'submitreason' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'usermodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
