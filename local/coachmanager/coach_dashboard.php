<?php
// ============================================
// CoachManager - Dashboard Coach Integrata
// ============================================
// Vista unificata con:
// - Filtri avanzati (corso, colore, settimana, stato)
// - Calendario espandibile (settimana/mese)
// - Card studenti collassabili
// - Alert fine 6 settimane
// - Integrazione competenze, autovalutazioni, laboratori
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('classes/dashboard_helper.php');

require_login();
$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);
$colorfilter = optional_param('color', '', PARAM_ALPHA);
$weekfilter = optional_param('week', 0, PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_TEXT);

// Setup pagina
$PAGE->set_url(new moodle_url('/local/coachmanager/coach_dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coach_dashboard', 'local_coachmanager'));
$PAGE->set_heading(get_string('coach_dashboard', 'local_coachmanager'));
$PAGE->set_pagelayout('report');

// Carica dati
$dashboard = new \local_coachmanager\dashboard_helper($USER->id);
$students = $dashboard->get_my_students($courseid, $colorfilter, $weekfilter, $statusfilter, $search);
$courses = $dashboard->get_coach_courses();
$groups = $dashboard->get_color_groups();
$stats = $dashboard->get_dashboard_stats($students);
$end6weeks = $dashboard->get_students_end_6_weeks($students);
$calendar_data = $dashboard->get_calendar_data(date('Y'), date('m'));

echo $OUTPUT->header();

// Include CSS
require_once('styles/dashboard.css.php');
?>

