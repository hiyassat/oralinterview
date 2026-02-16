<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/form/session_form.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check if user is a committee member for any session in this activity
$is_committee_member = $DB->record_exists_sql("
    SELECT 1
    FROM {oralint_committee} oc
    JOIN {oralint_session} s ON s.id = oc.sessionid
    WHERE s.oralinterviewid = :oralinterviewid
    AND oc.userid = :userid
", ['oralinterviewid' => $cm->instance, 'userid' => $USER->id]);

$can_manage = has_capability('mod/oralinterview:manage', $context);

// Override visibility for committee members (but they can't edit)
if ($is_committee_member && !$can_manage) {
    // Committee members can view but not edit
    $cm->visible = 1;
    $cm->visibleoncoursepage = 1;
    require_login();
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
    $PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', ['id' => $course->id]));
    // Redirect to manage page - committee members can't edit sessions
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]), 
        'Committee members can view but not edit sessions.', null, \core\output\notification::NOTIFY_INFO);
} else {
require_login($course, true, $cm);
}

// Ensure Arabic is forced after login as well (require_login may reset language to user preference).
if ($spacui && function_exists('force_current_language')) {
    force_current_language('ar');
}
// Simplified permission check - temporarily disabled
// access_helper::require_manage($context);

$oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
$jobid = (int)($oralinterview->jobid ?? 0);

// Single question set for the interview (internal template).
$questionset = oralinterview_get_question_set_template($cm, $course);
$templates = [(int)$questionset->id => (string)$questionset->name];
$candidates = $DB->get_records_menu('oralint_candidate', null, 'fullname ASC', 'id, fullname');

// Get existing committee members for this session (if editing)
$existing_committee = [];
if ($sessionid) {
    $committee_members = $DB->get_records('oralint_committee', ['sessionid' => $sessionid], '', 'userid');
    foreach ($committee_members as $member) {
        $user = $DB->get_record('user', ['id' => $member->userid], 'id, username, firstname, lastname, email', MUST_EXIST);
        $existing_committee[] = [
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => fullname($user),
            'email' => $user->email
        ];
    }
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('session_manage', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));

$customdata = [
    'id' => $cm->id,
    'templates' => $templates,
    'questionsetname' => (string)$questionset->name,
    'candidates' => $candidates,
    'existing_committee' => $existing_committee
];
$form = new mod_oralinterview_session_form(null, $customdata);

