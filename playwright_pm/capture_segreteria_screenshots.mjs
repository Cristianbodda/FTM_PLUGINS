/**
 * FTM Screenshots - Parte Segreteria
 * Cattura screenshot per sezione CPURC/Segreteria
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
    baseUrl: 'https://test-urc.hizuvala.myhostpoint.ch',
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

async function main() {
    console.log('='.repeat(60));
    console.log('FTM - Screenshot Segreteria/CPURC');
    console.log('='.repeat(60));

    await ensureDir(CONFIG.outputDir);

    const browser = await chromium.launch({
        headless: false,
        slowMo: 200,
        args: ['--start-maximized']
    });

    const context = await browser.newContext({
        viewport: null,
        locale: 'it-IT'
    });

    const page = await context.newPage();

    try {
        // Login come segreteria
        console.log('\n🔐 Login come segreteria...');
        await page.goto(`${CONFIG.baseUrl}/login/index.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
        await page.fill('#username', CONFIG.segreteria.username);
        await page.fill('#password', CONFIG.segreteria.password);
        await page.click('#loginbtn');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('✅ Login riuscito');

        // ===== DASHBOARD CPURC =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 DASHBOARD CPURC');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/index.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await saveScreenshot(page, 'dashboard_cpurc_full', { fullPage: true });
        await saveScreenshot(page, 'dashboard_cpurc');

        // Statistiche
        await saveScreenshot(page, 'statistiche_cpurc', { selector: '.stats-row, .statistics, .stat-cards' });

        // Distribuzione URC (badge colorati)
        await saveScreenshot(page, 'distribuzione_urc', { selector: '.badge-row, .distribution' });

        // Barra filtri
        await saveScreenshot(page, 'barra_filtri_cpurc', { selector: '.filter-row, .filters, form' });

        // Tabella studenti
        await saveScreenshot(page, 'tabella_studenti', { selector: 'table' });

        // Singola riga con pulsanti
        const firstRow = await page.$('table tbody tr:first-child');
        if (firstRow) {
            await firstRow.hover();
            await page.waitForTimeout(300);
            await saveScreenshot(page, 'riga_studente', { selector: 'table tbody tr:first-child' });
        }

        // ===== SCHEDA STUDENTE =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 SCHEDA STUDENTE');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/student_card.php?id=1`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await saveScreenshot(page, 'student_card_full', { fullPage: true });
        await saveScreenshot(page, 'student_card');

        // Header scheda con nome e badge
        await saveScreenshot(page, 'header_scheda', { selector: '.card-header, .student-header, h2, h3' });

        // Tab navigation
        await saveScreenshot(page, 'tab_navigazione', { selector: '.nav-tabs, [role="tablist"]' });

        // Tab Anagrafica
        await saveScreenshot(page, 'tab_anagrafica');

        // Click su Percorso
        const percorsoTab = await page.$('a[href*="percorso"], button:has-text("Percorso"), .nav-link:has-text("Percorso")');
        if (percorsoTab) {
            await percorsoTab.click();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, 'tab_percorso');
        }

        // Click su Assenze
        const assenzeTab = await page.$('a[href*="assenze"], button:has-text("Assenze"), .nav-link:has-text("Assenze")');
        if (assenzeTab) {
            await assenzeTab.click();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, 'tab_assenze');
        }

        // Click su Stage
        const stageTab = await page.$('a[href*="stage"], button:has-text("Stage"), .nav-link:has-text("Stage")');
        if (stageTab) {
            await stageTab.click();
            await page.waitForTimeout(1000);
            await saveScreenshot(page, 'tab_stage');
        }

        // ===== REPORT =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 REPORT');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/report.php?id=1`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await saveScreenshot(page, 'report_full', { fullPage: true });
        await saveScreenshot(page, 'report');

        // Pulsanti azione report
        await saveScreenshot(page, 'pulsanti_report', { selector: '.btn-group, .actions, .report-actions' });

        // ===== REPORT COMPETENZE =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 REPORT COMPETENZE');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/competencymanager/student_report.php?userid=2&courseid=2`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await saveScreenshot(page, 'report_competenze_full', { fullPage: true });
        await saveScreenshot(page, 'report_competenze');

        // Grafico radar se presente
        const canvas = await page.$('canvas');
        if (canvas) {
            await saveScreenshot(page, 'radar_competenze', { selector: 'canvas' });
        }

        // ===== EXPORT WORD BULK =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 EXPORT WORD BULK');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/export_word_bulk.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await saveScreenshot(page, 'export_word_bulk_full', { fullPage: true });
        await saveScreenshot(page, 'export_word_bulk');

        // Form selezione
        await saveScreenshot(page, 'selezione_export', { selector: 'form' });

        // ===== IMPORT CSV =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 IMPORT CSV');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_cpurc/import.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        await saveScreenshot(page, 'import_csv_full', { fullPage: true });
        await saveScreenshot(page, 'import_csv');

        // ===== SECTOR ADMIN =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 GESTIONE SETTORI');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/competencymanager/sector_admin.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        await saveScreenshot(page, 'sector_admin_full', { fullPage: true });
        await saveScreenshot(page, 'sector_admin');

        // ===== SETUP UNIVERSALE =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 SETUP UNIVERSALE');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/competencyxmlimport/setup_universale.php?courseid=2`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        await saveScreenshot(page, 'setup_universale_full', { fullPage: true });
        await saveScreenshot(page, 'setup_universale');

        // ===== SELFASSESSMENT =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 AUTOVALUTAZIONE');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/selfassessment/index.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        await saveScreenshot(page, 'autovalutazione');

        // ===== FTM HUB =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 FTM HUB');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/ftm_hub/index.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        await saveScreenshot(page, 'ftm_hub');

        // ===== LABEVAL =====
        console.log('\n' + '='.repeat(50));
        console.log('📷 LAB EVALUATION');
        console.log('='.repeat(50));

        await page.goto(`${CONFIG.baseUrl}/local/labeval/index.php`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        await saveScreenshot(page, 'labeval');

    } catch (error) {
        console.error(`\n❌ Errore: ${error.message}`);
        await page.screenshot({ path: join(CONFIG.outputDir, 'error_segreteria.png') });
    }

    await browser.close();

    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO SEGRETERIA');
    console.log('='.repeat(60));
    console.log(`✅ Screenshot salvati: ${successCount}`);
    console.log(`❌ Falliti: ${failCount}`);
    console.log('='.repeat(60));
}

main().catch(console.error);
