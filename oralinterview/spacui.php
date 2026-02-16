<?php
// Helper functions for rendering Oral Interview pages inside the SPAC website UI (no Moodle navbar).

defined('MOODLE_INTERNAL') || die();

/**
 * Mark the current session as "SPAC UI mode" so oral interview pages can auto-force spacui=1.
 */
function oralinterview_spacui_mark_session(): void {
    global $SESSION;
    $SESSION->oralinterview_spacui = 1;
}

/**
 * If user is in SPAC UI mode, force current request to have spacui=1 (prevents falling back to Moodle UI).
 * Call this near the top of each page after config.php.
 */
function oralinterview_spacui_autoforce(): void {
    global $CFG, $SESSION;
    if (empty($SESSION->oralinterview_spacui)) {
        return;
    }
    $current = optional_param('spacui', 0, PARAM_BOOL);
    if ($current) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri === '') {
        return;
    }

    // Normalise REQUEST_URI to a local path under $CFG->wwwroot (avoid doubling base path like /spac_custom_core/spac_custom_core/...).
    $basepath = (string)(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '');
    $local = $uri;
    if ($basepath !== '' && $basepath !== '/') {
        // PHP 7.4 compatible "starts_with".
        $prefix = $basepath . '/';
        if (strpos($local, $prefix) === 0) {
            $local = substr($local, strlen($basepath));
        } else if ($local === $basepath) {
            $local = '/';
        }
    }
    if ($local === '' || $local[0] !== '/') {
        $local = '/' . ltrim($local, '/');
    }

    // Add/replace spacui=1 in the query string without parsing (parsing could create arrays for repeated params).
    if (preg_match('/([?&])spacui=\d+/i', $local)) {
        $local = preg_replace('/([?&])spacui=\d+/i', '$1spacui=1', $local);
    } else {
        // PHP 7.4 compatible "contains".
        $local .= (strpos($local, '?') !== false ? '&' : '?') . 'spacui=1';
    }

    redirect($CFG->wwwroot . $local);
}

/**
 * Bootstrap SPAC UI mode early (before moodleform construction) so get_string() uses Arabic.
 * Call right after including this file on SPAC pages.
 */
function oralinterview_spacui_bootstrap(): void {
    global $SESSION;
    $spacui = optional_param('spacui', 0, PARAM_BOOL);
    if ($spacui || !empty($SESSION->oralinterview_spacui)) {
        oralinterview_spacui_mark_session();
        // Force Arabic aggressively (Moodle may reset language after require_login()).
        $SESSION->lang = 'ar';
        $SESSION->forcelang = 'ar';
        if (function_exists('force_current_language')) {
            force_current_language('ar');
        }
    }
}

/**
 * Get the single "question set" template for this oral interview activity.
 * We keep using oralint_template internally, but we do not expose templates in the SPAC UI.
 *
 * NOTE: oralint_template.oralinterviewid is keyed by *cmid* in this plugin.
 *
 * @param stdClass $cm coursemodule record (must include id, instance, course)
 * @param stdClass $course course record
 * @return stdClass template record
 */
function oralinterview_get_question_set_template(stdClass $cm, stdClass $course): stdClass {
    global $DB, $USER;

    // Try to find the oldest template for this activity (cmid keyed).
    $existing = $DB->get_records('oralint_template', ['oralinterviewid' => $cm->id], 'timecreated ASC', '*', 0, 1);
    if (!empty($existing)) {
        return reset($existing);
    }

    // Create one if missing (should be rare; create.php normally creates it).
    $oi = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
    $jobid = (int)($oi->jobid ?? 0);
    $jobtitle = '';
    if ($jobid) {
        $jobtitle = (string)$DB->get_field('planning_ready_jobs', 'job_title', ['jobid' => $jobid], IGNORE_MISSING);
    }
    $jobtitle = trim($jobtitle) !== '' ? $jobtitle : ($jobid ? (string)$jobid : '');

    $tpl = new stdClass();
    $tpl->courseid = $course->id;
    $tpl->oralinterviewid = $cm->id; // cmid keyed.
    $tpl->name = $oi->name ?: get_string('interview_questions', 'oralinterview');
    $tpl->jobtitle = $jobtitle;
    $tpl->jobid = $jobid;
    $tpl->description = '';
    $tpl->status = 'draft';
    $tpl->maxscore = 10;
    $tpl->timecreated = time();
    $tpl->timemodified = time();
    $tpl->usermodified = $USER->id;
    $tpl->id = (int)$DB->insert_record('oralint_template', $tpl);
    return $tpl;
}

/**
 * Render the SPAC header + tabs for an oral interview course module.
 *
 * @param cm_info|stdClass $cm Course module record (must have id, instance, course)
 * @param string $active One of: manage|schedule|committee|candidates|questions|report|evaluate
 * @param string $title Page title shown in the content header
 */
