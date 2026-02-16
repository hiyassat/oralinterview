<?php
namespace mod_oralinterview\local\persistent;

use core_persistent;

defined('MOODLE_INTERNAL') || die();

class score extends core_persistent {
    protected static function define_table_name() {
        return 'oralint_score';
    }

    protected static function define_properties() {
        return [
            'evaluationid' => ['type' => PARAM_INT],
            'sessionqid' => ['type' => PARAM_INT],
            'score' => ['type' => PARAM_FLOAT],
            'comment' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'usermodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
