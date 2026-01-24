<?php
namespace mod_oralinterview\local\persistent;

use core_persistent;

defined('MOODLE_INTERNAL') || die();

class template extends core_persistent {
    protected static function define_table_name() {
        return 'oralint_template';
    }

    protected static function define_properties() {
        return [
            'courseid' => ['type' => PARAM_INT],
            'name' => ['type' => PARAM_TEXT],
            'jobtitle' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'description' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'status' => ['type' => PARAM_TEXT, 'default' => 'draft'],
            'maxscore' => ['type' => PARAM_INT, 'default' => 10],
            'timecreated' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'timemodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
            'usermodified' => ['type' => PARAM_INT, 'null' => NULL_ALLOWED],
        ];
    }
}
