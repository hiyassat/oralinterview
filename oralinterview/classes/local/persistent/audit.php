<?php
namespace mod_oralinterview\local\persistent;

use \core\persistent;

defined('MOODLE_INTERNAL') || die();

class audit extends \core\persistent {
    protected static function define_table_name() {
        return 'oralint_audit';
    }

    protected static function define_properties() {
        return [
            'sessionid' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'userid' => ['type' => PARAM_INT],
            'action' => ['type' => PARAM_TEXT],
            'entity' => ['type' => PARAM_TEXT],
            'oldvalue' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'newvalue' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'reason' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'ip' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'crud' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
