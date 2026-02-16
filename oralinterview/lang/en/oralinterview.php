<?php
$string['pluginname'] = 'Oral Interview';
$string['oralinterviewname'] = 'Oral interview name';
$string['name_auto_from_job'] = 'Set automatically from the job and date when you select a job below.';
$string['jobid'] = 'Job';
$string['jobid_help'] = 'Select the approved job this oral interview activity is for. Templates, questions, and sessions will be scoped to this job.';
$string['select_job'] = 'Select a job...';
$string['nojobsfound'] = 'No jobs were found in the approved jobs table. Please add/approve jobs first.';
$string['exam_frame_weights'] = 'Exam: {$a->exam}% | Interview: {$a->interview}%';
$string['exam_frame_top_n'] = 'Interview selection: top {$a}';
$string['modulename'] = 'Oral Interview';
$string['modulenameplural'] = 'Oral Interviews';
$string['pluginadministration'] = 'Oral Interview administration';

// Capability strings
$string['oralinterview:view'] = 'View oral interview activity';
$string['oralinterview:addinstance'] = 'Add new oral interview activities';
$string['oralinterview:manage'] = 'Manage oral interviews';
$string['oralinterview:managecandidates'] = 'Manage candidates';
$string['oralinterview:evaluate'] = 'Evaluate interviews';
$string['oralinterview:viewown'] = 'View own evaluations';
$string['oralinterview:viewall'] = 'View all interviews';
$string['oralinterview:export'] = 'Export interview data';
$string['oralinterview:lock'] = 'Lock/unlock sessions';
$string['oralinterview:recalculate'] = 'Recalculate scores';
$string['oralinterview:override'] = 'Override evaluations';
$string['oralinterview:reopen'] = 'Reopen evaluations';
$string['manage'] = 'Manage interviews';
$string['manage_hint'] = 'Create templates, candidates, and sessions here.';
$string['myinterviews'] = 'My Interviews';
$string['my_hint'] = 'Review sessions assigned to you as a committee member.';
$string['templates'] = 'Interview templates';
$string['interview_questions'] = 'Interview questions';
$string['committee_assign'] = 'Assign committee';
$string['committee_assign_help'] = 'Select committee members once, then apply them to all sessions or selected sessions.';
$string['committee_apply_all'] = 'Apply to all sessions';
$string['committee_select_sessions'] = 'Select sessions';
$string['committee_mode_add'] = 'Add to sessions';
$string['committee_mode_replace'] = 'Replace sessions committee';
$string['committee_apply'] = 'Apply';
$string['committee_assigned'] = 'Assigned {$a} committee member-session link(s).';
$string['candidates'] = 'Candidates';
$string['assigned_candidates'] = 'Assigned Candidates';
$string['assigned_candidates_desc'] = 'These candidates are assigned to interview sessions in this activity with specific templates.';
$string['available_candidates'] = 'Available Candidates';
$string['available_candidates_desc'] = 'These candidates are available to be assigned to interview sessions. Click "Create Session" to assign them to a template.';
$string['create_session'] = 'Create Session';
$string['edit_session'] = 'Edit Session';
$string['template'] = 'Template';
$string['interview_date'] = 'Interview Date';
$string['evaluate'] = 'Evaluate';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['assign'] = 'Assign';
$string['template_assigned'] = 'Template assigned successfully!';
$string['not_assigned'] = 'Not Assigned';
$string['add_candidate'] = 'Add Candidate';
$string['add_candidate_desc'] = 'Select an enrolled user and assign them to a template for this oral interview activity.';
$string['select_template'] = 'Select Template';
$string['candidate_added'] = 'Candidate added successfully!';
$string['error_select_user'] = 'Please select a user';
$string['error_select_template'] = 'Please select a template';
$string['available_interview_questions'] = 'Available Interview Questions';
$string['no_interview_questions'] = 'No interview questions available in the question bank. Please create some interview-type questions first.';
$string['no_template_questions'] = 'No questions added to this template yet.';
$string['invalid_total_questions'] = 'Please enter a valid total number of questions.';
$string['no_competencies_for_job'] = 'No competencies configured for this job.';
$string['competency_weight'] = 'Weight';
$string['competency_weight_percent'] = '{$a}%';
$string['generate_by_weights'] = 'Generate by weights';
$string['generate_by_weights_help'] = 'Distribute questions across competencies according to their weights (اوزان) from competency_areas.';
$string['generate_by_weights_done'] = 'Added {$a} questions to template.';
$string['total_questions'] = 'Total questions';
$string['select_enrolled_user'] = 'Select enrolled user';
$string['select_user'] = 'Select user';
$string['candidate_search_users'] = 'Search users...';
$string['select_all'] = 'Select all';
$string['selected_count'] = 'Selected: {$a}';