if ($sessionid) {
    $session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);
    // Get committee member IDs
    $committee_ids = $DB->get_fieldset_select('oralint_committee', 'userid', 'sessionid = ?', [$sessionid]);
    
    $form->set_data((object)[
        'id' => $cm->id,
        'templateid' => $session->templateid,
        'candidateid' => $session->candidateid,
        'interviewdate' => $session->interviewdate,
        'deadline' => $session->deadline,
        'requireall' => $session->requireall,
        'status' => $session->status,
        'sessionid' => $sessionid,
        'committee' => implode(',', $committee_ids)
    ]);
} else {
    $form->set_data((object)['id' => $cm->id]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

if ($data = $form->get_data()) {
    if (!empty($data->sessionid)) {
        // Update existing session
        $record = new stdClass();
        $record->id = $data->sessionid;
        $record->templateid = $data->templateid;
        $record->candidateid = $data->candidateid;
        $record->interviewdate = $data->interviewdate ?? 0;
        $record->deadline = $data->deadline ?? 0;
        $record->requireall = $data->requireall ?? 0;
        $record->status = $data->status ?? 'draft';
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->update_record('oralint_session', $record);
        
        // Update committee members
        if (!empty($data->committee)) {
            // Delete existing committee members
            $DB->delete_records('oralint_committee', ['sessionid' => $data->sessionid]);
            
            // Handle comma-separated string from hidden field
            $committee_ids = is_array($data->committee) ? $data->committee : explode(',', $data->committee);
            $committee_ids = array_filter(array_map('intval', $committee_ids));
            
            // Add new committee members
            $saved_count = 0;
            foreach ($committee_ids as $userid) {
                if ($userid > 0) {
                $committee_record = new stdClass();
                $committee_record->sessionid = $data->sessionid;
                $committee_record->userid = $userid;
                $committee_record->timecreated = time();
                $committee_record->timemodified = time();
                $committee_record->usermodified = $USER->id;
                    $result = $DB->insert_record('oralint_committee', $committee_record);
                    if ($result) {
                        $saved_count++;
                    }
                }
            }
            
            // Enroll committee members in the course
            if ($saved_count > 0) {
                $enrolplugin = enrol_get_plugin('manual');
                if ($enrolplugin) {
                    $enrolinstances = enrol_get_instances($course->id, true);
                    $manualinstance = null;
                    foreach ($enrolinstances as $instance) {
                        if ($instance->enrol === 'manual') {
                            $manualinstance = $instance;
                            break;
                        }
                    }
                    
                    // Prefer the site default manual enrol role id if set.
                    $defaultmanualroleid = (int) get_config('enrol_manual', 'roleid');

                    if (!$manualinstance) {
                        $manualinstance = new stdClass();
                        $manualinstance->courseid = $course->id;
                        $manualinstance->enrol = 'manual';
                        $manualinstance->status = ENROL_INSTANCE_ENABLED;
                        $manualinstance->roleid = $defaultmanualroleid ?: 0;
                        $manualinstance->enrolperiod = 0;
                        $manualinstance->enrolstartdate = 0;
                        $manualinstance->enrolenddate = 0;
                        $manualinstance->timemodified = time();
                        $manualinstance->id = $enrolplugin->add_instance($course, $manualinstance);
                    }
                    
                    $coursecontext = context_course::instance($course->id);
                    $committeeviewroleid = $defaultmanualroleid ?: (int)($manualinstance->roleid ?? 0);
                    foreach ($committee_ids as $userid) {
                        if ($userid > 0 && !is_enrolled($coursecontext, $userid)) {
                            // Enrol in course.
                            $enrolplugin->enrol_user($manualinstance, $userid, $committeeviewroleid ?: null, time(), 0, ENROL_USER_ACTIVE);
                        }
                        // Ensure the user has a course role so mod/oralinterview:view works (affects module visibility).
                        if ($userid > 0 && $committeeviewroleid) {
                            $existingroles = get_user_roles($coursecontext, $userid, true);
                            $hasstudent = false;
                            foreach ($existingroles as $r) {
                                if ((int)$r->roleid === (int)$committeeviewroleid) {
                                    $hasstudent = true;
                                    break;
                                }
                            }
                            if (!$hasstudent) {
                                role_assign($committeeviewroleid, $userid, $coursecontext->id, 'mod_oralinterview', (int)$cm->id);
                            }
                        }
                    }
                }
            }
        }
    } else {
        // Create new session
        $record = new stdClass();
        $record->courseid = $course->id;
        // Use the oralinterview instance ID, not the course module ID
        $record->oralinterviewid = $cm->instance;
        $record->templateid = $data->templateid;
        $record->candidateid = $data->candidateid;
        $record->interviewdate = $data->interviewdate ?? 0;
        $record->deadline = $data->deadline ?? 0;
        $record->requireall = $data->requireall ?? 0;
        $record->status = $data->status ?? 'draft';
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        // Use the oralinterview instance ID, not the course module ID
        $oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
        $record->oralinterviewid = $oralinterview->id;
        
        $sessionid = $DB->insert_record('oralint_session', $record);
        
        // Snapshot template questions into session questions ONCE (stable IDs for scoring/reporting).
        if (!$DB->record_exists('oralint_session_q', ['sessionid' => $sessionid])) {
            $template_questions = $DB->get_records('oralint_template_q', ['templateid' => $record->templateid], 'sortorder ASC');
            foreach ($template_questions as $tq) {
                $sq = new stdClass();
                $sq->sessionid = $sessionid;
                $sq->questionid = $tq->questionid;
                $sq->questiontext = $tq->questiontext;
                $sq->rubric = $tq->rubric;
                $sq->maxscore = $tq->maxscore;
                $sq->sortorder = $tq->sortorder;
                $sq->timecreated = time();
                $sq->timemodified = time();
                $sq->usermodified = $USER->id;
                $DB->insert_record('oralint_session_q', $sq);
            }
            // Store question count for convenience.
            $DB->set_field('oralint_session', 'questioncount', count($template_questions), ['id' => $sessionid]);
        }
        
        // Save committee members
        $committee_data = $data->committee ?? '';
        
        if (!empty($committee_data) && $committee_data !== '' && $committee_data !== '0') {
            // Handle comma-separated string from hidden field
            $committee_ids = is_array($committee_data) ? $committee_data : explode(',', (string)$committee_data);
            $committee_ids = array_filter(array_map('intval', $committee_ids));
            
            $saved_count = 0;
            foreach ($committee_ids as $userid) {
                if ($userid > 0) {
                $committee_record = new stdClass();
                $committee_record->sessionid = $sessionid;
                $committee_record->userid = $userid;
                $committee_record->timecreated = time();
                $committee_record->timemodified = time();
                $committee_record->usermodified = $USER->id;
                    $result = $DB->insert_record('oralint_committee', $committee_record);
                    if ($result) {
                        $saved_count++;
            }
        }
    }

            if ($saved_count > 0) {
                // Enroll committee members in the course so they can access the activity
                $enrolplugin = enrol_get_plugin('manual');
                if ($enrolplugin) {
                    // Get or create manual enrollment instance
                    $enrolinstances = enrol_get_instances($course->id, true);
                    $manualinstance = null;
                    foreach ($enrolinstances as $instance) {
                        if ($instance->enrol === 'manual') {
                            $manualinstance = $instance;
                            break;
                        }
                    }
                    // Prefer the site default manual enrol role id if set.
                    $defaultmanualroleid = (int) get_config('enrol_manual', 'roleid');
                    
                    // If no manual enrollment instance exists, create one
                    if (!$manualinstance) {
                        $manualinstance = new stdClass();
                        $manualinstance->courseid = $course->id;
                        $manualinstance->enrol = 'manual';
                        $manualinstance->status = ENROL_INSTANCE_ENABLED;
                        $manualinstance->roleid = $defaultmanualroleid ?: 0;
                        $manualinstance->enrolperiod = 0;
                        $manualinstance->enrolstartdate = 0;
                        $manualinstance->enrolenddate = 0;
                        $manualinstance->timemodified = time();
                        $manualinstance->id = $enrolplugin->add_instance($course, $manualinstance);
                    }
                    
                    // Enroll each committee member
                    $coursecontext = context_course::instance($course->id);
                    $committeeviewroleid = $defaultmanualroleid ?: (int)($manualinstance->roleid ?? 0);
                    foreach ($committee_ids as $userid) {
                        if ($userid > 0 && !is_enrolled($coursecontext, $userid)) {
                            $enrolplugin->enrol_user($manualinstance, $userid, $committeeviewroleid ?: null, time(), 0, ENROL_USER_ACTIVE);
                        }
                        // Ensure the user has a course role so mod/oralinterview:view works (affects module visibility).
                        if ($userid > 0 && $committeeviewroleid) {
                            $existingroles = get_user_roles($coursecontext, $userid, true);
                            $hasstudent = false;
                            foreach ($existingroles as $r) {
                                if ((int)$r->roleid === (int)$committeeviewroleid) {
                                    $hasstudent = true;
                                    break;
                                }
                            }
                            if (!$hasstudent) {
                                role_assign($committeeviewroleid, $userid, $coursecontext->id, 'mod_oralinterview', (int)$cm->id);
                            }
                        }
                    }
                }
                
                $SESSION->oralinterview_success = get_string('committee_saved_enrolled', 'oralinterview', $saved_count);
            } else {
                $SESSION->oralinterview_error = get_string('committee_save_failed', 'oralinterview');
            }
        } else {
            $SESSION->oralinterview_error = get_string('committee_none_selected', 'oralinterview');
        }
    }

    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/session_edit.php', ['id' => $cm->id, 'sessionid' => $sessionid, 'spacui' => $spacui]));
$PAGE->set_title(get_string('session_manage', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('session_manage', 'oralinterview'));
$PAGE->navbar->add(get_string('session_add', 'oralinterview'));

if ($spacui) {
    oralinterview_spacui_header($cm, 'manage', get_string('session_add', 'oralinterview'));
} else {
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('session_add', 'oralinterview'));
}
if ($spacui) {
    echo '<div class="row justify-content-center">';
    echo '<div class="col-12 col-lg-9 col-xl-8">';
    echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-body p-4">';
}

$form->display();

if ($spacui) {
    echo '</div></div></div></div>';
}
if ($spacui) {
    oralinterview_spacui_footer();
} else {
echo $OUTPUT->footer();
}
