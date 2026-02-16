<?php
/**
 * AJAX handler for updating competency type
 */
require_once('../../config.php');
require_sesskey();

global $DB, $USER;

$jobid = required_param('jobid', PARAM_INT);
$competency_id = required_param('competency_id', PARAM_TEXT);
$competency_name = required_param('competency_name', PARAM_TEXT);
$competency_type = required_param('competency_type', PARAM_ALPHA);

// Validate competency_type
if (!in_array($competency_type, ['interview', 'exam', 'both'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid competency type']);
    exit;
}

try {
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
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
