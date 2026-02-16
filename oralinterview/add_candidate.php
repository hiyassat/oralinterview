<?php
require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // oralinterview cm id
$spacui = optional_param('spacui', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// require_login may reset language; enforce Arabic in SPAC UI.
if ($spacui) {
    $SESSION->lang = 'ar';
    $SESSION->forcelang = 'ar';
    if (function_exists('force_current_language')) {
        force_current_language('ar');
    }
}

// Managers only (or candidate managers).
$can_manage = has_capability('mod/oralinterview:manage', $context);
$can_manage_candidates = has_capability('mod/oralinterview:managecandidates', $context);
if (!$can_manage && !$can_manage_candidates) {
    throw new moodle_exception('nopermission', 'error');
}

$PAGE->set_url(new moodle_url('/mod/oralinterview/add_candidate.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->set_title(get_string('add_candidate', 'oralinterview'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('candidates', 'oralinterview'), new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
$PAGE->navbar->add(get_string('add_candidate', 'oralinterview'));

// Get enrolled users for this course
$enrolled_users = get_enrolled_users(context_course::instance($course->id), '', 0, 'u.id, u.firstname, u.lastname, u.email');
$user_options = [];

// Hide users already added to this interview (already have a session in this oral interview).
$already_userids = $DB->get_fieldset_sql("
    SELECT DISTINCT c.linkeduserid
      FROM {oralint_session} s
      JOIN {oralint_candidate} c ON c.id = s.candidateid
     WHERE s.oralinterviewid = ?
       AND c.linkeduserid IS NOT NULL
       AND c.linkeduserid <> 0
", [$cm->instance]);
$already_map = array_fill_keys(array_map('intval', $already_userids ?: []), true);

foreach ($enrolled_users as $user) {
    if (!empty($already_map[(int)$user->id])) {
        continue;
    }
    $user_options[$user->id] = fullname($user) . ' (' . $user->email . ')';
}

// Resolve the single interview question set (internal template).
$questionset = oralinterview_get_question_set_template($cm, $course);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    
    $userids = optional_param_array('userids', [], PARAM_INT);
    $userids = array_filter(array_map('intval', $userids));
    
    if (empty($userids)) {
        $errors[] = get_string('error_select_user', 'oralinterview');
    }
    
    if (empty($errors)) {
        $created = 0;
        foreach ($userids as $userid) {
            // Check if user already exists as candidate.
            $existing_candidate = $DB->get_record('oralint_candidate', ['linkeduserid' => $userid]);
            if (!$existing_candidate) {
                $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
                $candidate = (object)[
                    'fullname' => fullname($user),
                    'email' => $user->email,
                    'phone' => '',
                    'nationalid' => '',
                    'linkeduserid' => $user->id,
                    'status' => 'active',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id
                ];
                $candidateid = (int)$DB->insert_record('oralint_candidate', $candidate);
            } else {
                $candidateid = (int)$existing_candidate->id;
            }

            // Avoid duplicates: if a session already exists for this candidate in this interview, skip.
            if ($DB->record_exists('oralint_session', ['oralinterviewid' => $cm->instance, 'candidateid' => $candidateid])) {
                continue;
            }

            // Create a draft session for this candidate (uses the interview question set).
            $session = (object)[
                'courseid' => $course->id,
                'oralinterviewid' => $cm->instance,
                'candidateid' => $candidateid,
                'templateid' => (int)$questionset->id,
                'interviewdate' => 0,
                'deadline' => 0,
                'requireall' => 1,
                'status' => 'draft',
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => $USER->id
            ];
            $DB->insert_record('oralint_session', $session);
            $created++;
        }
        
        redirect(new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => $spacui]));
    }
}

if ($spacui) {
    oralinterview_spacui_header($cm, 'candidates', get_string('add_candidate', 'oralinterview'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('add_candidate', 'oralinterview'));
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $OUTPUT->notification($error, 'notifymessage');
    }
}

echo html_writer::div(get_string('add_candidate_desc', 'oralinterview'), 'alert alert-info mb-4');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'class' => 'form-horizontal'
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// User selection
echo html_writer::div(get_string('select_enrolled_user', 'oralinterview'), 'form-label');
echo '<div class="mb-3" style="max-width: 720px;">';
echo '<div class="d-flex gap-2 align-items-center flex-wrap mb-2">';
echo '<input type="text" id="candidate-user-filter" class="form-control" style="max-width: 420px;" placeholder="' . s(get_string('candidate_search_users', 'oralinterview')) . '">';
echo '<div class="form-check ms-2">';
echo '<input class="form-check-input" type="checkbox" id="candidate-select-all">';
echo '<label class="form-check-label" for="candidate-select-all">' . s(get_string('select_all', 'oralinterview')) . '</label>';
echo '</div>';
echo '<div class="text-muted small ms-auto" id="candidate-selected-count">' . s(get_string('selected_count', 'oralinterview', 0)) . '</div>';
echo '</div>';

echo '<div class="border rounded p-2" style="max-height: 360px; overflow:auto; background:#fff;">';
foreach ($user_options as $uid => $label) {
    echo '<div class="form-check candidate-user-item" data-label="' . s(mb_strtolower((string)$label)) . '">';
    echo '<input class="form-check-input candidate-user-checkbox" type="checkbox" name="userids[]" value="' . (int)$uid . '" id="cand_user_' . (int)$uid . '">';
    echo '<label class="form-check-label w-100" for="cand_user_' . (int)$uid . '">' . s($label) . '</label>';
    echo '</div>';
}
echo '</div></div>';

echo '<script>
(function(){
  var filter = document.getElementById("candidate-user-filter");
  var selectAll = document.getElementById("candidate-select-all");
  var items = Array.prototype.slice.call(document.querySelectorAll(".candidate-user-item"));
  var boxes = Array.prototype.slice.call(document.querySelectorAll(".candidate-user-checkbox"));
  var countEl = document.getElementById("candidate-selected-count");

  function updateCount(){
    var n = boxes.filter(function(b){ return b.checked; }).length;
    countEl.textContent = "' . addslashes(get_string('selected_count', 'oralinterview', '{$a}')) . '".replace("{$a}", String(n));
  }

  function applyFilter(){
    var q = (filter.value || "").trim().toLowerCase();
    items.forEach(function(it){
      var label = it.getAttribute("data-label") || "";
      it.style.display = (q === "" || label.indexOf(q) !== -1) ? "" : "none";
    });
  }

  filter.addEventListener("input", function(){ applyFilter(); });
  boxes.forEach(function(b){ b.addEventListener("change", updateCount); });
  selectAll.addEventListener("change", function(){
    var visible = items.filter(function(it){ return it.style.display !== "none"; });
    visible.forEach(function(it){
      var cb = it.querySelector("input[type=checkbox]");
      if (cb) { cb.checked = selectAll.checked; }
    });
    updateCount();
  });

  updateCount();
})();
</script>';

// Submit button
echo html_writer::div(html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('add_candidate', 'oralinterview'),
    'class' => 'btn btn-primary'
]), 'form-group mt-4');

echo html_writer::end_tag('form');

if ($spacui) {
    oralinterview_spacui_footer();
} else {
    echo $OUTPUT->footer();
}
