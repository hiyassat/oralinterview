<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER, $SESSION;

$id = required_param('id', PARAM_INT);
$spacui = optional_param('spacui', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Enforce Arabic after login (Moodle may reset language).
if ($spacui) {
    $SESSION->lang = 'ar';
    $SESSION->forcelang = 'ar';
    if (function_exists('force_current_language')) {
        force_current_language('ar');
    }
}

$can_manage = has_capability('mod/oralinterview:manage', $context);
if (!$can_manage) {
    throw new moodle_exception('nopermission', 'error');
}

// Load sessions for this interview.
$sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->instance], 'interviewdate DESC');

// Helper: enroll and role-assign committee users so they can see the activity.
function oralinterview_ensure_committee_enrolments(stdClass $course, stdClass $cm, array $userids): void {
    $userids = array_values(array_filter(array_map('intval', $userids)));
    if (empty($userids)) {
        return;
    }
    $enrolplugin = enrol_get_plugin('manual');
    if (!$enrolplugin) {
        return;
    }
    $enrolinstances = enrol_get_instances($course->id, true);
    $manualinstance = null;
    foreach ($enrolinstances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }
    $defaultmanualroleid = (int)get_config('enrol_manual', 'roleid');
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
    foreach ($userids as $userid) {
        if ($userid > 0 && !is_enrolled($coursecontext, $userid)) {
            $enrolplugin->enrol_user($manualinstance, $userid, $committeeviewroleid ?: null, time(), 0, ENROL_USER_ACTIVE);
        }
        if ($userid > 0 && $committeeviewroleid) {
            $existingroles = get_user_roles($coursecontext, $userid, true);
            $hasrole = false;
            foreach ($existingroles as $r) {
                if ((int)$r->roleid === (int)$committeeviewroleid) {
                    $hasrole = true;
                    break;
                }
            }
            if (!$hasrole) {
                role_assign($committeeviewroleid, $userid, $coursecontext->id, 'mod_oralinterview', (int)$cm->id);
            }
        }
    }
}

