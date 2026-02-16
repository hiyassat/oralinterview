<?php
require_once('../../config.php');

global $DB, $USER;

// Get the session you created
$session = $DB->get_record('oralint_session', ['id' => 1], '*', IGNORE_MISSING);
if (!$session) {
    echo "No session found with ID 1. Please create a session first.";
    exit;
}

// Create a dummy evaluation record for testing
$evaluation = new stdClass();
$evaluation->sessionid = $session->id;
$evaluation->userid = $USER->id; // Current user
$evaluation->status = 'notstarted';
$evaluation->timecreated = time();
$evaluation->timemodified = time();
$evaluation->usermodified = $USER->id;

$evalid = $DB->insert_record('oralint_eval', $evaluation);

echo "Created evaluation record with ID: $evalid<br>";
echo "You can now access: <a href='evaluate.php?id=5&evalid=$evalid'>Evaluate Interview</a>";

// Also create session questions from template questions
$template_questions = $DB->get_records('oralint_template_q', ['templateid' => $session->templateid]);
foreach ($template_questions as $tq) {
    $sq = new stdClass();
    $sq->sessionid = $session->id;
    $sq->questiontext = $tq->questiontext;
    $sq->rubric = $tq->rubric;
    $sq->maxscore = $tq->maxscore;
    $sq->sortorder = $tq->sortorder;
    $sq->timecreated = time();
    $sq->timemodified = time();
    $sq->usermodified = $USER->id;
    $DB->insert_record('oralint_session_q', $sq);
}

echo "<br>Created session questions from template.";
?>
