<?php
require_once('../../config.php');

global $DB, $PAGE, $OUTPUT;

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/mod/oralinterview/manage_competency_types.php');
$PAGE->set_title('Manage Competency Types');
$PAGE->set_heading('Competency Type Management');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $jobid = required_param('jobid', PARAM_INT);
    $competency_id = required_param('competency_id', PARAM_TEXT);
    $competency_name = required_param('competency_name', PARAM_TEXT);
    $competency_type = required_param('competency_type', PARAM_ALPHA);
    
    // Check if record exists
    $existing = $DB->get_record('job_competency_type', [
        'jobid' => $jobid,
        'competency_id' => $competency_id
    ]);
    
    if ($existing) {
        // Update existing record
        $existing->competency_type = $competency_type;
        $existing->timemodified = time();
        $existing->usermodified = $USER->id;
        $DB->update_record('job_competency_type', $existing);
    } else {
        // Insert new record
        $record = new stdClass();
        $record->jobid = $jobid;
        $record->competency_id = $competency_id;
        $record->competency_name = $competency_name;
        $record->competency_type = $competency_type;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->insert_record('job_competency_type', $record);
    }
    
    redirect(new moodle_url('/mod/oralinterview/manage_competency_types.php', ['jobid' => $jobid]));
}

$jobid = optional_param('jobid', 0, PARAM_INT);

echo $OUTPUT->header();
echo $OUTPUT->heading('Manage Competency Types');

