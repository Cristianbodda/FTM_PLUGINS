<?php
// ============================================
// CoachManager - Dashboard CSS Styles
// ============================================
// Stile armonizzato con student_report.php
// Colori: #667eea (viola), #764ba2 (viola scuro)
// ============================================

defined('MOODLE_INTERNAL') || die();
?>
<style>
/* =============================================
   COACH DASHBOARD - CSS
   ============================================= */

.coach-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* Dashboard Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h2 {
    font-size: 24px;
    color: #333;
    margin: 0;
}

.dashboard-header .student-count {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    margin-left: 10px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

/* Buttons - Stile student_report */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.btn-sm {
    padding: 6px 14px;
    font-size: 13px;
}

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.filters-header h4 {
    font-size: 14px;
    color: #667eea;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.filter-icon {
    font-size: 16px;
}

.filters-toggle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(102, 126, 234, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s;
    font-size: 12px;
    color: #667eea;
}

.filters-section.collapsed .filters-toggle {
    transform: rotate(-90deg);
}

.filters-section.collapsed .filters-body {
    display: none;
}

.filters-body {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #555;
}

.filter-group select {
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 13px;
    transition: all 0.2s;
    background: white;
}

.filter-group select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

/* Color Chips */
.color-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.color-chip {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.2s;
}

.color-chip:hover {
    transform: scale(1.15);
}

.color-chip.selected {
    border-color: #333;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

/* Alert 6 Weeks */
.alert-6-weeks {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border: 2px solid #ffc107;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.alert-6-weeks-icon {
    font-size: 24px;
    background: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #856404;
}

.alert-6-weeks-content {
    flex: 1;
    min-width: 200px;
}

.alert-6-weeks-content h4 {
    color: #856404;
    font-size: 15px;
    margin: 0 0 5px 0;
}

.alert-6-weeks-content p {
    color: #856404;
    font-size: 13px;
    margin: 0;
}

.alert-6-weeks-students {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.student-mini-badge {
    background: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #856404;
    border: 1px solid #ffc107;
}

/* Deadline Box */
.deadline-box {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border: 2px solid #dc3545;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.deadline-icon {
    font-size: 32px;
    background: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #dc3545;
}

.deadline-content {
    flex: 1;
    min-width: 200px;
}

.deadline-content h3 {
    color: #721c24;
    margin: 0 0 5px 0;
    font-size: 18px;
}

.deadline-content p {
    color: #721c24;
    font-size: 14px;
    margin: 0;
}

.deadline-timer {
    text-align: center;
    background: white;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.deadline-timer .time {
    font-size: 32px;
    font-weight: 700;
    color: #dc3545;
}

.deadline-timer .label {
    font-size: 12px;
    color: #721c24;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e0e0e0;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card.violet { border-left: 4px solid #667eea; }
.stat-card.violet .stat-number { color: #667eea; }
.stat-card.blue { border-left: 4px solid #4facfe; }
.stat-card.blue .stat-number { color: #4facfe; }
.stat-card.green { border-left: 4px solid #28a745; }
.stat-card.green .stat-number { color: #28a745; }
.stat-card.orange { border-left: 4px solid #fd7e14; }
.stat-card.orange .stat-number { color: #fd7e14; }
.stat-card.red { border-left: 4px solid #dc3545; }
.stat-card.red .stat-number { color: #dc3545; }

.stat-number {
    font-size: 28px;
    font-weight: 700;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

/* Calendar Container */
.calendar-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border-bottom: 1px solid #e0e0e0;
    cursor: pointer;
}

.calendar-header h4 {
    font-size: 14px;
    color: #667eea;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.calendar-header-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.calendar-nav {
    display: flex;
    gap: 5px;
}

.calendar-nav button {
    width: 30px;
    height: 30px;
    border: 1px solid #e0e0e0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
}

.calendar-nav button:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.calendar-expand-toggle {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: white;
    border: 1px solid #667eea;
    border-radius: 20px;
    font-size: 12px;
    color: #667eea;
    cursor: pointer;
    transition: all 0.2s;
}

.calendar-expand-toggle:hover {
    background: #667eea;
    color: white;
}

.calendar-expand-toggle .arrow {
    transition: transform 0.3s;
}

.calendar-container.expanded .calendar-expand-toggle .arrow {
    transform: rotate(180deg);
}

.calendar-body {
    padding: 15px 20px;
}

.week-indicator {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.week-indicator.current {
    background: #667eea;
    color: white;
}

/* Week View */
.calendar-week-view {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}

.calendar-month-view {
    display: none;
}

.calendar-container.expanded .calendar-week-view {
    display: none;
}

.calendar-container.expanded .calendar-month-view {
    display: block;
}

.calendar-day {
    text-align: center;
    padding: 10px 8px;
    border-radius: 8px;
    font-size: 12px;
}

.calendar-day.header {
    font-weight: 600;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    color: #667eea;
}

.calendar-day.giallo {
    background: #FEF9C3;
    border: 1px solid #EAB308;
    color: #92400E;
}

.calendar-day.blu {
    background: #DBEAFE;
    border: 1px solid #3B82F6;
    color: #1E40AF;
}

.calendar-day.verde {
    background: #D1FAE5;
    border: 1px solid #10B981;
    color: #065F46;
}

.calendar-day.empty {
    background: #f8f9fa;
    color: #999;
}

/* Month Grid */
.month-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}

.month-day {
    text-align: center;
    padding: 8px 4px;
    border-radius: 6px;
    font-size: 11px;
    min-height: 60px;
    border: 1px solid #e0e0e0;
    background: white;
}

.month-day.header {
    background: #f8f9fa;
    font-weight: 600;
    color: #666;
    min-height: auto;
    border: none;
}

.month-day .day-number {
    font-weight: 600;
    margin-bottom: 4px;
}

.month-day .day-event {
    font-size: 9px;
    padding: 2px 4px;
    border-radius: 3px;
    margin-top: 2px;
}

.month-day.giallo { background: #FEF9C3; border-color: #EAB308; }
.month-day.giallo .day-event { background: #EAB308; color: #333; }
.month-day.blu { background: #DBEAFE; border-color: #3B82F6; }
.month-day.blu .day-event { background: #3B82F6; color: white; }
.month-day.verde { background: #D1FAE5; border-color: #10B981; }
.month-day.verde .day-event { background: #10B981; color: white; }
.month-day.weekend { background: #f0f0f0; color: #999; }
.month-day.today { border: 2px solid #667eea; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); }

/* Quick Filters */
.quick-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.quick-filter {
    padding: 8px 16px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.quick-filter:hover {
    border-color: #667eea;
    color: #667eea;
}

.quick-filter.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.quick-filter.end6 {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.quick-filter.end6.active {
    background: #ffc107;
    color: #333;
}

/* Expand/Collapse */
.expand-collapse-all {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.expand-collapse-btn {
    padding: 8px 16px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.expand-collapse-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

.no-students {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 2px dashed #e0e0e0;
}

.no-students .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.no-students h3 {
    color: #666;
    margin-bottom: 10px;
}

.no-students p {
    color: #999;
}

/* Student Card */
.student-card {
    background: white;
    border-radius: 16px;
    border: 2px solid #e0e0e0;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.student-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.student-card.alert-border {
    border-color: #dc3545;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
}

.student-card.end-6-weeks {
    border-color: #ffc107;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

/* Card Header */
.student-card-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.student-card-header:hover {
    filter: brightness(0.97);
}

.student-card-header.giallo {
    background: linear-gradient(135deg, #FEF9C3 0%, #FDE68A 100%);
    border-left: 4px solid #EAB308;
}

.student-card-header.blu {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    border-left: 4px solid #3B82F6;
}

.student-card-header.verde {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    border-left: 4px solid #10B981;
}

.student-card-header.arancione {
    background: linear-gradient(135deg, #FFEDD5 0%, #FED7AA 100%);
    border-left: 4px solid #F97316;
}

.student-card-header.rosso {
    background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
    border-left: 4px solid #EF4444;
}

.student-card-header.viola {
    background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
    border-left: 4px solid #8B5CF6;
}

.student-card-header.grigio {
    background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
    border-left: 4px solid #6B7280;
}

.student-info-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.collapse-toggle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s;
    font-size: 14px;
}

.student-card.collapsed .collapse-toggle {
    transform: rotate(-90deg);
}

.student-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.student-email {
    font-size: 12px;
    color: #666;
}

.student-badges {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.badge-6-weeks {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #333;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    animation: pulse 2s infinite;
}

.badge-below {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.settore-badge {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.week-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.week-badge.giallo { background: #EAB308; color: #333; }
.week-badge.blu { background: #3B82F6; color: white; }
.week-badge.verde { background: #10B981; color: white; }
.week-badge.arancione { background: #F97316; color: white; }
.week-badge.rosso { background: #EF4444; color: white; }
.week-badge.viola { background: #8B5CF6; color: white; }
.week-badge.grigio { background: #6B7280; color: white; }

/* Collapsible Content */
.student-card-collapsible {
    max-height: 600px;
    overflow: hidden;
    transition: max-height 0.35s ease-out, opacity 0.25s ease;
    opacity: 1;
}

.student-card.collapsed .student-card-collapsible {
    max-height: 0;
    opacity: 0;
}

.student-card-body {
    padding: 20px;
}

/* Progress Section */
.progress-section {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 15px;
}

.progress-item {
    text-align: center;
    padding: 12px 8px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
}

.progress-item .value {
    font-size: 22px;
    font-weight: 700;
}

.progress-item .label {
    font-size: 11px;
    color: #666;
    margin-top: 4px;
}

.progress-item.competenze { border-left: 3px solid #667eea; }
.progress-item.competenze .value { color: #667eea; }
.progress-item.autoval { border-left: 3px solid #20c997; }
.progress-item.autoval .value { color: #20c997; }
.progress-item.lab { border-left: 3px solid #fd7e14; }
.progress-item.lab .value { color: #fd7e14; }
.progress-item.danger { border-left: 3px solid #dc3545; background: #f8d7da; }
.progress-item.danger .value { color: #dc3545; }

.mini-progress {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-top: 6px;
    overflow: hidden;
}

.mini-progress-fill {
    height: 100%;
    border-radius: 3px;
}

.mini-progress-fill.violet { background: linear-gradient(90deg, #667eea, #764ba2); }
.mini-progress-fill.teal { background: linear-gradient(90deg, #11998e, #38ef7d); }
.mini-progress-fill.orange { background: #fd7e14; }
.mini-progress-fill.red { background: #dc3545; }

/* Status Row */
.status-row {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.status-badge.done { background: #d4edda; color: #155724; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.missing { background: #f8d7da; color: #721c24; }
.status-badge.end-path { background: #fff3cd; color: #856404; }

/* Week Choices */
.week-choices {
    margin-top: 15px;
    padding: 15px;
    border-radius: 10px;
}

.week-choices.giallo {
    background: linear-gradient(135deg, #FEF9C3 0%, #FDE68A 100%);
    border: 1px solid #EAB308;
}

.week-choices.blu {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    border: 1px solid #3B82F6;
}

.week-choices.verde {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    border: 1px solid #10B981;
}

.week-choices h4 {
    font-size: 13px;
    margin: 0 0 10px 0;
}

.week-choices.giallo h4 { color: #92400E; }
.week-choices.blu h4 { color: #1E40AF; }
.week-choices.verde h4 { color: #065F46; }

.choice-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    font-size: 13px;
}

.choice-row:last-child {
    border-bottom: none;
}

.choice-label {
    font-weight: 500;
}

.choice-select {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    background: white;
    min-width: 180px;
    border: 1px solid #ccc;
}

.choice-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

/* End Path Box */
.end-path-box {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #ffc107;
    margin-top: 15px;
}

.end-path-box h4 {
    color: #856404;
    font-size: 13px;
    margin: 0 0 8px 0;
}

.end-path-box p {
    font-size: 12px;
    color: #856404;
    margin: 0;
}

/* Card Footer */
.student-card-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
    border-top: 1px solid #e0e0e0;
    flex-wrap: wrap;
}

.student-card-footer .btn {
    flex: 1;
    justify-content: center;
    min-width: 80px;
}

/* Sidebar Groups Mobile */
.sidebar-groups-mobile {
    display: none;
}

/* Gruppo Chip */
.gruppo-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 15px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.gruppo-chip:hover {
    transform: translateX(3px);
}

.gruppo-chip.giallo { background: #FEF9C3; border: 2px solid #EAB308; }
.gruppo-chip.blu { background: #DBEAFE; border: 2px solid #3B82F6; }
.gruppo-chip.verde { background: #D1FAE5; border: 2px solid #10B981; }

.gruppo-chip .week {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: auto;
    font-weight: 600;
}

.gruppo-chip.giallo .week { background: #EAB308; }
.gruppo-chip.blu .week { background: #3B82F6; color: white; }
.gruppo-chip.verde .week { background: #10B981; color: white; }

/* Responsive */
@media (max-width: 1200px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filters-body {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 900px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .students-grid {
        grid-template-columns: 1fr;
    }
    .filters-body {
        grid-template-columns: 1fr;
    }
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .sidebar-groups-mobile {
        display: block;
        margin-top: 20px;
        padding: 20px;
        background: white;
        border-radius: 12px;
    }
    .sidebar-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #764ba2;
        margin-bottom: 10px;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    .student-badges {
        flex-direction: column;
        align-items: flex-end;
    }
    .calendar-week-view {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