<div class="coach-dashboard">

    <!-- Header con titolo e azioni -->
    <div class="dashboard-header">
        <div class="header-left">
            <h2><?php echo get_string('my_students', 'local_coachmanager'); ?></h2>
            <span class="student-count"><?php echo count($students); ?> studenti</span>
        </div>
        <div class="header-actions">
            <button class="btn btn-warning" onclick="openQuickChoices()">
                <?php echo get_string('quick_choices', 'local_coachmanager'); ?>
            </button>
            <button class="btn btn-primary" onclick="location.href='reports_class.php'">
                <?php echo get_string('class_report', 'local_coachmanager'); ?>
            </button>
        </div>
    </div>

    <!-- Sezione Filtri Avanzati -->
    <div class="filters-section" id="filtersSection">
        <div class="filters-header" onclick="toggleFilters()">
            <h4>
                <span class="filter-icon">&#9776;</span>
                <?php echo get_string('advanced_filters', 'local_coachmanager'); ?>
            </h4>
            <div class="filters-toggle">&#9660;</div>
        </div>
        <div class="filters-body">
            <form method="get" action="" id="filterForm">
                <div class="filter-group">
                    <label><?php echo get_string('course', 'local_coachmanager'); ?></label>
                    <select name="courseid" onchange="this.form.submit()">
                        <option value=""><?php echo get_string('all_courses', 'local_coachmanager'); ?></option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course->id; ?>" <?php echo $courseid == $course->id ? 'selected' : ''; ?>>
                            <?php echo format_string($course->fullname); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo get_string('group_color', 'local_coachmanager'); ?></label>
                    <div class="color-chips">
                        <?php
                        $colors = ['giallo' => '#FFFF00', 'blu' => '#0066cc', 'verde' => '#28a745',
                                   'arancione' => '#fd7e14', 'rosso' => '#dc3545', 'viola' => '#7030A0', 'grigio' => '#808080'];
                        foreach ($colors as $name => $hex):
                        ?>
                        <div class="color-chip <?php echo $name; ?> <?php echo $colorfilter == $name ? 'selected' : ''; ?>"
                             onclick="setColorFilter('<?php echo $name; ?>')"
                             title="<?php echo ucfirst($name); ?>"
                             style="background: <?php echo $hex; ?>;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="color" id="colorFilter" value="<?php echo s($colorfilter); ?>">
                </div>

                <div class="filter-group">
                    <label><?php echo get_string('week', 'local_coachmanager'); ?></label>
                    <select name="week" onchange="this.form.submit()">
                        <option value=""><?php echo get_string('all_weeks', 'local_coachmanager'); ?></option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $weekfilter == $i ? 'selected' : ''; ?>>
                            <?php echo get_string('week', 'local_coachmanager') . ' ' . $i; ?>
                            <?php echo $i == 6 ? ' (' . get_string('end', 'local_coachmanager') . ')' : ''; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo get_string('status', 'local_coachmanager'); ?></label>
                    <select name="status" onchange="this.form.submit()">
                        <option value=""><?php echo get_string('all_statuses', 'local_coachmanager'); ?></option>
                        <option value="end6" <?php echo $statusfilter == 'end6' ? 'selected' : ''; ?>><?php echo get_string('end_6_weeks', 'local_coachmanager'); ?></option>
                        <option value="below50" <?php echo $statusfilter == 'below50' ? 'selected' : ''; ?>><?php echo get_string('below_threshold', 'local_coachmanager'); ?></option>
                        <option value="no_autoval" <?php echo $statusfilter == 'no_autoval' ? 'selected' : ''; ?>><?php echo get_string('missing_autoval', 'local_coachmanager'); ?></option>
                        <option value="no_lab" <?php echo $statusfilter == 'no_lab' ? 'selected' : ''; ?>><?php echo get_string('missing_lab', 'local_coachmanager'); ?></option>
                        <option value="no_choices" <?php echo $statusfilter == 'no_choices' ? 'selected' : ''; ?>><?php echo get_string('missing_choices', 'local_coachmanager'); ?></option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Alert Fine 6 Settimane -->
    <?php if (!empty($end6weeks)): ?>
    <div class="alert-6-weeks">
        <div class="alert-6-weeks-icon">!</div>
        <div class="alert-6-weeks-content">
            <h4><?php echo get_string('students_end_path', 'local_coachmanager'); ?></h4>
            <p><?php echo count($end6weeks); ?> <?php echo get_string('students_completing', 'local_coachmanager'); ?></p>
        </div>
        <div class="alert-6-weeks-students">
            <?php foreach (array_slice($end6weeks, 0, 3) as $student): ?>
            <span class="student-mini-badge"><?php echo fullname($student); ?></span>
            <?php endforeach; ?>
            <?php if (count($end6weeks) > 3): ?>
            <span class="student-mini-badge">+<?php echo count($end6weeks) - 3; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Deadline Alert -->
    <?php
    $deadline = $dashboard->get_next_deadline();
    if ($deadline):
    ?>
    <div class="deadline-box">
        <div class="deadline-icon">!</div>
        <div class="deadline-content">
            <h3>DEADLINE: <?php echo $deadline->title; ?></h3>
            <p><?php echo $deadline->description; ?></p>
        </div>
        <div class="deadline-timer">
            <div class="time"><?php echo $deadline->days_remaining; ?> <?php echo get_string('days', 'local_coachmanager'); ?></div>
            <div class="label"><?php echo get_string('remaining', 'local_coachmanager'); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card violet">
            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
            <div class="stat-label"><?php echo get_string('assigned_students', 'local_coachmanager'); ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number"><?php echo $stats['avg_competency']; ?>%</div>
            <div class="stat-label"><?php echo get_string('avg_competencies', 'local_coachmanager'); ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['autoval_complete']; ?>/<?php echo $stats['total_students']; ?></div>
            <div class="stat-label"><?php echo get_string('autoval_complete', 'local_coachmanager'); ?></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['lab_evaluated']; ?>/<?php echo $stats['total_students']; ?></div>
            <div class="stat-label"><?php echo get_string('lab_evaluated', 'local_coachmanager'); ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['end_6_weeks']; ?></div>
            <div class="stat-label"><?php echo get_string('end_6_weeks', 'local_coachmanager'); ?></div>
        </div>
    </div>

    <!-- Calendario Espandibile -->
    <div class="calendar-container" id="calendarContainer">
        <div class="calendar-header" onclick="toggleCalendar()">
            <h4>
                <span id="calendarTitle"><?php echo date('F Y'); ?></span>
                <span class="week-indicator current">KW<?php echo date('W'); ?></span>
            </h4>
            <div class="calendar-header-right">
                <div class="calendar-nav">
                    <button onclick="event.stopPropagation(); changeMonth(-1);">&#8249;</button>
                    <button onclick="event.stopPropagation(); changeMonth(1);">&#8250;</button>
                </div>
                <div class="calendar-expand-toggle">
                    <span><?php echo get_string('view_month', 'local_coachmanager'); ?></span>
                    <span class="arrow">&#9660;</span>
                </div>
            </div>
        </div>
        <div class="calendar-body">
            <!-- Vista Settimana -->
            <div class="calendar-week-view" id="weekView">
                <?php echo $dashboard->render_week_view($calendar_data); ?>
            </div>
            <!-- Vista Mese -->
            <div class="calendar-month-view" id="monthView">
                <?php echo $dashboard->render_month_view($calendar_data); ?>
            </div>
        </div>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <button class="quick-filter <?php echo empty($statusfilter) ? 'active' : ''; ?>"
                onclick="location.href='?'">
            <?php echo get_string('all', 'local_coachmanager'); ?> (<?php echo $stats['total_students']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_choices' ? 'active' : ''; ?>"
                onclick="location.href='?status=no_choices'">
            <?php echo get_string('missing_choices', 'local_coachmanager'); ?> (<?php echo $stats['missing_choices']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_autoval' ? 'active' : ''; ?>"
                onclick="location.href='?status=no_autoval'">
            <?php echo get_string('missing_autoval', 'local_coachmanager'); ?> (<?php echo $stats['missing_autoval']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_lab' ? 'active' : ''; ?>"
                onclick="location.href='?status=no_lab'">
            <?php echo get_string('missing_lab', 'local_coachmanager'); ?> (<?php echo $stats['missing_lab']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'below50' ? 'active' : ''; ?>"
                onclick="location.href='?status=below50'">
            <?php echo get_string('below_threshold', 'local_coachmanager'); ?> (<?php echo $stats['below_threshold']; ?>)
        </button>
        <button class="quick-filter end6 <?php echo $statusfilter == 'end6' ? 'active' : ''; ?>"
                onclick="location.href='?status=end6'">
            <?php echo get_string('end_6_weeks', 'local_coachmanager'); ?> (<?php echo $stats['end_6_weeks']; ?>)
        </button>
    </div>

    <!-- Expand/Collapse All -->
    <div class="expand-collapse-all">
        <button class="expand-collapse-btn" onclick="expandAllCards()">
            <span>&#9660;</span> <?php echo get_string('expand_all', 'local_coachmanager'); ?>
        </button>
        <button class="expand-collapse-btn" onclick="collapseAllCards()">
            <span>&#9650;</span> <?php echo get_string('collapse_all', 'local_coachmanager'); ?>
        </button>
    </div>

    <!-- Students Grid -->
    <div class="students-grid" id="studentsGrid">
        <?php
        if (empty($students)):
        ?>
        <div class="no-students">
            <div class="icon">&#128101;</div>
            <h3><?php echo get_string('no_students_found', 'local_coachmanager'); ?></h3>
            <p><?php echo get_string('adjust_filters', 'local_coachmanager'); ?></p>
        </div>
        <?php
        else:
            foreach ($students as $student):
                $is_end6 = $student->current_week >= 6;
                $is_below = $student->competency_avg < 50;
                $card_class = $is_end6 ? 'end-6-weeks' : ($is_below ? 'alert-border' : '');
                $header_class = $student->group_color ?? 'giallo';
        ?>
        <div class="student-card <?php echo $card_class; ?>"
             id="student-<?php echo $student->id; ?>"
             data-color="<?php echo $header_class; ?>"
             data-corso="<?php echo $student->course_shortname ?? ''; ?>">

            <div class="student-card-header <?php echo $header_class; ?>" onclick="toggleCard('student-<?php echo $student->id; ?>')">
                <div class="student-info-left">
                    <div class="collapse-toggle">&#9660;</div>
                    <div>
                        <div class="student-name"><?php echo fullname($student); ?></div>
                        <div class="student-email"><?php echo $student->email; ?></div>
                    </div>
                </div>
                <div class="student-badges">
                    <?php if ($is_end6): ?>
                    <span class="badge-6-weeks">FINE 6 SETT.</span>
                    <?php endif; ?>
                    <?php if ($is_below): ?>
                    <span class="badge-below">SOTTO SOGLIA</span>
                    <?php endif; ?>
                    <span class="settore-badge"><?php echo strtoupper($student->sector ?? 'N/D'); ?></span>
                    <span class="week-badge <?php echo $header_class; ?>">Sett. <?php echo $student->current_week ?? 1; ?></span>
                </div>
            </div>

            <div class="student-card-collapsible">
                <div class="student-card-body">
                    <!-- Progress Section -->
                    <div class="progress-section">
                        <div class="progress-item competenze <?php echo $is_below ? 'danger' : ''; ?>">
                            <div class="value"><?php echo round($student->competency_avg ?? 0); ?>%</div>
                            <div class="label"><?php echo get_string('competencies', 'local_coachmanager'); ?></div>
                            <div class="mini-progress">
                                <div class="mini-progress-fill <?php echo $is_below ? 'red' : 'violet'; ?>"
                                     style="width: <?php echo $student->competency_avg ?? 0; ?>%;"></div>
                            </div>
                        </div>
                        <div class="progress-item autoval">
                            <div class="value"><?php echo $student->autoval_avg !== null ? number_format($student->autoval_avg, 1) : '--'; ?></div>
                            <div class="label"><?php echo get_string('autoval', 'local_coachmanager'); ?></div>
                            <div class="mini-progress">
                                <div class="mini-progress-fill teal"
                                     style="width: <?php echo ($student->autoval_avg ?? 0) * 20; ?>%;"></div>
                            </div>
                        </div>
                        <div class="progress-item lab">
                            <div class="value"><?php echo $student->lab_avg !== null ? number_format($student->lab_avg, 1) : '--'; ?></div>
                            <div class="label"><?php echo get_string('laboratory', 'local_coachmanager'); ?></div>
                            <div class="mini-progress">
                                <div class="mini-progress-fill orange"
                                     style="width: <?php echo ($student->lab_avg ?? 0) * 20; ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Row -->
                    <div class="status-row">
                        <?php if ($student->quiz_done): ?>
                        <span class="status-badge done"><?php echo get_string('quiz_done', 'local_coachmanager'); ?></span>
                        <?php else: ?>
                        <span class="status-badge missing"><?php echo get_string('quiz_missing', 'local_coachmanager'); ?></span>
                        <?php endif; ?>

                        <?php if ($student->autoval_done): ?>
                        <span class="status-badge done"><?php echo get_string('autoval_done', 'local_coachmanager'); ?></span>
                        <?php else: ?>
                        <span class="status-badge missing"><?php echo get_string('autoval_missing', 'local_coachmanager'); ?></span>
                        <?php endif; ?>

                        <?php if ($student->lab_done): ?>
                        <span class="status-badge done"><?php echo get_string('lab_done', 'local_coachmanager'); ?></span>
                        <?php elseif ($student->lab_pending): ?>
                        <span class="status-badge pending"><?php echo get_string('lab_pending', 'local_coachmanager'); ?></span>
                        <?php else: ?>
                        <span class="status-badge missing"><?php echo get_string('lab_missing', 'local_coachmanager'); ?></span>
                        <?php endif; ?>

                        <?php if ($is_end6): ?>
                        <span class="status-badge end-path"><?php echo get_string('end_path', 'local_coachmanager'); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Week Choices -->
                    <?php if (!$is_end6 && $student->needs_choices): ?>
                    <div class="week-choices <?php echo $header_class; ?>">
                        <h4><?php echo get_string('week_choices', 'local_coachmanager'); ?> <?php echo ($student->current_week ?? 1) + 1; ?></h4>
                        <div class="choice-row">
                            <span class="choice-label"><?php echo get_string('theory_test', 'local_coachmanager'); ?>:</span>
                            <select class="choice-select"
                                    name="choice_test_<?php echo $student->id; ?>"
                                    data-studentid="<?php echo $student->id; ?>"
                                    data-type="test">
                                <option value="">-- <?php echo get_string('select_test', 'local_coachmanager'); ?> --</option>
                                <?php foreach ($dashboard->get_available_tests($student->sector) as $test): ?>
                                <option value="<?php echo $test->id; ?>"><?php echo $test->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="choice-row">
                            <span class="choice-label"><?php echo get_string('lab_practice', 'local_coachmanager'); ?>:</span>
                            <select class="choice-select"
                                    name="choice_lab_<?php echo $student->id; ?>"
                                    data-studentid="<?php echo $student->id; ?>"
                                    data-type="lab">
                                <option value="">-- <?php echo get_string('select_lab', 'local_coachmanager'); ?> --</option>
                                <?php foreach ($dashboard->get_available_labs($student->sector) as $lab): ?>
                                <option value="<?php echo $lab->id; ?>"><?php echo $lab->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php elseif ($is_end6): ?>
                    <div class="end-path-box">
                        <h4><?php echo get_string('final_report_needed', 'local_coachmanager'); ?></h4>
                        <p><?php echo get_string('student_completed_6_weeks', 'local_coachmanager'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="student-card-footer">
                    <button class="btn btn-secondary btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                        <?php echo get_string('report', 'local_coachmanager'); ?>
                    </button>
                    <?php if ($is_end6): ?>
                    <button class="btn btn-warning btn-sm"
                            onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>&final=1'">
                        <?php echo get_string('final_report', 'local_coachmanager'); ?>
                    </button>
                    <?php elseif (!$student->autoval_done): ?>
                    <button class="btn btn-warning btn-sm" onclick="sendReminder(<?php echo $student->id; ?>, 'autoval')">
                        <?php echo get_string('remind_autoval', 'local_coachmanager'); ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm"
                            onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>'">
                        <?php echo get_string('interview', 'local_coachmanager'); ?>
                    </button>
                    <?php if ($student->needs_choices): ?>
                    <button class="btn btn-success btn-sm" onclick="saveChoices(<?php echo $student->id; ?>)">
                        <?php echo get_string('save_choices', 'local_coachmanager'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <!-- Sidebar Groups (Mobile) -->
    <div class="sidebar-groups-mobile" id="sidebarMobile">
        <div class="sidebar-title"><?php echo get_string('my_groups', 'local_coachmanager'); ?></div>
        <?php foreach ($groups as $group): ?>
        <div class="gruppo-chip <?php echo $group->color; ?>" onclick="filterByColor('<?php echo $group->color; ?>')">
            <span><?php echo $group->name; ?></span>
            <span class="week">Sett. <?php echo $group->current_week; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Include JavaScript -->
<script>
// Toggle card collapse
function toggleCard(cardId) {
    document.getElementById(cardId).classList.toggle('collapsed');
}

// Expand all cards
function expandAllCards() {
    document.querySelectorAll('.student-card').forEach(card => {
        card.classList.remove('collapsed');
    });
}

// Collapse all cards
function collapseAllCards() {
    document.querySelectorAll('.student-card').forEach(card => {
        if (card.querySelector('.student-card-collapsible')) {
            card.classList.add('collapsed');
        }
    });
}

// Toggle filters section
function toggleFilters() {
    document.getElementById('filtersSection').classList.toggle('collapsed');
}

// Toggle calendar view
function toggleCalendar() {
    const container = document.getElementById('calendarContainer');
    container.classList.toggle('expanded');
}

// Set color filter
function setColorFilter(color) {
    const chips = document.querySelectorAll('.color-chip');
    chips.forEach(chip => chip.classList.remove('selected'));

    const input = document.getElementById('colorFilter');
    if (input.value === color) {
        input.value = '';
    } else {
        document.querySelector('.color-chip.' + color).classList.add('selected');
        input.value = color;
    }
    document.getElementById('filterForm').submit();
}

// Filter by color from sidebar
function filterByColor(color) {
    setColorFilter(color);
}

// Change month in calendar
function changeMonth(delta) {
    // AJAX call to update calendar
    console.log('Change month by', delta);
}

// Open quick choices modal
function openQuickChoices() {
    // TODO: Implement modal
    alert('<?php echo get_string('quick_choices_coming', 'local_coachmanager'); ?>');
}

// Send reminder
function sendReminder(studentId, type) {
    if (confirm('<?php echo get_string('confirm_send_reminder', 'local_coachmanager'); ?>')) {
        // AJAX call
        fetch('ajax_send_reminder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'studentid=' + studentId + '&type=' + type + '&sesskey=<?php echo sesskey(); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('<?php echo get_string('reminder_sent', 'local_coachmanager'); ?>');
            } else {
                alert('<?php echo get_string('error', 'local_coachmanager'); ?>: ' + data.message);
            }
        });
    }
}

// Save choices for a student
function saveChoices(studentId) {
    const testSelect = document.querySelector('select[data-studentid="' + studentId + '"][data-type="test"]');
    const labSelect = document.querySelector('select[data-studentid="' + studentId + '"][data-type="lab"]');

    const testId = testSelect ? testSelect.value : '';
    const labId = labSelect ? labSelect.value : '';

    if (!testId && !labId) {
        alert('<?php echo get_string('select_at_least_one', 'local_coachmanager'); ?>');
        return;
    }

    fetch('ajax_save_choices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'studentid=' + studentId + '&testid=' + testId + '&labid=' + labId + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('<?php echo get_string('choices_saved', 'local_coachmanager'); ?>');
            location.reload();
        } else {
            alert('<?php echo get_string('error', 'local_coachmanager'); ?>: ' + data.message);
        }
    });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-collapse cards on mobile
    if (window.innerWidth < 768) {
        collapseAllCards();
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
