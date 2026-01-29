<?php
// ============================================
// Self Assessment - Hook Registration (Moodle 4.3+)
// ============================================
// Registra i callback per i nuovi hook di Moodle
// ============================================

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => core\hook\output\before_standard_head_html_generation::class,
        'callback' => 'local_selfassessment\hook_callbacks::before_standard_head_html_generation',
        'priority' => 500,
    ],
];
