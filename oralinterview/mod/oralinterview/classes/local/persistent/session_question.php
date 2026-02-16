<?php
namespace mod_oralinterview\local\persistent;

use core_persistent;

defined('MOODLE_INTERNAL') || die();

class session_question extends core_persistent {
    protected static function define_table_name() {
        return 'oralint_session_q';
    }

    protected static function define_properties() {
        return [
            'sessionid' => ['type' => PARAM_INT],
            'questiontext' => ['type' => PARAM_TEXT],
            'rubric' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'maxscore' => ['type' => PARAM_INT, 'default' => 10],
            'sortorder' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
