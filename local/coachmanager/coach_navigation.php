<?php
// ============================================
// CoachManager - Reusable Navigation Bar
// ============================================
// Include file per barra navigazione report studente
// ============================================
// Usage:
// $nav_studentid = $userid;
// $nav_studentname = fullname($student);
// $nav_courseid = $courseid;
// $nav_current = 'quiz'; // 'lista', 'quiz', 'autoval', 'lab', 'confronti'
// include(__DIR__ . '/../coachmanager/coach_navigation.php');
// ============================================

defined('MOODLE_INTERNAL') || die();

// Validate required variables
if (!isset($nav_studentid) || !isset($nav_studentname)) {
    debugging('coach_navigation.php requires $nav_studentid and $nav_studentname to be set', DEBUG_DEVELOPER);
    return;
}

// Set defaults
$nav_courseid = isset($nav_courseid) ? $nav_courseid : 0;
$nav_current = isset($nav_current) ? $nav_current : '';

// Build navigation items
$nav_items = [
    'lista' => [
        'label' => 'Lista Studenti',
        'icon' => '&#128101;', // people icon
        'url' => $CFG->wwwroot . '/local/coachmanager/coach_dashboard.php' . ($nav_courseid ? '?courseid=' . $nav_courseid : ''),
    ],
    'quiz' => [
        'label' => 'Quiz',
        'icon' => '&#128221;', // clipboard icon
        'url' => $CFG->wwwroot . '/local/competencymanager/student_report.php?userid=' . $nav_studentid . ($nav_courseid ? '&courseid=' . $nav_courseid : ''),
    ],
    'autoval' => [
        'label' => 'Autovalutazione',
        'icon' => '&#9733;', // star icon
        'url' => $CFG->wwwroot . '/local/coachmanager/reports_v2.php?studentid=' . $nav_studentid . '&tab=autovalutazione',
    ],
    'lab' => [
        'label' => 'Laboratori',
        'icon' => '&#128295;', // wrench icon
        'url' => $CFG->wwwroot . '/local/coachmanager/reports_v2.php?studentid=' . $nav_studentid . '&tab=laboratori',
    ],
    'confronti' => [
        'label' => 'Confronti',
        'icon' => '&#128200;', // chart icon
        'url' => $CFG->wwwroot . '/local/coachmanager/reports_v2.php?studentid=' . $nav_studentid . '&tab=confronti',
    ],
];
?>

<style>
/* =============================================
   COACH NAVIGATION BAR - INLINE CSS
   FTM Color Scheme: #667eea (viola), #764ba2 (viola scuro)
   ============================================= */

.coach-nav-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 0;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    overflow: hidden;
}

.coach-nav-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.coach-nav-student {
    display: flex;
    align-items: center;
    gap: 12px;
}

.coach-nav-student-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.coach-nav-student-name {
    font-size: 18px;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.coach-nav-student-label {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.coach-nav-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.coach-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.coach-nav-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.4);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
}

.coach-nav-btn.active {
    background: white;
    color: #667eea;
    border-color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.coach-nav-btn.active:hover {
    background: white;
    color: #764ba2;
}

.coach-nav-btn-icon {
    font-size: 14px;
    line-height: 1;
}

.coach-nav-btn-print {
    background: rgba(255, 193, 7, 0.9);
    border-color: rgba(255, 193, 7, 1);
    color: #333;
}

.coach-nav-btn-print:hover {
    background: #ffc107;
    border-color: #ffc107;
    color: #333;
}

.coach-nav-separator {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.3);
    margin: 0 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .coach-nav-header {
        flex-direction: column;
        align-items: stretch;
        padding: 15px;
    }

    .coach-nav-student {
        justify-content: center;
        margin-bottom: 10px;
    }

    .coach-nav-buttons {
        justify-content: center;
    }

    .coach-nav-btn {
        padding: 8px 12px;
        font-size: 12px;
    }

    .coach-nav-separator {
        display: none;
    }
}

@media (max-width: 480px) {
    .coach-nav-btn span:not(.coach-nav-btn-icon) {
        display: none;
    }

    .coach-nav-btn {
        padding: 10px 12px;
    }

    .coach-nav-btn-icon {
        font-size: 16px;
    }
}

/* Print styles - hide navigation when printing */
@media print {
    .coach-nav-bar {
        display: none !important;
    }
}
</style>

<div class="coach-nav-bar">
    <div class="coach-nav-header">
        <!-- Student Info -->
        <div class="coach-nav-student">
            <div class="coach-nav-student-icon">&#128100;</div>
            <div>
                <div class="coach-nav-student-label">Report Studente</div>
                <div class="coach-nav-student-name"><?php echo htmlspecialchars($nav_studentname); ?></div>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="coach-nav-buttons">
            <?php foreach ($nav_items as $key => $item): ?>
            <a href="<?php echo $item['url']; ?>"
               class="coach-nav-btn <?php echo ($nav_current === $key) ? 'active' : ''; ?>"
               title="<?php echo htmlspecialchars($item['label']); ?>">
                <span class="coach-nav-btn-icon"><?php echo $item['icon']; ?></span>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <?php endforeach; ?>

            <div class="coach-nav-separator"></div>

            <!-- Print Button -->
            <button type="button"
                    class="coach-nav-btn coach-nav-btn-print"
                    onclick="coachNavPrint()"
                    title="Stampa Report">
                <span class="coach-nav-btn-icon">&#128424;</span>
                <span>Stampa</span>
            </button>
        </div>
    </div>
</div>

<script>
function coachNavPrint() {
    // Check if there's a custom print modal function defined
    if (typeof openPrintModal === 'function') {
        openPrintModal();
    } else if (typeof showPrintDialog === 'function') {
        showPrintDialog();
    } else {
        // Default: trigger browser print
        window.print();
    }
}
</script>