// Session management strings
$string['session_manage'] = 'Manage Sessions';
$string['session_manage_hint'] = 'Create and manage interview sessions here.';
$string['session_add'] = 'Add New Session';
$string['session_template'] = 'Template';
$string['session_candidate'] = 'Candidate';
$string['session_committee'] = 'Committee Members';
$string['session_interviewdate'] = 'Interview Date';
$string['session_deadline'] = 'Evaluation Deadline';
$string['session_requireall'] = 'Require All Committee Members';
$string['session_status'] = 'Status';
$string['session_status_draft'] = 'Draft';
$string['session_status_active'] = 'Active';
$string['session_progress'] = 'Progress';
$string['session_finalscore'] = 'Final Score';
$string['session_actions'] = 'Actions';
$string['session_no_sessions'] = 'No interview sessions have been created yet.';
$string['session_instruction'] = 'Select a template, candidate, and committee members to create an interview session.';
$string['session_no_templates'] = 'No templates found. Please create a template first.';
$string['session_no_candidates'] = 'No candidates found. Please add candidates first.';
$string['session_committee_required'] = 'You must select at least one committee member.';
$string['templatequestions'] = 'Template Questions';
$string['questions'] = 'Questions';
$string['addtemplate'] = 'Add Template';
$string['addquestion'] = 'Add Question';
$string['template_name'] = 'Template Name';
$string['template_jobtitle'] = 'Job Title';
$string['template_description'] = 'Description';
$string['template_edit'] = 'Edit Template';
$string['question_text'] = 'Question Text';
$string['question_rubric'] = 'Scoring Rubric';
$string['question_maxscore'] = 'Maximum Score';
$string['addquestion'] = 'Add Question';

// Candidate management strings
$string['candidate_add'] = 'Add Candidate';
$string['import_csv'] = 'Import CSV';
$string['candidate_fullname'] = 'Full Name';
$string['candidate_email'] = 'Email';
$string['candidate_phone'] = 'Phone';
$string['candidate_nationalid'] = 'National ID';
$string['candidate_linkeduser'] = 'Linked Moodle User';
$string['candidate_actions'] = 'Actions';
$string['nocandidates'] = 'No candidates have been added yet.';

// Import related strings
$string['import_summary'] = 'Import complete: {$a->imported} imported, {$a->skipped} skipped (duplicates).';
$string['csv_error_empty'] = 'CSV file is empty or contains no valid data.';
$string['csv_error_upload'] = 'Error uploading CSV file.';
$string['import_preview_headline'] = 'Import Preview';
$string['import_preview_hint'] = 'Review the data below before confirming the import.';
$string['import_preview_summary'] = 'Total rows: {$a->total}, Invalid rows: {$a->invalid}';
$string['import_confirm'] = 'Confirm Import';
$string['candidate_edit'] = 'Edit';
$string['candidate_remove'] = 'Delete candidate';
$string['candidate_remove_confirm'] = 'Delete this candidate from this interview? This will delete their session(s) and any evaluations for this interview.';
$string['candidate_removed'] = 'Candidate removed from this interview.';
$string['sync_candidates_from_exam'] = 'Sync from exam';
$string['sync_candidates_done'] = 'Synced: {$a->synced} candidate(s) added, {$a->skipped} already assigned.';
$string['sync_candidates_no_job'] = 'This activity has no job set; sync from exam is not available.';
$string['sync_candidates_no_frame'] = 'No exam frame found for this job in exam_frame_settings (jobid/courseid/sessionid).';
$string['sync_candidates_no_users'] = 'No users found in test_session_assignments for this exam session.';

