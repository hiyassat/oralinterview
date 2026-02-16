<?php
/**
 * Small snippet to add competency type checkbox to existing competency display
 * Include this in the create_exam_frame.php where competencies are displayed
 */

global $DB, $USER;

// Get jobid from the current context
$jobid = $DB->get_field_sql("
    SELECT jobid
    FROM {test_center_selection}
    WHERE courseid = ?
      AND jobid IS NOT NULL
    LIMIT 1
", [$courseid]);

if ($jobid) {
    // Function to get competency type
    function get_competency_type($jobid, $competency_id) {
        global $DB;
        return $DB->get_field('job_competency_type', 'competency_type', [
            'jobid' => $jobid,
            'competency_id' => $competency_id
        ]) ?: 'both';
    }
    
    // Function to update competency type via AJAX
    ?>
    <script>
    function updateCompetencyType(jobid, competencyId, competencyName, newType) {
        fetch('<?php echo $CFG->wwwroot; ?>/mod/oralinterview/ajax_update_competency_type.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `jobid=${jobid}&competency_id=${competencyId}&competency_name=${encodeURIComponent(competencyName)}&competency_type=${newType}&sesskey=<?php echo sesskey(); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Competency type updated successfully');
            } else {
                console.error('Failed to update competency type');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
    
    <style>
    .competency-type-selector {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.5rem;
        margin: 0.5rem 0;
    }
    .competency-type-selector label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.25rem;
    }
    .competency-type-selector .form-check {
        margin-right: 1rem;
    }
    .competency-type-selector .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    </style>
    
    <?php
}
?>
