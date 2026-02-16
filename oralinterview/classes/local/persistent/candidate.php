<?php
namespace mod_oralinterview\local\persistent;

use \core\persistent;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class candidate extends \core\persistent {
    protected static function define_table_name() {
        return 'oralint_candidate';
    }

    protected static function define_properties() {
        return [
            'fullname' => ['type' => PARAM_TEXT],
            'email' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'phone' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'nationalid' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'linkeduserid' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'status' => ['type' => PARAM_TEXT, 'default' => 'active'],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'usermodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