// Session management strings
$string['session_manage'] = 'Manage Sessions';
$string['session_add'] = 'Add Session';
$string['session_manage_hint'] = 'Create interview sessions by combining templates with candidates and assigning committee members.';
$string['session_template'] = 'Template';
$string['session_candidate'] = 'Candidate';
$string['session_status'] = 'Status';
$string['session_actions'] = 'Actions';
$string['nosessions'] = 'No sessions have been created yet.';

// Session form strings
$string['session_committee'] = 'Committee Members';
$string['session_interviewdate'] = 'Interview Date';
$string['session_deadline'] = 'Evaluation Deadline';
$string['session_requireall'] = 'Require all committee members to evaluate';
$string['session_status_draft'] = 'Draft';
$string['session_status_active'] = 'Active';
$string['session_instruction'] = 'Select a template, candidate, and committee members to create an interview session.';
$string['no_committee_members'] = 'No users available for committee assignment. Users need to have evaluation permissions.';

// Committee search UI strings (SPAC UI / session form).
$string['committee_search_placeholder'] = 'Search users (username, name, email)... Type at least 2 characters';
$string['committee_searching'] = 'Searching...';
$string['committee_no_users'] = 'No users found';
$string['committee_search_error'] = 'Error searching. Please try again.';
$string['committee_selected'] = 'Selected: {$a}';
$string['committee_none'] = 'NONE';

// Session edit/save messages.
$string['committee_saved_enrolled'] = 'Successfully saved {$a} committee member(s) and enrolled them in the course';
$string['committee_save_failed'] = 'Failed to save committee members.';
$string['committee_none_selected'] = 'You must select at least one committee member.';

// Session action strings
$string['session_recalculate'] = 'Recalculate';
$string['session_override'] = 'Override';
$string['session_reopen'] = 'Reopen';
$string['session_lock'] = 'Lock';
$string['session_unlock'] = 'Unlock';
$string['session_evaluate'] = 'Evaluate';

// My interviews strings
$string['status'] = 'Status';
$string['noassigned'] = 'No interviews have been assigned to you for evaluation.';
$string['myinterviews'] = 'My Interviews';

// Evaluation strings
$string['evaluation_all_required'] = 'All questions must be scored when submitting evaluation.';
$string['evaluation_score_range'] = 'Scores must be between 0 and the maximum score for each question.';
$string['evaluation_saved'] = 'Evaluation saved successfully.';
$string['evaluation_submitted'] = 'Evaluation submitted successfully.';

// Recalculate messages.
$string['session_recalculate_done'] = 'Final score recalculated: {$a->score} ({$a->percent}%)';
$string['session_recalculate_nothing'] = 'Nothing to recalculate yet (missing questions or submitted evaluations).';

// Evaluation interface strings
$string['evaluate'] = 'Evaluate';
$string['score'] = 'Score';
$string['comment'] = 'Comment';
$string['submit'] = 'Submit';
$string['save_draft'] = 'Save Draft';
$string['questiontext'] = 'Question';
$string['maxscore'] = 'Max Score';

// Evaluation status strings
$string['evaluation_status_notstarted'] = 'Not Started';
$string['evaluation_status_inprogress'] = 'In Progress';
$string['evaluation_status_submitted'] = 'Submitted';
$string['override_evaluation'] = 'Override Evaluation';
$string['override_reason'] = 'Override Reason';
$string['override_reason_required'] = 'Override reason is required.';