// Job selection
if (!$jobid) {
    echo '<div class="card mb-4">
        <div class="card-header">
            <h5>Select Job to Manage Competencies</h5>
        </div>
        <div class="card-body">';
    
    // Robust approved jobs list (supports status/flag schemas) + show title if available.
    $wheres = [];
    $ajcols = $DB->get_columns('approved_jobs');
    if (isset($ajcols['status'])) {
        $wheres[] = "aj.status IN ('approved', 'Approved', 'APPROVED')";
    }
    if (isset($ajcols['flag'])) {
        $wheres[] = "aj.flag = 1";
    }
    $where = !empty($wheres) ? ('WHERE ' . implode(' OR ', $wheres)) : '';

    $hasprj = false;
    try {
        $prjcols = $DB->get_columns('planning_ready_jobs');
        $hasprj = isset($prjcols['jobid']) && isset($prjcols['job_title']);
    } catch (Throwable $e) {
        $hasprj = false;
    }

    if ($hasprj) {
        $approved_jobs = $DB->get_records_sql("
            SELECT aj.*, prj.job_title AS job_title
              FROM {approved_jobs} aj
         LEFT JOIN {planning_ready_jobs} prj ON prj.jobid = aj.jobid
              $where
          ORDER BY prj.job_title ASC, aj.jobid ASC
        ");
    } else {
        $approved_jobs = $DB->get_records_sql("
            SELECT aj.*
              FROM {approved_jobs} aj
              $where
          ORDER BY aj.jobid ASC
        ");
    }
    
    if ($approved_jobs) {
        echo '<div class="row">';
        foreach ($approved_jobs as $job) {
            echo '<div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">' . (!empty($job->job_title) ? format_string($job->job_title) . ' (' . s($job->jobid) . ')' : 'Job ID: ' . s($job->jobid)) . '</h6>
                        <p class="card-text">
                            <small class="text-muted">Approved: ' . userdate($job->approved_date) . '</small>
                        </p>
                        <a href="manage_competency_types.php?jobid=' . $job->jobid . '" class="btn btn-primary btn-sm">Manage Competencies</a>
                    </div>
                </div>
            </div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">No approved jobs found.</div>';
    }
    
    echo '</div></div>';
} else {
    // Show competencies for selected job
    $jobtitle = (string)$DB->get_field('planning_ready_jobs', 'job_title', ['jobid' => $jobid], IGNORE_MISSING);
    if (trim($jobtitle) === '') {
        $jobtitle = 'Job ID: ' . $jobid;
    }
    
    echo '<div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Competencies for: ' . format_string($jobtitle) . '</h5>
            <a href="manage_competency_types.php" class="btn btn-secondary btn-sm">Back to Jobs</a>
        </div>
        <div class="card-body">';
    
    // Get all competencies from competencyindicators (schema varies across your installs).
    $cicols = $DB->get_columns('competencyindicators');
    $hasname = isset($cicols['COMPETENCY_NAME']);
    if ($hasname) {
        $all_competencies = $DB->get_records_sql("
            SELECT DISTINCT COMPETENCY_ID, COMPETENCY_NAME
              FROM {competencyindicators}
             WHERE COMPETENCY_ID IS NOT NULL AND COMPETENCY_ID <> ''
             ORDER BY COMPETENCY_NAME
        ");
    } else {
        $all_competencies = $DB->get_records_sql("
            SELECT DISTINCT COMPETENCY_ID
              FROM {competencyindicators}
             WHERE COMPETENCY_ID IS NOT NULL AND COMPETENCY_ID <> ''
             ORDER BY COMPETENCY_ID
        ");
        // Normalize field name expected by the renderer below.
        foreach ($all_competencies as $k => $c) {
            $all_competencies[$k]->COMPETENCY_NAME = $c->COMPETENCY_ID;
        }
    }
    
    if ($all_competencies) {
        echo '<form method="post" action="manage_competency_types.php">
            <input type="hidden" name="jobid" value="' . $jobid . '" />
            <input type="hidden" name="sesskey" value="' . sesskey() . '" />
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Competency Name</th>
                            <th>Competency ID</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($all_competencies as $competency) {
            // Get current type for this job-competency combination
            $current_type = $DB->get_field('job_competency_type', 'competency_type', [
                'jobid' => $jobid,
                'competency_id' => $competency->COMPETENCY_ID
            ]);
            
            if (!$current_type) {
                $current_type = 'both'; // Default
            }
            
            echo '<tr>
                <td>' . format_string($competency->COMPETENCY_NAME) . '</td>
                <td><code>' . $competency->COMPETENCY_ID . '</code></td>
                <td>
                    <select name="competency_type" class="form-select form-select-sm">
                        <option value="interview" ' . ($current_type == 'interview' ? 'selected' : '') . '>Interview Only</option>
                        <option value="exam" ' . ($current_type == 'exam' ? 'selected' : '') . '>Exam Only</option>
                        <option value="both" ' . ($current_type == 'both' ? 'selected' : '') . '>Both Interview & Exam</option>
                    </select>
                </td>
                <td>
                    <button type="submit" name="competency_id" value="' . $competency->COMPETENCY_ID . '" 
                            name="competency_name" value="' . htmlspecialchars($competency->COMPETENCY_NAME) . '" 
                            class="btn btn-primary btn-sm">Update</button>
                </td>
            </tr>';
        }
        
        echo '</tbody>
                </table>
            </div>
        </form>';
    } else {
        echo '<div class="alert alert-warning">No competencies found in the system.</div>';
    }
    
    echo '</div></div>';
    
    // Show current job-competency mappings
    echo '<div class="card">
        <div class="card-header">
            <h6>Current Job-Competency Mappings</h6>
        </div>
        <div class="card-body">';
    
    $current_mappings = $DB->get_records('job_competency_type', ['jobid' => $jobid], 'competency_name ASC');
    
    if ($current_mappings) {
        echo '<div class="row">';
        foreach ($current_mappings as $mapping) {
            $badge_class = $mapping->competency_type == 'interview' ? 'bg-success' : 
                          ($mapping->competency_type == 'exam' ? 'bg-info' : 'bg-primary');
            
            echo '<div class="col-md-4 mb-2">
                <span class="badge ' . $badge_class . '">' . format_string($mapping->competency_name) . '</span>
                <small class="text-muted">(' . $mapping->competency_type . ')</small>
            </div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-info">No competency mappings set for this job yet.</div>';
    }
    
    echo '</div></div>';
}

echo $OUTPUT->footer();