// Handle bulk assign.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $mode = required_param('mode', PARAM_ALPHA); // add|replace
    $applyall = optional_param('applyall', 0, PARAM_BOOL);
    $sessionids = optional_param_array('sessionids', [], PARAM_INT);
    $committee = optional_param('committee', '', PARAM_TEXT); // comma-separated
    $committeeids = array_values(array_filter(array_map('intval', explode(',', (string)$committee))));

    if (empty($committeeids)) {
        $SESSION->oralinterview_error = get_string('committee_none_selected', 'oralinterview');
        redirect(new moodle_url('/mod/oralinterview/committee_assign.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }

    if ($applyall) {
        $targetsessionids = array_map('intval', array_keys($sessions));
    } else {
        $targetsessionids = array_values(array_filter(array_map('intval', $sessionids)));
    }
    if (empty($targetsessionids)) {
        $SESSION->oralinterview_error = get_string('committee_select_sessions', 'oralinterview');
        redirect(new moodle_url('/mod/oralinterview/committee_assign.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }

    // Ensure access.
    oralinterview_ensure_committee_enrolments($course, $cm, $committeeids);

    $assigned = 0;
    foreach ($targetsessionids as $sid) {
        if (!$DB->record_exists('oralint_session', ['id' => $sid, 'oralinterviewid' => $cm->instance])) {
            continue;
        }
        if ($mode === 'replace') {
            $DB->delete_records('oralint_committee', ['sessionid' => $sid]);
        }
        // Insert missing.
        foreach ($committeeids as $uid) {
            if ($uid <= 0) {
                continue;
            }
            if ($DB->record_exists('oralint_committee', ['sessionid' => $sid, 'userid' => $uid])) {
                continue;
            }
            $rec = new stdClass();
            $rec->sessionid = $sid;
            $rec->userid = $uid;
            $rec->timecreated = time();
            $rec->timemodified = time();
            $rec->usermodified = $USER->id;
            $DB->insert_record('oralint_committee', $rec);
            $assigned++;
        }
        // Update cached committee count.
        $count = $DB->count_records('oralint_committee', ['sessionid' => $sid]);
        $DB->set_field('oralint_session', 'committeecount', $count, ['id' => $sid]);
    }

    $SESSION->oralinterview_success = get_string('committee_assigned', 'oralinterview', $assigned);
    redirect(new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => $spacui]));
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/committee_assign.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->set_title(get_string('committee_assign', 'oralinterview'));
$PAGE->set_heading($course->fullname);

if ($spacui) {
    oralinterview_spacui_header($cm, 'committee', get_string('committee_assign', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('committee_assign', 'oralinterview'));
}

echo html_writer::div(get_string('committee_assign_help', 'oralinterview'), 'alert alert-info');

// Session selector.
echo '<form method="post" class="mt-3">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

echo '<div class="row g-3">';

echo '<div class="col-12 col-lg-6">';
echo '<div class="card border-0 shadow-sm"><div class="card-body p-3">';
echo '<div class="fw-semibold mb-2">' . get_string('committee_select_sessions', 'oralinterview') . '</div>';
echo '<div class="form-check mb-2">';
echo '<input class="form-check-input" type="checkbox" id="applyall" name="applyall" value="1" checked>';
echo '<label class="form-check-label" for="applyall">' . get_string('committee_apply_all', 'oralinterview') . '</label>';
echo '</div>';

echo '<div id="sessions-box" class="border rounded p-2 bg-light" style="max-height:320px; overflow:auto;">';
foreach ($sessions as $s) {
    $sid = (int)$s->id;
    $cand = $DB->get_record('oralint_candidate', ['id' => $s->candidateid], 'fullname', IGNORE_MISSING);
    $label = ($cand ? $cand->fullname : '-') . ' • #' . $sid . ' • ' . s($s->status ?? 'draft');
    echo '<div class="form-check session-item">';
    echo '<input class="form-check-input session-checkbox" type="checkbox" name="sessionids[]" value="' . $sid . '" id="sess_' . $sid . '" checked>';
    echo '<label class="form-check-label w-100" for="sess_' . $sid . '">' . s($label) . '</label>';
    echo '</div>';
}
echo '</div>';
echo '<div class="text-muted small mt-2" id="sessions-count"></div>';
echo '</div></div></div>';

// Committee selector (search).
echo '<div class="col-12 col-lg-6">';
echo '<div class="card border-0 shadow-sm"><div class="card-body p-3">';
echo '<div class="fw-semibold mb-2">' . get_string('session_committee', 'oralinterview') . '</div>';
echo '<div class="position-relative" style="max-width: 520px;">';
echo '<input type="text" id="committee-search-input" class="form-control" placeholder="' . s(get_string('committee_search_placeholder', 'oralinterview')) . '">';
echo '<div id="committee-search-results" class="list-group shadow-sm" style="position:absolute;background:#fff;border:1px solid #dee2e6;max-height:220px;overflow:auto;z-index:1000;display:none;width:100%;margin-top:6px;border-radius:.375rem;"></div>';
echo '</div>';
echo '<div id="committee-selected-list" class="border rounded p-3 bg-light mt-3" style="min-height:64px;"></div>';
echo '<div id="committee-debug" class="text-muted small mt-2">' . s(get_string('committee_selected', 'oralinterview', '0')) . '</div>';
echo '<input type="hidden" name="committee" id="committee-hidden" value="">';
echo '</div></div></div>';

echo '</div>'; // row

echo '<div class="d-flex gap-2 justify-content-end mt-3">';
echo '<select name="mode" class="form-select" style="max-width: 220px;">';
echo '<option value="add">' . get_string('committee_mode_add', 'oralinterview') . '</option>';
echo '<option value="replace">' . get_string('committee_mode_replace', 'oralinterview') . '</option>';
echo '</select>';
echo '<button type="submit" class="btn btn-primary">' . get_string('committee_apply', 'oralinterview') . '</button>';
echo '</div>';
echo '</form>';

// JS: sessions toggling + committee search/selection.
$searchurl = (new moodle_url('/mod/oralinterview/ajax_search_users.php'))->out(false);
$i18n = json_encode([
    'selected' => get_string('committee_selected', 'oralinterview'),
    'none' => get_string('committee_none', 'oralinterview'),
    'searching' => get_string('committee_searching', 'oralinterview'),
    'noresults' => get_string('committee_no_users', 'oralinterview'),
    'searcherror' => get_string('committee_search_error', 'oralinterview'),
    'sessionscount' => get_string('selected_count', 'oralinterview', '{$a}'),
]);

echo '<script>
(function(){
  var applyAll = document.getElementById("applyall");
  var sessionsBox = document.getElementById("sessions-box");
  var sessionChecks = Array.prototype.slice.call(document.querySelectorAll(".session-checkbox"));
  var sessionsCount = document.getElementById("sessions-count");
  function updateSessions(){
    var n = sessionChecks.filter(function(c){ return c.checked; }).length;
    sessionsCount.textContent = ' . json_encode(get_string('selected_count', 'oralinterview', '{$a}')) . '.replace("{$a}", String(n));
    sessionsBox.style.display = applyAll.checked ? "none" : "block";
    sessionsCount.style.display = applyAll.checked ? "none" : "block";
  }
  applyAll.addEventListener("change", updateSessions);
  sessionChecks.forEach(function(c){ c.addEventListener("change", updateSessions); });
  updateSessions();

  var searchInput = document.getElementById("committee-search-input");
  var resultsDiv = document.getElementById("committee-search-results");
  var selectedList = document.getElementById("committee-selected-list");
  var hidden = document.getElementById("committee-hidden");
  var debugDiv = document.getElementById("committee-debug");
  var selectedUsers = new Set();
  var i18n = ' . $i18n . ';

  function updateHidden(){
    var value = Array.from(selectedUsers).join(",");
    hidden.value = value;
    var count = value ? value.split(",").filter(Boolean).length : 0;
    debugDiv.textContent = (count === 0)
      ? i18n.selected.replace("{$a}", i18n.none)
      : i18n.selected.replace("{$a}", String(count));
  }

  document.addEventListener("click", function(e){
    if (e.target.classList.contains("remove-committee")) {
      var tag = e.target.closest(".committee-member-tag");
      var uid = tag.getAttribute("data-userid");
      selectedUsers.delete(uid);
      tag.remove();
      updateHidden();
    }
  });

  function addMember(user){
    var id = String(user.id);
    if (selectedUsers.has(id)) return;
    selectedUsers.add(id);
    var tag = document.createElement("span");
    tag.className = "committee-member-tag badge bg-primary me-1 mb-1";
    tag.setAttribute("data-userid", id);
    tag.style.cssText = "font-size:.95rem;padding:.5rem .6rem;";
    tag.innerHTML = user.display + " <span class=\\"remove-committee ms-2\\" style=\\"cursor:pointer;\\">×</span>";
    selectedList.appendChild(tag);
    updateHidden();
  }

  function performSearch(){
    var q = (searchInput.value || "").trim();
    if (q.length < 2) { resultsDiv.style.display="none"; resultsDiv.innerHTML=""; return; }
    resultsDiv.innerHTML = "<div class=\\"list-group-item\\">"+i18n.searching+"</div>";
    resultsDiv.style.display = "block";
    fetch(' . json_encode($searchurl) . ' + "?q=" + encodeURIComponent(q) + "&id=" + encodeURIComponent(' . json_encode((string)$cm->id) . '))
      .then(function(r){ return r.json(); })
      .then(function(data){
        resultsDiv.innerHTML = "";
        if (data.error) throw new Error(data.error);
        if (!data.length) {
          resultsDiv.innerHTML = "<div class=\\"list-group-item text-muted\\">"+i18n.noresults+"</div>";
        } else {
          data.forEach(function(u){
            if (selectedUsers.has(String(u.id))) return;
            var div = document.createElement("div");
            div.className = "list-group-item list-group-item-action";
            div.style.cursor = "pointer";
            div.innerHTML = "<div class=\\"fw-semibold\\">"+u.display+"</div><div class=\\"small text-muted\\">"+u.email+"</div>";
            div.addEventListener("click", function(){
              addMember(u);
              searchInput.value = "";
              resultsDiv.style.display = "none";
            });
            resultsDiv.appendChild(div);
          });
        }
      })
      .catch(function(err){
        resultsDiv.innerHTML = "<div class=\\"list-group-item text-danger\\">"+(err.message || i18n.searcherror)+"</div>";
        resultsDiv.style.display = "block";
      });
  }

  var t;
  searchInput.addEventListener("input", function(){
    clearTimeout(t);
    t = setTimeout(performSearch, 350);
  });
  document.addEventListener("click", function(e){
    if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) resultsDiv.style.display = "none";
  });

  updateHidden();
})();
</script>';

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}

