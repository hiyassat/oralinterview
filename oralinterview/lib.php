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
            return false;
        default:
            return null;
    }
}

function mod_oralinterview_add_instance($oralinterview) {
    global $DB, $CFG;

    // Interview name is auto-generated from job + date (never manual).
    if (!empty($oralinterview->jobid)) {
        require_once($CFG->dirroot . '/mod/oralinterview/lib_template_questions.php');
        $oralinterview->name = oralinterview_template_auto_name((int) $oralinterview->jobid);
    }
    if (trim((string) ($oralinterview->name ?? '')) === '') {
        $oralinterview->name = get_string('modulename', 'oralinterview') . ' ' . date('Y-m-d');
    }

    $oralinterview->timemodified = time();
    $oralinterview->timecreated = time();
    $id = $DB->insert_record('oralinterview', $oralinterview);
    if (!$id) {
        return false;
    }

    // Auto-enroll candidates from exam (cm exists now; Moodle passes coursemodule id in $oralinterview).
    if (!empty($oralinterview->jobid) && !empty($oralinterview->course) && !empty($oralinterview->coursemodule)) {
        $cmid = (int) $oralinterview->coursemodule;
        $cm = get_coursemodule_from_id('oralinterview', $cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $cm->instance = $id; // Instance not yet set on cm in DB when we're called.
            $course = $DB->get_record('course', ['id' => $oralinterview->course], '*', IGNORE_MISSING);
            if ($course) {
                require_once($CFG->dirroot . '/mod/oralinterview/spacui.php');
                oralinterview_get_question_set_template($cm, $course);
            }
            require_once($CFG->dirroot . '/mod/oralinterview/lib_enrollment.php');
            oralinterview_sync_candidates_from_exam((int) $id, (int) $oralinterview->course, (int) $oralinterview->jobid, $cmid);
        }
    }

    return $id;
}

function mod_oralinterview_update_instance($oralinterview) {
    global $DB, $CFG;
    if (!empty($oralinterview->jobid)) {
        require_once($CFG->dirroot . '/mod/oralinterview/lib_template_questions.php');
        $oralinterview->name = oralinterview_template_auto_name((int) $oralinterview->jobid);
    }
    $oralinterview->timemodified = time();
    return $DB->update_record('oralinterview', $oralinterview);
}

function mod_oralinterview_delete_instance($id) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    // Find course module id(s) for this instance (templates are currently keyed by cmid).
    $cmids = [];
    try {
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'oralinterview'], IGNORE_MISSING);
        if ($moduleid) {
            $cmids = array_keys($DB->get_records('course_modules', [
                'module' => $moduleid,
                'instance' => $id,
                'deletioninprogress' => 0,
            ], '', 'id'));
        }
    } catch (Throwable $e) {
        // Ignore; cleanup will still proceed for session-linked data below.
        $cmids = [];
    }

    // Delete templates (and their questions) keyed by cmid(s).
    if (!empty($cmids)) {
        list($cmsql, $cmparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
        $templateids = array_keys($DB->get_records_select('oralint_template', "oralinterviewid $cmsql", $cmparams, '', 'id'));
        if (!empty($templateids)) {
            list($tsql, $tparams) = $DB->get_in_or_equal($templateids, SQL_PARAMS_NAMED, 'tid');
            $DB->delete_records_select('oralint_template_q', "templateid $tsql", $tparams);
            $DB->delete_records_select('oralint_template', "id $tsql", $tparams);
        } else {
            $DB->delete_records_select('oralint_template', "oralinterviewid $cmsql", $cmparams);
        }
    }

    // Delete sessions and all dependent records keyed by instance id.
    $sessionids = array_keys($DB->get_records('oralint_session', ['oralinterviewid' => $id], '', 'id'));
    if (!empty($sessionids)) {
        list($ssql, $sparams) = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sid');

        $evaluationids = array_keys($DB->get_records_select('oralint_eval', "sessionid $ssql", $sparams, '', 'id'));
        if (!empty($evaluationids)) {
            list($esql, $eparams) = $DB->get_in_or_equal($evaluationids, SQL_PARAMS_NAMED, 'eid');
            $DB->delete_records_select('oralint_score', "evaluationid $esql", $eparams);
            $DB->delete_records_select('oralint_eval', "id $esql", $eparams);
        }

        $DB->delete_records_select('oralint_committee', "sessionid $ssql", $sparams);
        $DB->delete_records_select('oralint_session_q', "sessionid $ssql", $sparams);
        $DB->delete_records_select('oralint_audit', "sessionid $ssql", $sparams);
        $DB->delete_records_select('oralint_session', "id $ssql", $sparams);
    }

    // Finally delete the module instance.
    $ok = $DB->delete_records('oralinterview', ['id' => $id]);
    $transaction->allow_commit();
    return $ok;
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

// Legacy function aliases for Moodle compatibility
function oralinterview_add_instance($oralinterview) {
    return mod_oralinterview_add_instance($oralinterview);
}

function oralinterview_update_instance($oralinterview) {
    return mod_oralinterview_update_instance($oralinterview);
}

function oralinterview_delete_instance($id) {
    return mod_oralinterview_delete_instance($id);
}

function oralinterview_supports($feature) {
    return mod_oralinterview_supports($feature);
}
