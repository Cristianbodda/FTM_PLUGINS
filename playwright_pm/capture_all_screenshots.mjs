/**
 * FTM Complete Screenshots Capture
 * Cattura TUTTI gli screenshot necessari per i manuali
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
    baseUrl: 'https://test-urc.hizuvala.myhostpoint.ch',
    coach: {
        username: 'roberto.bravo',
        password: '123Roberto*'
    },
    segreteria: {
        username: 'admin_urc_test',
        password: 'Brain666*'
    },
    outputDir: join(__dirname, '..', 'docs', 'manuali', 'screenshots'),
    timeout: 30000
};

let successCount = 0;
let failCount = 0;

async function ensureDir(dir) {
    if (!existsSync(dir)) {
        mkdirSync(dir, { recursive: true });
    }
}

async function login(page, credentials) {
    console.log(`\n🔐 Login come: ${credentials.username}`);
    await page.goto(`${CONFIG.baseUrl}/login/index.php`);
    await page.waitForLoadState('networkidle');
    await page.fill('#username', credentials.username);
    await page.fill('#password', credentials.password);
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');
    console.log('✅ Login riuscito');
}

async function logout(page) {
    await page.goto(`${CONFIG.baseUrl}/login/logout.php`);
    await page.waitForLoadState('networkidle');
}

async function saveScreenshot(page, name, options = {}) {
    const filePath = join(CONFIG.outputDir, `${name}.png`);
    try {
        if (options.selector) {
            const element = await page.$(options.selector);
            if (element) {
                await element.screenshot({ path: filePath });
            } else {
                await page.screenshot({ path: filePath, fullPage: false });
            }
        } else if (options.clip) {
            await page.screenshot({ path: filePath, clip: options.clip });
        } else {
            await page.screenshot({ path: filePath, fullPage: options.fullPage || false });
        }
        console.log(`   ✅ ${name}.png`);
        successCount++;
        return true;
    } catch (error) {
        console.log(`   ❌ ${name}.png - ${error.message}`);
        failCount++;
        return false;
    }
}

async function captureGeneralScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT GENERALI');
    console.log('='.repeat(50));

    // Login page
    await page.goto(`${CONFIG.baseUrl}/login/index.php`);
    await page.waitForLoadState('networkidle');
    await saveScreenshot(page, 'login');

    // Login come coach per vedere menu
    await login(page, CONFIG.coach);

    // Menu navigazione FTM
    await page.goto(`${CONFIG.baseUrl}/my/`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Apri menu navigazione se necessario
    const navDrawer = await page.$('#nav-drawer');
    if (navDrawer) {
        await saveScreenshot(page, 'menu_ftm', { selector: '#nav-drawer' });
    } else {
        // Screenshot del menu laterale
        await saveScreenshot(page, 'menu_ftm', { selector: '.navbar, nav' });
    }
}

async function captureCoachDashboardScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT DASHBOARD COACH');
    console.log('='.repeat(50));

    await page.goto(`${CONFIG.baseUrl}/local/coachmanager/coach_dashboard_v2.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Dashboard completa
    await saveScreenshot(page, 'dashboard_coach_full', { fullPage: true });

    // Vista principale
    await saveScreenshot(page, 'dashboard_coach');

    // Barra filtri superiore
    const filtersRow = await page.$('.filters-row, .view-controls, .dashboard-filters');
    if (filtersRow) {
        await saveScreenshot(page, 'dashboard_barra', { selector: '.filters-row, .view-controls' });
    }

    // Pulsanti vista (Classica, Compatta, Standard, Dettagliata)
    const viewButtons = await page.$('.view-toggle, .btn-group');
    if (viewButtons) {
        await saveScreenshot(page, 'pulsanti_vista', { selector: '.view-toggle, .btn-group' });
    }

    // Filtro corso - apri dropdown
    const courseFilter = await page.$('select[name="course"], #course-filter, .course-selector');
    if (courseFilter) {
        await courseFilter.click();
        await page.waitForTimeout(500);
        await saveScreenshot(page, 'filtro_corso');
        await page.keyboard.press('Escape');
    }

    // Filtro gruppo colore
    const colorDots = await page.$('.group-color-filter, .color-dots');
    if (colorDots) {
        await saveScreenshot(page, 'filtro_gruppo', { selector: '.group-color-filter, .color-dots' });
    }

    // Zoom controls
    const zoomControls = await page.$('.zoom-controls, .zoom-slider');
    if (zoomControls) {
        await saveScreenshot(page, 'pulsanti_zoom', { selector: '.zoom-controls, .zoom-slider' });
    }

    // Card studente singola
    const studentCard = await page.$('.student-card, .card-student, [class*="student"]');
    if (studentCard) {
        await saveScreenshot(page, 'card_studente', { selector: '.student-card, .card-student' });

        // Hover sulla card per mostrare pulsanti
        await studentCard.hover();
        await page.waitForTimeout(500);
        await saveScreenshot(page, 'card_pulsanti', { selector: '.student-card, .card-student' });
    }

    // Filtri avanzati - espandi se presenti
    const advancedFilters = await page.$('.advanced-filters, #advanced-filters');
    if (advancedFilters) {
        const toggle = await page.$('.advanced-filters-toggle, [data-toggle="advanced"]');
        if (toggle) {
            await toggle.click();
            await page.waitForTimeout(500);
        }
        await saveScreenshot(page, 'filtri_avanzati', { selector: '.advanced-filters' });
    }

    // Statistiche dashboard
    const stats = await page.$('.dashboard-stats, .stats-row, .statistics');
    if (stats) {
        await saveScreenshot(page, 'statistiche_coach', { selector: '.dashboard-stats, .stats-row' });
    }

    // Prova diverse viste
    const compactBtn = await page.$('button:has-text("Compatta"), input[value="Compatta"]');
    if (compactBtn) {
        await compactBtn.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'vista_compatta');
    }

    const detailedBtn = await page.$('button:has-text("Dettagliata"), input[value="Dettagliata"]');
    if (detailedBtn) {
        await detailedBtn.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'vista_dettagliata');
    }

    // Torna a vista classica
    const classicBtn = await page.$('button:has-text("Classica"), input[value="Classica"]');
    if (classicBtn) {
        await classicBtn.click();
        await page.waitForTimeout(1000);
    }
}

async function captureStudentCardScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT SCHEDA STUDENTE');
    console.log('='.repeat(50));

    // Vai alla scheda studente
    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/student_card.php?id=1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // Scheda completa
    await saveScreenshot(page, 'student_card_full', { fullPage: true });
    await saveScreenshot(page, 'student_card');

    // Tab navigazione
    const tabNav = await page.$('.nav-tabs, .tab-navigation, [role="tablist"]');
    if (tabNav) {
        await saveScreenshot(page, 'tab_navigazione', { selector: '.nav-tabs, .tab-navigation' });
    }

    // Tab Anagrafica (già attivo)
    await saveScreenshot(page, 'tab_anagrafica');

    // Sezione dati personali
    const personalData = await page.$('.personal-data, .dati-personali, form');
    if (personalData) {
        await saveScreenshot(page, 'dati_personali', { selector: '.personal-data, .dati-personali' });
    }

    // Tab Percorso
    const percorsoTab = await page.$('a:has-text("Percorso"), [data-tab="percorso"], .nav-link:has-text("Percorso")');
    if (percorsoTab) {
        await percorsoTab.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'tab_percorso');
    }

    // Tab Assenze
    const assenzeTab = await page.$('a:has-text("Assenze"), [data-tab="assenze"], .nav-link:has-text("Assenze")');
    if (assenzeTab) {
        await assenzeTab.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'tab_assenze');
    }

    // Tab Stage
    const stageTab = await page.$('a:has-text("Stage"), [data-tab="stage"], .nav-link:has-text("Stage")');
    if (stageTab) {
        await stageTab.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'tab_stage');
    }

    // Torna ad Anagrafica per altri screenshot
    const anagraficaTab = await page.$('a:has-text("Anagrafica"), [data-tab="anagrafica"], .nav-link:has-text("Anagrafica")');
    if (anagraficaTab) {
        await anagraficaTab.click();
        await page.waitForTimeout(1000);
    }

    // Sezione Coach assegnato
    const coachSection = await page.$('.coach-section, .assigned-coach, [class*="coach"]');
    if (coachSection) {
        await saveScreenshot(page, 'sezione_coach', { selector: '.coach-section, .assigned-coach' });
    }

    // Sezione Multi-settore
    const sectorSection = await page.$('.sector-section, .multi-sector, [class*="sector"], [class*="settore"]');
    if (sectorSection) {
        await saveScreenshot(page, 'multi_settore', { selector: '.sector-section, .multi-sector' });
    }

    // Form aggiungi nota (se presente)
    const noteForm = await page.$('.note-form, .add-note, #add-note-form');
    if (noteForm) {
        await saveScreenshot(page, 'aggiungi_nota', { selector: '.note-form, .add-note' });
    }

    // Pulsante accesso report
    const reportBtn = await page.$('a:has-text("Report"), button:has-text("Report"), .btn-report');
    if (reportBtn) {
        await saveScreenshot(page, 'pulsante_report', { selector: 'a:has-text("Report"), .btn-report' });
    }
}

async function captureReportScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT REPORT');
    console.log('='.repeat(50));

    // Pagina report
    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/report.php?id=1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // Report completo
    await saveScreenshot(page, 'report_full', { fullPage: true });
    await saveScreenshot(page, 'report');

    // Sezione comportamento/compilazione
    const behaviorSection = await page.$('.behavior-section, .comportamento, [class*="behavior"]');
    if (behaviorSection) {
        await saveScreenshot(page, 'sezione_comportamento', { selector: '.behavior-section, .comportamento' });
    }

    // Pulsante salva bozza
    const saveDraftBtn = await page.$('button:has-text("Salva"), button:has-text("Bozza"), .btn-save-draft');
    if (saveDraftBtn) {
        await saveScreenshot(page, 'salva_bozza', { selector: 'button:has-text("Salva"), .btn-save-draft' });
    }

    // Pulsante finalizza (se presente)
    const finalizeBtn = await page.$('button:has-text("Finalizza"), .btn-finalize');
    if (finalizeBtn) {
        await saveScreenshot(page, 'pulsante_finalizza', { selector: 'button:has-text("Finalizza")' });
    }

    // Pulsante export Word
    const exportWordBtn = await page.$('a:has-text("Word"), a:has-text("Export"), .btn-export-word');
    if (exportWordBtn) {
        await saveScreenshot(page, 'esporta_word', { selector: 'a:has-text("Word"), .btn-export-word' });
    }

    // Report competenze
    await page.goto(`${CONFIG.baseUrl}/local/competencymanager/student_report.php?userid=2&courseid=2`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    await saveScreenshot(page, 'report_competenze_full', { fullPage: true });
    await saveScreenshot(page, 'report_competenze');

    // Radar chart se presente
    const radarChart = await page.$('canvas, .radar-chart, .chart-container');
    if (radarChart) {
        await saveScreenshot(page, 'radar_competenze', { selector: 'canvas, .radar-chart' });
    }
}

async function captureCPURCDashboardScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT DASHBOARD CPURC');
    console.log('='.repeat(50));

    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/index.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Dashboard completa
    await saveScreenshot(page, 'dashboard_cpurc_full', { fullPage: true });
    await saveScreenshot(page, 'dashboard_cpurc');

    // Statistiche in alto
    const statsRow = await page.$('.stats-row, .statistics, .dashboard-stats');
    if (statsRow) {
        await saveScreenshot(page, 'statistiche_cpurc', { selector: '.stats-row, .statistics' });
    }

    // Distribuzione URC
    const urcDistribution = await page.$('.urc-distribution, .distribution-badges');
    if (urcDistribution) {
        await saveScreenshot(page, 'distribuzione_urc', { selector: '.urc-distribution' });
    }

    // Campo ricerca
    const searchInput = await page.$('input[name="search"], input[type="search"], #search-input');
    if (searchInput) {
        await searchInput.focus();
        await saveScreenshot(page, 'filtro_ricerca');
    }

    // Filtro URC
    const urcFilter = await page.$('select[name="urc"], #urc-filter');
    if (urcFilter) {
        await urcFilter.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'filtro_urc');
        await page.keyboard.press('Escape');
    }

    // Filtro Settore
    const sectorFilter = await page.$('select[name="settore"], select[name="sector"], #sector-filter');
    if (sectorFilter) {
        await sectorFilter.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'filtro_settore');
        await page.keyboard.press('Escape');
    }

    // Filtro Stato Report
    const statusFilter = await page.$('select[name="stato"], select[name="report_status"], #status-filter');
    if (statusFilter) {
        await statusFilter.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'filtro_stato_report');
        await page.keyboard.press('Escape');
    }

    // Filtro Coach
    const coachFilter = await page.$('select[name="coach"], #coach-filter');
    if (coachFilter) {
        await coachFilter.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'filtro_coach');
        await page.keyboard.press('Escape');
    }

    // Riga tabella con pulsanti azione
    const tableRow = await page.$('table tbody tr, .student-row');
    if (tableRow) {
        await tableRow.hover();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'pulsanti_azione', { selector: 'table tbody tr:first-child, .student-row:first-child' });
    }

    // Dropdown assegnazione coach
    const coachDropdown = await page.$('select.coach-select, .coach-dropdown');
    if (coachDropdown) {
        await coachDropdown.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'assegna_coach');
        await page.keyboard.press('Escape');
    }

    // Pulsante Export Excel
    const excelBtn = await page.$('a:has-text("Excel"), button:has-text("Excel"), .btn-excel');
    if (excelBtn) {
        await saveScreenshot(page, 'export_excel', { selector: 'a:has-text("Excel"), .btn-excel' });
    }

    // Pulsante filtro
    const filterBtn = await page.$('button:has-text("Filtra"), input[value="Filtra"], .btn-filter');
    if (filterBtn) {
        await saveScreenshot(page, 'pulsante_filtra', { selector: 'button:has-text("Filtra")' });
    }

    // Tabella studenti
    const table = await page.$('table.student-table, table');
    if (table) {
        await saveScreenshot(page, 'tabella_studenti', { selector: 'table' });
    }
}

async function captureImportExportScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT IMPORT/EXPORT');
    console.log('='.repeat(50));

    // Pagina import CSV
    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/import.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await saveScreenshot(page, 'accesso_import');

    // Form upload file
    const uploadForm = await page.$('form, .upload-form, .file-picker');
    if (uploadForm) {
        await saveScreenshot(page, 'carica_file', { selector: 'form, .upload-form' });
    }

    // Export Word bulk
    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/export_word_bulk.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await saveScreenshot(page, 'export_word_bulk');
    await saveScreenshot(page, 'export_zip', { fullPage: true });

    // Checkbox selezione studenti
    const checkboxes = await page.$('.student-checkboxes, form');
    if (checkboxes) {
        await saveScreenshot(page, 'selezione_studenti', { selector: '.student-checkboxes, form' });
    }

    // Pulsante genera ZIP
    const zipBtn = await page.$('button:has-text("ZIP"), button:has-text("Genera"), input[type="submit"]');
    if (zipBtn) {
        await saveScreenshot(page, 'pulsante_genera_zip', { selector: 'button:has-text("ZIP"), input[type="submit"]' });
    }
}

async function captureSectorScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT GESTIONE SETTORI');
    console.log('='.repeat(50));

    // Pagina admin settori
    await page.goto(`${CONFIG.baseUrl}/local/competencymanager/sector_admin.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await saveScreenshot(page, 'sector_admin_full', { fullPage: true });
    await saveScreenshot(page, 'sector_admin');

    // Form assegnazione settori
    const sectorForm = await page.$('.sector-form, form');
    if (sectorForm) {
        await saveScreenshot(page, 'assegnazione_settori', { selector: '.sector-form, form' });
    }

    // Torna alla scheda studente per settori
    await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/student_card.php?id=1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Sezione settori nella scheda
    const sectorBadges = await page.$('.sector-badges, .settori, [class*="sector"]');
    if (sectorBadges) {
        await saveScreenshot(page, 'badge_settori', { selector: '.sector-badges, .settori' });
    }

    // Dropdown settori (se presente)
    const sectorSelect = await page.$('select[name*="sector"], select[name*="settore"]');
    if (sectorSelect) {
        await sectorSelect.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'dropdown_settori');
        await page.keyboard.press('Escape');
    }
}

async function captureSchedulerScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT SCHEDULER/CALENDARIO');
    console.log('='.repeat(50));

    await page.goto(`${CONFIG.baseUrl}/local/ftm_scheduler/index.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Calendario completo
    await saveScreenshot(page, 'calendario_full', { fullPage: true });
    await saveScreenshot(page, 'calendario');

    // Vista settimanale
    const weekView = await page.$('.week-view, .fc-timeGridWeek-view, [class*="week"]');
    if (weekView) {
        await saveScreenshot(page, 'vista_settimanale', { selector: '.week-view' });
    }

    // Pulsanti navigazione calendario
    const calendarNav = await page.$('.fc-toolbar, .calendar-toolbar, .calendar-nav');
    if (calendarNav) {
        await saveScreenshot(page, 'navigazione_calendario', { selector: '.fc-toolbar, .calendar-toolbar' });
    }

    // Pulsanti cambio vista (giorno, settimana, mese)
    const viewBtns = await page.$('.fc-button-group, .view-buttons');
    if (viewBtns) {
        await saveScreenshot(page, 'pulsanti_vista_calendario', { selector: '.fc-button-group' });
    }

    // Prova vista mensile
    const monthBtn = await page.$('button:has-text("Mese"), .fc-dayGridMonth-button');
    if (monthBtn) {
        await monthBtn.click();
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'vista_mensile');
    }

    // Sidebar gruppi/colori
    const sidebar = await page.$('.sidebar, .group-sidebar, .color-legend');
    if (sidebar) {
        await saveScreenshot(page, 'legenda_gruppi', { selector: '.sidebar, .group-sidebar' });
    }

    // Eventi nel calendario
    const calendarEvent = await page.$('.fc-event, .calendar-event');
    if (calendarEvent) {
        await calendarEvent.hover();
        await page.waitForTimeout(500);
        await saveScreenshot(page, 'evento_calendario', { selector: '.fc-event, .calendar-event' });
    }

    // Gestione aule (se presente link)
    const aulaLink = await page.$('a:has-text("Aule"), a:has-text("Atelier")');
    if (aulaLink) {
        await aulaLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
        await saveScreenshot(page, 'gestione_aule');
    }
}

async function captureSetupUniversaleScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT SETUP UNIVERSALE');
    console.log('='.repeat(50));

    await page.goto(`${CONFIG.baseUrl}/local/competencyxmlimport/setup_universale.php?courseid=2`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // Pagina completa
    await saveScreenshot(page, 'setup_universale_full', { fullPage: true });
    await saveScreenshot(page, 'setup_universale');

    // Step 1 - Selezione framework
    const frameworkSelect = await page.$('select[name="framework"], #framework-select');
    if (frameworkSelect) {
        await frameworkSelect.click();
        await page.waitForTimeout(300);
        await saveScreenshot(page, 'selezione_framework');
        await page.keyboard.press('Escape');
    }

    // Step configurazione
    const configSection = await page.$('.config-section, .wizard-step');
    if (configSection) {
        await saveScreenshot(page, 'configurazione_import', { selector: '.config-section' });
    }
}

async function captureSelfAssessmentScreenshots(page) {
    console.log('\n' + '='.repeat(50));
    console.log('📷 SCREENSHOT AUTOVALUTAZIONE');
    console.log('='.repeat(50));

    await page.goto(`${CONFIG.baseUrl}/local/selfassessment/index.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await saveScreenshot(page, 'autovalutazione');

    // Pagina gestione autovalutazione
    await page.goto(`${CONFIG.baseUrl}/local/selfassessment/manage.php`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await saveScreenshot(page, 'gestione_autovalutazione');
}

// ============================================
// MAIN
// ============================================

async function main() {
    console.log('='.repeat(60));
    console.log('FTM - Cattura Completa Screenshot per Manuali');
    console.log('='.repeat(60));

    await ensureDir(CONFIG.outputDir);
    console.log(`\n📁 Output: ${CONFIG.outputDir}`);

    const browser = await chromium.launch({
        headless: false,
        slowMo: 150,
        args: ['--start-maximized']
    });

    const context = await browser.newContext({
        viewport: null,
        locale: 'it-IT',
        screen: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    try {
        // Screenshot generali
        await captureGeneralScreenshots(page);

        // Screenshot Coach (già loggato)
        await captureCoachDashboardScreenshots(page);
        await captureSchedulerScreenshots(page);

        // Logout e login come admin
        await logout(page);
        await login(page, CONFIG.segreteria);

        // Screenshot CPURC/Segreteria
        await captureCPURCDashboardScreenshots(page);
        await captureStudentCardScreenshots(page);
        await captureReportScreenshots(page);
        await captureImportExportScreenshots(page);
        await captureSectorScreenshots(page);
        await captureSetupUniversaleScreenshots(page);
        await captureSelfAssessmentScreenshots(page);

    } catch (error) {
        console.error(`\n❌ Errore: ${error.message}`);
        await page.screenshot({ path: join(CONFIG.outputDir, 'error_screenshot.png') });
    }

    await browser.close();

    // Riepilogo
    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO');
    console.log('='.repeat(60));
    console.log(`✅ Screenshot salvati: ${successCount}`);
    console.log(`❌ Falliti: ${failCount}`);
    console.log(`📁 Cartella: ${CONFIG.outputDir}`);
    console.log('='.repeat(60));
}

main().catch(console.error);
