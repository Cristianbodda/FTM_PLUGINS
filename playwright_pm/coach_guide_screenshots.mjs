/**
 * FTM Coach Guide - Screenshot Generator
 * Cattura screenshot per la Guida Operativa Coach
 *
 * Esegui con: node coach_guide_screenshots.mjs
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

// Configurazione
const CONFIG = {
    baseUrl: 'https://sezionelavoro.ch',
    username: 'cristian.bodda',
    password: 'Natale.2024!',
    outputDir: './screenshots/coach_guide',
    viewport: { width: 1920, height: 1080 }
};

// Crea directory output
if (!fs.existsSync(CONFIG.outputDir)) {
    fs.mkdirSync(CONFIG.outputDir, { recursive: true });
}

async function login(page) {
    console.log('🔐 Login in corso...');
    await page.goto(`${CONFIG.baseUrl}/login/index.php`);
    await page.fill('#username', CONFIG.username);
    await page.fill('#password', CONFIG.password);
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');
    console.log('✅ Login completato');
}

async function captureScreenshot(page, url, filename, options = {}) {
    const fullUrl = url.startsWith('http') ? url : `${CONFIG.baseUrl}${url}`;
    console.log(`📸 Cattura: ${filename}`);

    try {
        await page.goto(fullUrl, { waitUntil: 'networkidle', timeout: 30000 });

        // Attendi eventuali animazioni
        await page.waitForTimeout(1000);

        // Esegui azioni pre-screenshot se specificate
        if (options.beforeScreenshot) {
            await options.beforeScreenshot(page);
        }

        // Screenshot
        const filepath = path.join(CONFIG.outputDir, `${filename}.png`);

        if (options.fullPage) {
            await page.screenshot({ path: filepath, fullPage: true });
        } else if (options.selector) {
            const element = await page.$(options.selector);
            if (element) {
                await element.screenshot({ path: filepath });
            } else {
                await page.screenshot({ path: filepath });
            }
        } else {
            await page.screenshot({ path: filepath });
        }

        console.log(`   ✅ Salvato: ${filepath}`);
        return true;
    } catch (error) {
        console.error(`   ❌ Errore: ${error.message}`);
        return false;
    }
}

async function main() {
    console.log('🚀 FTM Coach Guide - Screenshot Generator');
    console.log('=========================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--lang=it-IT']
    });

    const context = await browser.newContext({
        viewport: CONFIG.viewport,
        locale: 'it-IT'
    });

    const page = await context.newPage();

    try {
        // Login
        await login(page);

        // =============================================
        // SEZIONE 1: DASHBOARD COACH V2
        // =============================================
        console.log('\n📊 SEZIONE 1: Dashboard Coach V2');
        console.log('─'.repeat(40));

        // 1.1 Dashboard completa
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php',
            '01_dashboard_coach_full',
            { fullPage: false }
        );

        // 1.2 Dashboard - Vista Classica
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php?view=classic',
            '02_dashboard_vista_classica'
        );

        // 1.3 Dashboard - Vista Compatta
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php?view=compact',
            '03_dashboard_vista_compatta'
        );

        // 1.4 Dashboard - Vista Standard
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php?view=standard',
            '04_dashboard_vista_standard'
        );

        // 1.5 Dashboard - Vista Dettagliata
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php?view=detailed',
            '05_dashboard_vista_dettagliata'
        );

        // 1.6 Dashboard - Zoom A++
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php?zoom=140',
            '06_dashboard_zoom_grande',
            {
                beforeScreenshot: async (p) => {
                    // Clicca su A++ se presente
                    const zoomBtn = await p.$('button:has-text("A++")');
                    if (zoomBtn) await zoomBtn.click();
                    await p.waitForTimeout(500);
                }
            }
        );

        // 1.7 Barra filtri evidenziata
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php',
            '07_dashboard_barra_filtri',
            {
                selector: '.filter-bar, .filters-container, [class*="filter"]'
            }
        );

        // 1.8 Card studente singola
        await captureScreenshot(page,
            '/local/coachmanager/coach_dashboard_v2.php',
            '08_card_studente',
            {
                beforeScreenshot: async (p) => {
                    // Trova prima card studente
                    const card = await p.$('.student-card, .card, [class*="student"]');
                    if (card) {
                        await card.scrollIntoViewIfNeeded();
                    }
                },
                selector: '.student-card:first-child, .card:first-child'
            }
        );

        // =============================================
        // SEZIONE 2: SCHEDA STUDENTE
        // =============================================
        console.log('\n👤 SEZIONE 2: Scheda Studente');
        console.log('─'.repeat(40));

        // Prima troviamo un ID studente valido dalla dashboard
        await page.goto(`${CONFIG.baseUrl}/local/coachmanager/coach_dashboard_v2.php`);
        await page.waitForLoadState('networkidle');

        // Cerca link a scheda studente
        const studentLink = await page.$('a[href*="student_card"], a[href*="userid"]');
        let studentUrl = '/local/ftm_cpurc/student_card.php?id=1';

        if (studentLink) {
            const href = await studentLink.getAttribute('href');
            if (href) studentUrl = href;
        }

        // 2.1 Scheda studente - Tab Anagrafica
        await captureScreenshot(page,
            studentUrl,
            '09_scheda_studente_anagrafica',
            { fullPage: false }
        );

        // 2.2 Scheda studente - Tab Percorso
        await captureScreenshot(page,
            studentUrl + '&tab=percorso',
            '10_scheda_studente_percorso'
        );

        // 2.3 Scheda studente - Tab Assenze
        await captureScreenshot(page,
            studentUrl + '&tab=assenze',
            '11_scheda_studente_assenze'
        );

        // 2.4 Scheda studente - Tab Stage
        await captureScreenshot(page,
            studentUrl + '&tab=stage',
            '12_scheda_studente_stage'
        );

        // =============================================
        // SEZIONE 3: REPORT COMPETENZE
        // =============================================
        console.log('\n📊 SEZIONE 3: Report Competenze');
        console.log('─'.repeat(40));

        // 3.1 Report competenze - Panoramica
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2',
            '13_report_competenze_panoramica',
            { fullPage: false }
        );

        // 3.2 Report competenze - Con radar
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2&tab=overview',
            '14_report_competenze_radar',
            {
                beforeScreenshot: async (p) => {
                    // Scroll al radar
                    const radar = await p.$('svg, .radar-container, [class*="radar"]');
                    if (radar) await radar.scrollIntoViewIfNeeded();
                }
            }
        );

        // 3.3 Report competenze - Gap Analysis
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2&show_gap=1',
            '15_report_gap_analysis'
        );

        // 3.4 Report competenze - Doppio Radar
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2&show_dual_radar=1',
            '16_report_doppio_radar'
        );

        // 3.5 Report competenze - Suggerimenti Rapporto
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2&show_suggerimenti=1',
            '17_report_suggerimenti'
        );

        // 3.6 Modale stampa personalizzata
        await captureScreenshot(page,
            '/local/competencymanager/student_report.php?userid=2&courseid=2',
            '18_modale_stampa',
            {
                beforeScreenshot: async (p) => {
                    // Apri modale stampa
                    const printBtn = await p.$('button[data-target="#printModal"], button:has-text("Stampa")');
                    if (printBtn) {
                        await printBtn.click();
                        await p.waitForTimeout(500);
                    }
                }
            }
        );

        // =============================================
        // SEZIONE 4: AUTOVALUTAZIONE
        // =============================================
        console.log('\n📝 SEZIONE 4: Autovalutazione');
        console.log('─'.repeat(40));

        // 4.1 Report autovalutazione studente
        await captureScreenshot(page,
            '/local/selfassessment/student_report.php?userid=2&courseid=2',
            '19_autovalutazione_report'
        );

        // =============================================
        // SEZIONE 5: CALENDARIO FTM
        // =============================================
        console.log('\n📅 SEZIONE 5: Calendario FTM');
        console.log('─'.repeat(40));

        // 5.1 Calendario settimanale
        await captureScreenshot(page,
            '/local/ftm_scheduler/index.php',
            '20_calendario_settimanale'
        );

        // 5.2 Calendario mensile
        await captureScreenshot(page,
            '/local/ftm_scheduler/index.php?view=month',
            '21_calendario_mensile'
        );

        // =============================================
        // SEZIONE 6: REPORT WORD
        // =============================================
        console.log('\n📄 SEZIONE 6: Report Word');
        console.log('─'.repeat(40));

        // 6.1 Pagina compilazione report
        await captureScreenshot(page,
            '/local/ftm_cpurc/report.php?id=1',
            '22_report_word_compilazione'
        );

        // =============================================
        // RIEPILOGO
        // =============================================
        console.log('\n' + '═'.repeat(50));
        console.log('✅ SCREENSHOT COMPLETATI');
        console.log('═'.repeat(50));
        console.log(`📁 Directory: ${CONFIG.outputDir}`);

        // Lista file generati
        const files = fs.readdirSync(CONFIG.outputDir);
        console.log(`📸 File generati: ${files.length}`);
        files.forEach(f => console.log(`   - ${f}`));

    } catch (error) {
        console.error('❌ Errore generale:', error);
    } finally {
        await browser.close();
    }
}

main();
