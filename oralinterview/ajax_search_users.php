<?php
/**
 * AJAX endpoint to search all Moodle users
 * Used for committee member selection
 */

require_once('../../config.php');

global $DB, $PAGE, $OUTPUT;

// Security check
require_login();

// Check if user has manage permission - try to get from session or check site admin
$has_permission = false;

// Try to get oral interview ID from referrer or session
$id = optional_param('id', 0, PARAM_INT);
if ($id) {
    $cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $has_permission = has_capability('mod/oralinterview:manage', $context);
}

// If no ID or no permission, check if site admin
if (!$has_permission) {
    $has_permission = is_siteadmin();
}

// If still no permission, try to find any oral interview module
if (!$has_permission) {
    $oralinterview_cm = $DB->get_record_sql("
        SELECT cm.id
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE m.name = 'oralinterview'
        LIMIT 1
    ");
    
    if ($oralinterview_cm) {
        $context = context_module::instance($oralinterview_cm->id);
        $has_permission = has_capability('mod/oralinterview:manage', $context);
    }
}

if (!$has_permission) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get search term
$query = optional_param('q', '', PARAM_TEXT);
$limit = optional_param('limit', 20, PARAM_INT);

header('Content-Type: application/json');

if (empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// Search users by username, firstname, lastname, email
$search = '%' . $DB->sql_like_escape($query) . '%';

try {
    // Use Moodle's sql_like helper for proper database compatibility
    $conditions = array();
    $params = array();
    
    $conditions[] = $DB->sql_like('username', ':search1', false, false);
    $params['search1'] = $search;
    
    $conditions[] = $DB->sql_like('firstname', ':search2', false, false);
    $params['search2'] = $search;
    
    $conditions[] = $DB->sql_like('lastname', ':search3', false, false);
    $params['search3'] = $search;
    
    $conditions[] = $DB->sql_like('email', ':search4', false, false);
    $params['search4'] = $search;
    
    $where = 'deleted = 0 AND (' . implode(' OR ', $conditions) . ')';
    
    $users = $DB->get_records_select(
        'user',
        $where,
        $params,
        'firstname, lastname',
        'id, username, firstname, lastname, email',
        0,
        $limit
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$results = [];
foreach ($users as $user) {
    $results[] = [
        'id' => $user->id,
        'username' => $user->username,
        'fullname' => fullname($user),
        'email' => $user->email,
        'display' => fullname($user) . ' (' . $user->username . ')'
    ];
}

echo json_encode($results);
