<?php
/**
 * Library of interface functions and constants for module oralinterview.
 */

defined('MOODLE_INTERNAL') || die();

function mod_oralinterview_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_ANALYTICS:
            return false;
        default:
            return null;
    }
}

function mod_oralinterview_add_instance($oralinterview) {
    global $DB;
    $oralinterview->timemodified = time();
    $oralinterview->timecreated = time();
    return $DB->insert_record('oralinterview', $oralinterview);
}

function mod_oralinterview_update_instance($oralinterview) {
    global $DB;
    $oralinterview->timemodified = time();
    return $DB->update_record('oralinterview', $oralinterview);
}

function mod_oralinterview_delete_instance($id) {
    global $DB;
    return $DB->delete_records('oralinterview', ['id' => $id]);
}

function mod_oralinterview_extend_navigation(navigation_node $nav, navigation_node $node) {
    global $PAGE;
    if (empty($PAGE->cm)) {
        return;
    }

    $context = context_module::instance($PAGE->cm->id);

    if (has_capability('mod/oralinterview:manage', $context)) {
        $node->add(
            get_string('manage', 'oralinterview'),
            new moodle_url('/mod/oralinterview/manage.php', ['id' => $PAGE->cm->id])
        );
    }

    if (has_capability('mod/oralinterview:evaluate', $context)) {
        $node->add(
            get_string('myinterviews', 'oralinterview'),
            new moodle_url('/mod/oralinterview/my.php', ['id' => $PAGE->cm->id])
        );
    }
}
