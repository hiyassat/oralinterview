<?php
namespace mod_oralinterview\local\persistent;

use \core\persistent;

defined('MOODLE_INTERNAL') || die();

class session extends \core\persistent {
    protected static function define_table_name() {
        return 'oralint_session';
    }

    protected static function define_properties() {
        return [
            'courseid' => ['type' => PARAM_INT],
            'oralinterviewid' => ['type' => PARAM_INT],
            'candidateid' => ['type' => PARAM_INT],
            'templateid' => ['type' => PARAM_INT],
            'interviewdate' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'deadline' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'requireall' => ['type' => PARAM_INT, 'default' => 1],
            'status' => ['type' => PARAM_TEXT, 'default' => 'draft'],
            'finalscore' => ['type' => PARAM_RAW, 'null' => NULL_ALLOWED],
            'finalpercent' => ['type' => PARAM_RAW, 'null' => NULL_ALLOWED],
            'committeecount' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'questioncount' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'formula_version' => ['type' => PARAM_TEXT, 'default' => 'v1'],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'usermodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