// Session management strings
$string['session_progress'] = 'Progress';
$string['session_finalscore'] = 'Final Score';
$string['session_no_sessions'] = 'No sessions have been created yet.';

// Reopen evaluation strings
$string['reopen_reason_required'] = 'Reopen reason is required.';
$string['reopen_instruction'] = 'Select an evaluation to reopen. This will allow the evaluator to modify their scores and comments.';
$string['reopen_reason_label'] = 'Reason for reopening:';
$string['reopen_submit'] = 'Reopen Evaluation';
$string['notemplates'] = 'No templates have been created yet.';
$string['noquestions'] = 'This template does not have any questions.';
$string['maxscore'] = 'Max score';
$string['committee_member'] = 'Committee member';
$string['export_session'] = 'Export with comments';
$string['export_scores_only'] = 'Export scores only';
$string['average'] = 'Average';
$string['notapplicable'] = 'N/A';
$string['session_report'] = 'Session report';
$string['template_questions'] = 'Template questions';
$string['competency'] = 'Competency';
$string['indicator'] = 'Indicator';
$string['viewquestion'] = 'View question';
$string['sessionlocked'] = 'This session is locked. You cannot evaluate until it is unlocked.';
$string['event_evaluation_started'] = 'Evaluation started';
$string['event_question_scored'] = 'Question scored';
$string['event_evaluation_submitted'] = 'Evaluation submitted';
$string['event_evaluation_reopened'] = 'Evaluation reopened';
$string['event_session_completed'] = 'Session completed';
$string['event_score_overridden'] = 'Score overridden';
$string['task_reminder'] = 'Interview reminder notifications';
$string['task_retention'] = 'Data retention anonymization';
$string['message_subject_assigned'] = 'New interview assignment';
$string['message_body_assigned'] = 'You have been assigned to session {$a->sessionid}. Deadline: {$a->deadline}.';
$string['message_subject_reminder'] = 'Interview deadline reminder';
$string['message_body_reminder'] = 'Reminder: session {$a->sessionid} is due by {$a->deadline}.';
$string['session_lock'] = 'Lock session';
$string['session_unlock'] = 'Unlock session';
$string['session_report'] = 'Session report';
$string['session_recalculate'] = 'Recalculate final score';
$string['session_override'] = 'Override evaluation';
$string['session_reopen'] = 'Reopen evaluation';
$string['override_reason_hint'] = 'Document why you are overriding the committee scores.';
$string['override_reason_required'] = 'You must provide a reason for the override.';
$string['reopen_instruction'] = 'Provide a reason for reopening the evaluation so the committee can resubmit.';
$string['reopen_reason_label'] = 'Reopen reason';
$string['reopen_submit'] = 'Reopen evaluation';
$string['reopen_reason_required'] = 'A reopen reason is required.';
$string['lock_instruction'] = 'Locking prevents further submissions until you unlock the session.';
$string['lock_reason_label'] = 'Reason for locking/unlocking';
$string['lock_reason_required'] = 'Provide a reason to lock or unlock the session.';
$string['committee_member'] = 'Committee member';
$string['export_session'] = 'Export with comments';
$string['export_scores_only'] = 'Export scores only';
$string['average'] = 'Average';
$string['notapplicable'] = 'N/A';
$string['messageprovider:assigned'] = 'New interview assignment';
$string['messageprovider:deadline_reminder'] = 'Interview deadline reminder';
$string['setting_retentiondays'] = 'Retention days';
$string['setting_retentiondays_desc'] = 'Number of days to keep candidate PII before anonymization.';
$string['setting_messagingreminder'] = 'Reminder lead time';
$string['setting_messagingreminder_desc'] = 'Days before deadline to send reminder notifications.';
$string['exam_frame_info'] = 'Frame settings: exam %s%% | interview %s%%';
$string['exam_frame_selection_top'] = 'Selection: top %s';
$string['exam_frame_selection_top_n'] = 'Count: %s';