function oralinterview_spacui_header($cm, string $active, string $title): void {
    global $CFG, $DB, $USER;

    oralinterview_spacui_mark_session();
    // Force Arabic for SPAC UI pages to match the rest of the project.
    global $SESSION;
    $SESSION->lang = 'ar';
    $SESSION->forcelang = 'ar';
    if (function_exists('force_current_language')) {
        force_current_language('ar');
    }

    $hide_home_link = true;
    require_once($CFG->dirroot . '/includes/header_exam.php');

    $oi = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', IGNORE_MISSING);
    $jobid = (int)($oi->jobid ?? 0);
    $jobtitle = '';
    if ($jobid) {
        $jobtitle = (string)$DB->get_field('planning_ready_jobs', 'job_title', ['jobid' => $jobid], IGNORE_MISSING);
    }
    $joblabel = trim($jobtitle) !== '' ? ($jobtitle . ' (' . $jobid . ')') : ($jobid ? ('Job #' . $jobid) : '');

    // Exam frame display (exam_percent, interview_percent, etc.) under the interview name section.
    $frame_display = null;
    if ($jobid > 0) {
        require_once($CFG->dirroot . '/mod/oralinterview/lib_enrollment.php');
        $frame_display = oralinterview_get_exam_frame_display_for_job($jobid, (int)($cm->course ?? 0));
    }

    $base = [
        'manage' => new moodle_url('/mod/oralinterview/manage.php', ['id' => $cm->id, 'spacui' => 1]),
        'schedule' => new moodle_url('/mod/oralinterview/schedule.php', ['id' => $cm->id, 'spacui' => 1]),
        'candidates' => new moodle_url('/mod/oralinterview/candidates.php', ['id' => $cm->id, 'spacui' => 1]),
        'questions' => new moodle_url('/mod/oralinterview/questions.php', ['id' => $cm->id, 'spacui' => 1]),
        'committee' => new moodle_url('/mod/oralinterview/committee_assign.php', ['id' => $cm->id, 'spacui' => 1]),
    ];

    echo '<div class="container my-5" dir="rtl">';
    echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
    echo '<div>';
    echo '<div class="text-muted small mb-1">نظام المقابلات</div>';
    echo '<h3 class="m-0">' . s($title) . '</h3>';
    if ($joblabel !== '') {
        echo '<div class="text-muted mt-1">' . s($joblabel) . '</div>';
    }
    if ($frame_display) {
        $exam_pct = isset($frame_display->exam_percent) ? (int) $frame_display->exam_percent : null;
        $int_pct = isset($frame_display->interview_percent) ? (int) $frame_display->interview_percent : null;
        $parts = [];
        if ($exam_pct !== null && $int_pct !== null) {
            $parts[] = oralinterview_format_exam_frame_weights($exam_pct, $int_pct);
        }
        $sel = isset($frame_display->interview_selection) ? strtolower((string) $frame_display->interview_selection) : '';
        $top_n = isset($frame_display->interview_top_n) ? (int) $frame_display->interview_top_n : 0;
        if ($sel === 'top' && $top_n > 0) {
            $parts[] = oralinterview_format_exam_frame_top_n($top_n);
        }
        if (!empty($parts)) {
            echo '<div class="small text-muted mt-1">' . s(implode(' | ', $parts)) . '</div>';
        }
    }
    echo '</div>';
    echo '<div class="d-flex gap-2">';
    echo '<a class="btn btn-outline-secondary" href="' . (new moodle_url('/interviews/index.php'))->out() . '">رجوع</a>';
    echo '</div>';
    echo '</div>';

    // Tabs (security: committee should only see "Manage Sessions"; managers see everything).
    echo '<ul class="nav nav-tabs mb-4">';
    $context = context_module::instance($cm->id);
    $can_manage = has_capability('mod/oralinterview:manage', $context);
    if ($can_manage) {
        $tabs = [
            'manage' => ['label' => get_string('session_manage', 'oralinterview'), 'url' => $base['manage']],
            'schedule' => ['label' => 'جدولة الجلسات', 'url' => $base['schedule']],
            'committee' => ['label' => get_string('committee_assign', 'oralinterview'), 'url' => $base['committee']],
            'candidates' => ['label' => get_string('candidates', 'oralinterview'), 'url' => $base['candidates']],
            'questions' => ['label' => get_string('interview_questions', 'oralinterview'), 'url' => $base['questions']],
        ];
    } else {
        // Committee members (evaluate-only): keep them on Manage Sessions only.
        $tabs = [
            'manage' => ['label' => get_string('session_manage', 'oralinterview'), 'url' => $base['manage']],
        ];
        $active = 'manage';
    }
    foreach ($tabs as $key => $t) {
        $cls = ($key === $active) ? 'nav-link active' : 'nav-link';
        echo '<li class="nav-item"><a class="' . $cls . '" href="' . $t['url']->out() . '">' . s($t['label']) . '</a></li>';
    }
    echo '</ul>';

    // Minimal styling tweaks to make Moodle forms/tables look like the SPAC site.
    echo '<style>
      .mform .fitem { margin-bottom: 1rem; }
      .mform .fitemtitle { font-weight: 600; }
      .mform .fitem .felement { max-width: 100%; }
      .mform select, .mform input[type="text"], .mform input[type="number"], .mform textarea { width: 100%; }
      .table th { white-space: nowrap; }
      .nav-tabs .nav-link { padding: .75rem 1rem; }
    </style>';
}

/**
 * Close container and render footer.
 */
function oralinterview_spacui_footer(): void {
    global $CFG;
    echo '</div>';
    require_once($CFG->dirroot . '/includes/footer_exam.php');
}

