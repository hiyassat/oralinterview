<?php
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/oralinterview/lib_template_questions.php');

class mod_oralinterview_mod_form extends moodleform_mod {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // 1. Job first (required): select approved job so name can be auto-generated from it.
        $jobs = [];
        try {
            // Build a filter that matches your schema without referencing missing columns.
            $columns = $DB->get_columns('approved_jobs');
            $where = [];
            $params = [];

            // Prefer showing job title from planning_ready_jobs if available.
            $hasplanning = false;
            try {
                $planningcols = $DB->get_columns('planning_ready_jobs');
                $hasplanning = isset($planningcols['jobid']) && isset($planningcols['job_title']);
            } catch (Throwable $e) {
                $hasplanning = false;
            }

            // Build a filter that matches your schema without referencing missing columns.
            // When joining planning_ready_jobs, qualify with aj. to avoid ambiguity.
            $prefix = $hasplanning ? 'aj.' : '';
            if (isset($columns['status'])) {
                $where[] = "{$prefix}status IN ('approved', 'Approved', 'APPROVED')";
            }
            if (isset($columns['flag'])) {
                $where[] = "{$prefix}flag = 1";
            }

            $wheresql = '';
            if (!empty($where)) {
                $wheresql = 'WHERE ' . implode(' OR ', $where);
            }

            if ($hasplanning) {
                $jobs = $DB->get_records_sql_menu("
                    SELECT aj.jobid,
                           CONCAT(
                               COALESCE(NULLIF(prj.job_title, ''), aj.jobid),
                               CASE WHEN prj.job_title IS NULL OR prj.job_title = '' THEN '' ELSE CONCAT(' (', aj.jobid, ')') END
                           ) AS joblabel
                      FROM {approved_jobs} aj
                 LEFT JOIN {planning_ready_jobs} prj ON prj.jobid = aj.jobid
                      $wheresql
                     ORDER BY prj.job_title ASC, aj.jobid ASC
                ", $params);
            } else {
                // Try filtered first (show jobid).
                $jobs = $DB->get_records_sql_menu("
                    SELECT jobid, jobid AS joblabel
                      FROM {approved_jobs}
                      $wheresql
                     ORDER BY jobid ASC
                ", $params);
            }

            // Fallback: show all jobs (better than an empty dropdown).
            if (empty($jobs)) {
                if (!empty($hasplanning)) {
                    $jobs = $DB->get_records_sql_menu("
                        SELECT aj.jobid,
                               CONCAT(
                                   COALESCE(NULLIF(prj.job_title, ''), aj.jobid),
                                   CASE WHEN prj.job_title IS NULL OR prj.job_title = '' THEN '' ELSE CONCAT(' (', aj.jobid, ')') END
                               ) AS joblabel
                          FROM {approved_jobs} aj
                     LEFT JOIN {planning_ready_jobs} prj ON prj.jobid = aj.jobid
                         ORDER BY prj.job_title ASC, aj.jobid ASC
                    ");
                } else {
                    $jobs = $DB->get_records_sql_menu("
                        SELECT jobid, jobid AS joblabel
                          FROM {approved_jobs}
                         ORDER BY jobid ASC
                    ");
                }
            }
        } catch (Throwable $e) {
            // Leave empty; we'll show a helpful message below.
            $jobs = [];
        }
        $joboptions = [0 => get_string('select_job', 'oralinterview')] + ($jobs ?: []);
        $mform->addElement('select', 'jobid', get_string('jobid', 'oralinterview'), $joboptions);
        $mform->setType('jobid', PARAM_INT);
        // When there is exactly one job, preselect it so the name auto-fills immediately.
        if (count($jobs) === 1) {
            $singlejobid = (int) array_key_first($jobs);
            $mform->setDefault('jobid', $singlejobid);
            $mform->setDefault('name', oralinterview_template_auto_name($singlejobid));
        }
        // Only require selection when there are jobs available to pick.
        if (!empty($jobs)) {
            $mform->addRule('jobid', get_string('required'), 'required', null, 'client');
        } else {
            $mform->addElement('static', 'jobid_help_empty', '', get_string('nojobsfound', 'oralinterview'));
        }
        $mform->addHelpButton('jobid', 'jobid', 'oralinterview');

        // 2. Interview name: auto-generated from job + date (not editable).
        $defaultnamemsg = get_string('name_auto_from_job', 'oralinterview');
        $mform->addElement('static', 'name_display', get_string('oralinterviewname', 'oralinterview'),
            '<span id="oralinterview_auto_name_display" class="font-weight-bold" data-default="' . s($defaultnamemsg) . '">' . s($defaultnamemsg) . '</span>');
        $mform->addElement('hidden', 'name', '');
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'interview_auto_date', date('Y-m-d'));
        $mform->setType('interview_auto_date', PARAM_ALPHA);

        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        // When job is selected, set interview name = job label + date (hidden input + display span).
        $mform->addElement('html', '
<script>
(function() {
    function run() {
        var sel = document.querySelector("select[name=\"jobid\"], #id_jobid");
        var nameInput = document.querySelector("input[name=\"name\"]");
        var dateInput = document.querySelector("input[name=\"interview_auto_date\"]");
        var display = document.getElementById("oralinterview_auto_name_display");
        if (!sel || !nameInput) return;
        var dateStr = (dateInput && (dateInput.value || dateInput.getAttribute("value"))) ? (dateInput.value || dateInput.getAttribute("value")) : "";
        function updateName() {
            var val = sel.value;
            var label = "";
            if (val && val !== "0") {
                var opt = sel.options[sel.selectedIndex];
                label = (opt && opt.text) ? opt.text.replace(/\s*\(\d+\)\s*$/, "").trim() : ("Job #" + val);
                if (dateStr) label = label + " " + dateStr;
            }
            nameInput.value = label;
            if (display) display.textContent = label || (display.getAttribute("data-default") || "");
        }
        sel.addEventListener("change", updateName);
        updateName();
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run); else run();
})();
</script>');
    }

    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        $jobid = (int)($mform->getSubmitValue('jobid') ?: $this->current->jobid ?? 0);
        if ($jobid > 0) {
            $mform->setDefault('name', oralinterview_template_auto_name($jobid));
            // Freeze when editing so name cannot be changed (auto-generated from job + date).
            if (!empty($this->current->id)) {
                $mform->freeze('name');
            }
        }
    }
}
