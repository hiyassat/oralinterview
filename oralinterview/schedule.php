<?php
/**
 * جدولة الجلسات – Session scheduling tab.
 *
 * @package   mod_oralinterview
 */

require_once('../../config.php');
require_once(__DIR__ . '/spacui.php');
require_once(__DIR__ . '/lib_schedule.php');
oralinterview_spacui_bootstrap();
oralinterview_spacui_autoforce();

global $DB, $PAGE, $OUTPUT, $USER, $SESSION;

$id = required_param('id', PARAM_INT);
$spacui = optional_param('spacui', 1, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$cm = get_coursemodule_from_id('oralinterview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$can_manage = has_capability('mod/oralinterview:manage', $context);
if (!$can_manage) {
    throw new moodle_exception('nopermission', 'error');
}

$oralinterview = $DB->get_record('oralinterview', ['id' => $cm->instance], '*', MUST_EXIST);
$oralinterviewid = (int) $oralinterview->id;

// Ensure rooms exist.
oralinterview_schedule_ensure_rooms($oralinterviewid);
$candidate_count = oralinterview_schedule_count_candidates($oralinterviewid);

// Actions (GET delete exception so we don't nest forms)
$action_get = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($action_get === 'delete_exception') {
    require_sesskey();
    $exid = (int) optional_param('exception_id', 0, PARAM_INT);
    if ($exid) {
        $DB->delete_records('oralint_schedule_exceptions', ['id' => $exid, 'oralinterviewid' => $oralinterviewid]);
        $SESSION->oralinterview_success = 'تم حذف الوقت المستثنى.';
    }
    redirect(new moodle_url('/mod/oralinterview/schedule.php', ['id' => $cm->id, 'spacui' => 1]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    if (!empty($_POST['action_save_config'])) {
        $num_rooms = (int) ($_POST['num_rooms'] ?? 1);
        $start_time = trim((string) ($_POST['start_time'] ?? '09:00'));
        $end_time = trim((string) ($_POST['end_time'] ?? '17:00'));
        $slot_minutes = (int) ($_POST['slot_duration_minutes'] ?? 20);
        $dates = optional_param_array('selected_dates', [], PARAM_TEXT);
        $dates = array_values(array_unique(array_filter(array_map('trim', $dates))));
        $now = time();
        $config = $DB->get_record('oralint_schedule_config', ['oralinterviewid' => $oralinterviewid]);
        if (!$config) {
            $DB->insert_record('oralint_schedule_config', (object)[
                'oralinterviewid'      => $oralinterviewid,
                'num_rooms'            => max(1, min(3, $num_rooms)),
                'start_time'           => preg_match('/^\d{1,2}:\d{2}$/', $start_time) ? $start_time : '09:00',
                'end_time'             => preg_match('/^\d{1,2}:\d{2}$/', $end_time) ? $end_time : '17:00',
                'slot_duration_minutes'=> max(5, min(120, $slot_minutes)),
                'selected_dates'       => json_encode(array_values($dates)),
                'timecreated'          => $now,
                'timemodified'         => $now,
                'usermodified'         => $USER->id,
            ]);
        } else {
            $config->num_rooms = max(1, min(3, $num_rooms));
            $config->start_time = preg_match('/^\d{1,2}:\d{2}$/', $start_time) ? $start_time : $config->start_time;
            $config->end_time = preg_match('/^\d{1,2}:\d{2}$/', $end_time) ? $end_time : $config->end_time;
            $config->slot_duration_minutes = max(5, min(120, $slot_minutes));
            $config->selected_dates = json_encode(array_values($dates));
            $config->timemodified = $now;
            $config->usermodified = $USER->id;
            $DB->update_record('oralint_schedule_config', $config);
        }
        $SESSION->oralinterview_success = 'تم حفظ إعدادات الجدولة.';
    }
    if (!empty($_POST['action_add_exception'])) {
        $ex_date = trim((string) ($_POST['exception_date'] ?? ''));
        $ex_start = trim((string) ($_POST['exception_start'] ?? ''));
        $ex_end = trim((string) ($_POST['exception_end'] ?? ''));
        $ex_reason = trim((string) ($_POST['exception_reason'] ?? 'استراحة'));
        if ($ex_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ex_date)) {
            if ($ex_start === '' || $ex_end === '' || !preg_match('/^\d{1,2}:\d{2}$/', $ex_start) || !preg_match('/^\d{1,2}:\d{2}$/', $ex_end)) {
                $ex_start = '00:00';
                $ex_end = '23:59';
            }
            $now = time();
            $DB->insert_record('oralint_schedule_exceptions', (object)[
                'oralinterviewid' => $oralinterviewid,
                'exception_date'  => $ex_date,
                'start_time'      => $ex_start,
                'end_time'        => $ex_end,
                'reason'          => $ex_reason,
                'timecreated'     => $now,
                'timemodified'    => $now,
            ]);
            $SESSION->oralinterview_success = 'تمت إضافة وقت استثناء.';
        }
    }
    if (!empty($_POST['action_auto_assign'])) {
        $num_rooms = (int) ($_POST['num_rooms'] ?? 1);
        $start_time = trim((string) ($_POST['start_time'] ?? '09:00'));
        $end_time = trim((string) ($_POST['end_time'] ?? '17:00'));
        $slot_minutes = (int) ($_POST['slot_duration_minutes'] ?? 20);
        $dates = optional_param_array('selected_dates', [], PARAM_TEXT);
        $dates = array_values(array_unique(array_filter(array_map('trim', $dates))));
        if (empty($dates)) {
            $SESSION->oralinterview_error = 'يجب اختيار تاريخ واحد على الأقل في قسم «تواريخ المقابلات» ثم النقر على «إضافة تاريخ».';
        } else {
            $DB->delete_records('oralint_schedule_assignment', ['oralinterviewid' => $oralinterviewid]);
            $res = oralinterview_schedule_auto_assign(
                $oralinterviewid,
                max(1, min(3, $num_rooms)),
                array_values($dates),
                preg_match('/^\d{1,2}:\d{2}$/', $start_time) ? $start_time : '09:00',
                preg_match('/^\d{1,2}:\d{2}$/', $end_time) ? $end_time : '17:00',
                max(5, min(120, $slot_minutes))
            );
            if (!empty($res['errors'])) {
                $SESSION->oralinterview_error = implode(' ', $res['errors']);
            } else {
                $SESSION->oralinterview_success = 'تم تعيين ' . count($res['assignments']) . ' مرشحاً بنجاح.';
            }
        }
    }
    if (!empty($_POST['action_clear_assignments'])) {
        $DB->delete_records('oralint_schedule_assignment', ['oralinterviewid' => $oralinterviewid]);
        $SESSION->oralinterview_success = 'تم مسح جميع التعيينات.';
    }
    redirect(new moodle_url('/mod/oralinterview/schedule.php', ['id' => $cm->id, 'spacui' => 1]));
}

$config = $DB->get_record('oralint_schedule_config', ['oralinterviewid' => $oralinterviewid]);
$exceptions = $DB->get_records('oralint_schedule_exceptions', ['oralinterviewid' => $oralinterviewid], 'exception_date ASC, start_time ASC');
$assignments = $DB->get_records('oralint_schedule_assignment', ['oralinterviewid' => $oralinterviewid], 'session_date ASC, slot_start_time ASC');
$today = date('Y-m-d');

// Build upcoming dates for big-card selection (like plan_form / exam frame).
$UI_DAYS_AHEAD = 60;
$ui_dates = [];
$base_ts = strtotime($today);
for ($i = 0; $i < $UI_DAYS_AHEAD; $i++) {
    $ui_dates[] = date('Y-m-d', strtotime("+$i day", $base_ts));
}
$saved_dates = [];
if ($config && !empty($config->selected_dates)) {
    $decoded = json_decode($config->selected_dates, true);
    $saved_dates = is_array($decoded) ? $decoded : [];
}

$PAGE->set_url('/mod/oralinterview/schedule.php', ['id' => $cm->id, 'spacui' => $spacui]);
$PAGE->set_title('جدولة الجلسات');
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

oralinterview_spacui_header($cm, 'schedule', 'جدولة الجلسات');

if (!empty($SESSION->oralinterview_success)) {
    echo '<div class="alert alert-success">' . s($SESSION->oralinterview_success) . '</div>';
    unset($SESSION->oralinterview_success);
}
if (!empty($SESSION->oralinterview_error)) {
    echo '<div class="alert alert-danger">' . s($SESSION->oralinterview_error) . '</div>';
    unset($SESSION->oralinterview_error);
}

?>
<form method="post" action="" id="schedule-form">
<?php echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">'; ?>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title">عدد المرشحين</h6>
        <input type="text" class="form-control form-control-lg" value="<?php echo (int)$candidate_count; ?>" readonly style="max-width:120px;">
        <small class="text-muted">عدد الجلسات الحالية المرتبطة بهذه المقابلة</small>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">عدد الغرف</h6></div>
    <div class="card-body">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="num_rooms" id="rooms1" value="1" <?php echo ($config && $config->num_rooms == 1) || !$config ? 'checked' : ''; ?>>
            <label class="form-check-label" for="rooms1">غرفة واحدة</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="num_rooms" id="rooms2" value="2" <?php echo $config && $config->num_rooms == 2 ? 'checked' : ''; ?>>
            <label class="form-check-label" for="rooms2">غرفتان</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="num_rooms" id="rooms3" value="3" <?php echo $config && $config->num_rooms == 3 ? 'checked' : ''; ?>>
            <label class="form-check-label" for="rooms3">ثلاث غرف</label>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">تواريخ المقابلات</h6></div>
    <div class="card-body">
        <p class="text-muted small mb-3">اختر التواريخ من اليوم فصاعداً. يمكنك اختيار عدة أيام دفعة واحدة. المواعيد المستثناة ستكون غير متاحة.</p>
        <div class="border rounded p-3 bg-light">
            <div class="fw-bold mb-2">التواريخ القادمة (من <?php echo s($today); ?>)</div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($ui_dates as $d): ?>
                    <?php $checked = in_array($d, $saved_dates, true); ?>
                    <label class="date-pill border rounded px-3 py-2 mb-0 <?php echo $checked ? 'date-pill-selected' : ''; ?>" style="cursor:pointer;">
                        <input type="checkbox" name="selected_dates[]" value="<?php echo s($d); ?>" <?php echo $checked ? 'checked' : ''; ?> class="me-2">
                        <span><?php echo s($d); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">الأوقات المستثنية</h6></div>
    <div class="card-body">
        <p class="text-muted small">فترات الاستراحة أو الأوقات غير المتاحة. التاريخ إلزامي؛ من/إلى اختياري (إن تركت فارغاً يُعتبر اليوم كاملاً).</p>
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-2"><label class="form-label small mb-0">التاريخ</label><input type="date" name="exception_date" id="exception_date" min="<?php echo s($today); ?>" class="form-control"></div>
            <div class="col-md-2"><label class="form-label small mb-0">من</label><select name="exception_start" class="form-select form-control">
                <option value="">— اختياري —</option>
                <?php for ($h = 0; $h < 24; $h++) { for ($m = 0; $m < 60; $m += 15) { $v = sprintf('%02d:%02d', $h, $m); echo '<option value="' . $v . '">' . $v . '</option>'; } } ?>
            </select></div>
            <div class="col-md-2"><label class="form-label small mb-0">إلى</label><select name="exception_end" class="form-select form-control">
                <option value="">— اختياري —</option>
                <?php for ($h = 0; $h < 24; $h++) { for ($m = 0; $m < 60; $m += 15) { $v = sprintf('%02d:%02d', $h, $m); echo '<option value="' . $v . '">' . $v . '</option>'; } } ?>
            </select></div>
            <div class="col-md-2"><label class="form-label small mb-0">سبب (اختياري)</label><input type="text" name="exception_reason" class="form-control" value="استراحة"></div>
            <div class="col-md-2"><button type="submit" name="action_add_exception" value="1" class="btn btn-outline-secondary">إضافة</button></div>
        </div>
        <?php if (!empty($exceptions)): ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($exceptions as $ex): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?php echo s($ex->exception_date); ?> <?php echo s($ex->start_time); ?> – <?php echo s($ex->end_time); ?> <?php echo s($ex->reason); ?></span>
                <a href="<?php echo (new moodle_url('/mod/oralinterview/schedule.php', ['id' => $cm->id, 'action' => 'delete_exception', 'exception_id' => (int)$ex->id, 'sesskey' => sesskey(), 'spacui' => 1]))->out(false); ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف هذا الوقت المستثنى؟');">حذف</a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">أوقات بدء وانتهاء المقابلات</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">وقت البداية</label>
                <input type="time" name="start_time" class="form-control" value="<?php echo s($config ? $config->start_time : '09:00'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">وقت النهاية</label>
                <input type="time" name="end_time" class="form-control" value="<?php echo s($config ? $config->end_time : '17:00'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">مدة كل مقابلة (دقيقة)</label>
                <input type="number" name="slot_duration_minutes" class="form-control" min="5" max="120" value="<?php echo (int)($config ? $config->slot_duration_minutes : 20); ?>">
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <button type="submit" name="action_save_config" value="1" class="btn btn-secondary me-2">حفظ الإعدادات</button>
        <button type="submit" name="action_auto_assign" value="1" class="btn btn-primary">تعيين تلقائي للمرشحين</button>
        <?php if (!empty($assignments)): ?>
        <button type="submit" name="action_clear_assignments" value="1" class="btn btn-outline-danger ms-2" onclick="return confirm('مسح جميع التعيينات؟');">مسح التعيينات</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($assignments)): ?>
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">التعيينات</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>التاريخ</th><th>الغرفة</th><th>من</th><th>إلى</th><th>المرشح</th></tr></thead>
                <tbody>
                <?php
                $room_names = [];
                foreach ($assignments as $a) {
                    if (!isset($room_names[$a->roomid])) {
                        $r = $DB->get_record('oralint_room', ['id' => $a->roomid], 'name', IGNORE_MISSING);
                        $room_names[$a->roomid] = $r ? $r->name : 'غرفة';
                    }
                    $cand = $DB->get_record('oralint_candidate', ['id' => $a->candidateid], 'fullname', IGNORE_MISSING);
                    echo '<tr>';
                    echo '<td>' . s($a->session_date) . '</td>';
                    echo '<td>' . s($room_names[$a->roomid]) . '</td>';
                    echo '<td>' . s($a->slot_start_time) . '</td>';
                    echo '<td>' . s($a->slot_end_time) . '</td>';
                    echo '<td>' . ($cand ? s($cand->fullname) : '-') . '</td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

</form>

<style>
.date-pill { background:#fff; color:#333; transition:background .15s, color .15s; }
.date-pill:hover { background:#e9ecef !important; }
.date-pill.date-pill-selected { background:#0d6efd !important; color:#fff !important; }
</style>
<script>
(function() {
    var pills = document.querySelectorAll('.date-pill input[type="checkbox"]');
    pills.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var lab = cb.closest('.date-pill');
            if (lab) lab.classList.toggle('date-pill-selected', cb.checked);
        });
    });
})();
</script>
<?php

oralinterview_spacui_footer();
