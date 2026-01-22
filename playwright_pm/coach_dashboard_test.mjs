/**
 * FTM PLUGINS - Coach Dashboard V2 Test
 * Test completo delle funzionalita della nuova dashboard
 *
 * Esegui: node coach_dashboard_test.mjs
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const CREDENTIALS = {
    username: 'admin_urc_test',
    password: 'Brain666*'
};

// Crea cartella screenshots se non esiste
if (!existsSync('./screenshots')) {
    mkdirSync('./screenshots');
}

async function testCoachDashboardV2() {
    console.log('='.repeat(60));
    console.log('COACH DASHBOARD V2 - TEST COMPLETO');
    console.log('Data:', new Date().toLocaleString('it-IT'));
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    const results = {
        login: false,
        pageLoad: false,
        viewControls: false,
        zoomControls: false,
        filtersHorizontal: false,
        views: { classica: false, compatta: false, standard: false, dettagliata: false },
        zoom: { '90': false, '100': false, '120': false, '140': false },
        errors: []
    };

    try {
        // 1. LOGIN
        console.log('\n[1/8] Login...');
        await page.goto(`${BASE_URL}/login/index.php`);
        await page.fill('input[name="username"]', CREDENTIALS.username);
        await page.fill('input[name="password"]', CREDENTIALS.password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"], input[type="submit"]')
        ]);
        results.login = true;
        console.log('    OK');

        // 2. NAVIGAZIONE DASHBOARD V2
        console.log('\n[2/8] Navigazione a Coach Dashboard V2...');
        await page.goto(`${BASE_URL}/local/coachmanager/coach_dashboard_v2.php`, {
            waitUntil: 'networkidle',
            timeout: 30000
        });
        await page.waitForTimeout(2000);
        results.pageLoad = true;
        console.log('    OK - Pagina caricata');

        // 3. VERIFICA VIEW CONTROLS
        console.log('\n[3/8] Verifica View Controls...');
        const viewControls = await page.$('.view-controls');
        results.viewControls = !!viewControls;
        const viewButtons = await page.$$('.view-btn');
        console.log(`    ${viewButtons.length} bottoni vista trovati`);

        // 4. VERIFICA ZOOM CONTROLS
        console.log('\n[4/8] Verifica Zoom Controls...');
        const zoomControls = await page.$('.zoom-controls');
        results.zoomControls = !!zoomControls;
        const zoomButtons = await page.$$('.zoom-btn');
        console.log(`    ${zoomButtons.length} bottoni zoom trovati`);

        // 5. VERIFICA FILTRI ORIZZONTALI
        console.log('\n[5/8] Verifica Filtri Orizzontali...');
        const filtersRow = await page.$('table[style*="width: 100%"]');
        results.filtersHorizontal = !!filtersRow;
        if (filtersRow) {
            const filterCells = await filtersRow.$$('td');
            console.log(`    OK - ${filterCells.length} filtri in riga orizzontale`);
        } else {
            console.log('    WARN - Layout filtri potrebbe non essere orizzontale');
        }

        // 6. TEST VISTE
        console.log('\n[6/8] Test cambio viste...');
        const viste = ['Classica', 'Compatta', 'Standard', 'Dettagliata'];
        for (const vista of viste) {
            try {
                const btn = await page.$(`button.view-btn:has-text("${vista}")`);
                if (btn) {
                    await btn.click();
                    await page.waitForTimeout(1000);
                    await page.screenshot({
                        path: `./screenshots/v2_vista_${vista.toLowerCase()}.png`,
                        fullPage: true
                    });
                    results.views[vista.toLowerCase()] = true;
                    console.log(`    ${vista}: OK`);
                }
            } catch (e) {
                console.log(`    ${vista}: ERRORE - ${e.message}`);
                results.errors.push(`Vista ${vista}: ${e.message}`);
            }
        }

        // 7. TEST ZOOM
        console.log('\n[7/8] Test livelli zoom...');
        const zoomLevels = [
            { label: 'A-', value: '90' },
            { label: 'A', value: '100' },
            { label: 'A+', value: '120' },
            { label: 'A++', value: '140' }
        ];
        for (const zoom of zoomLevels) {
            try {
                const btn = await page.$(`button.zoom-btn:has-text("${zoom.label}")`);
                if (btn) {
                    await btn.click();
                    await page.waitForTimeout(500);
                    results.zoom[zoom.value] = true;
                    console.log(`    ${zoom.label} (${zoom.value}%): OK`);
                }
            } catch (e) {
                console.log(`    ${zoom.label}: ERRORE - ${e.message}`);
                results.errors.push(`Zoom ${zoom.label}: ${e.message}`);
            }
        }

        // 8. SCREENSHOT FINALE
        console.log('\n[8/8] Screenshot finale...');
        await page.screenshot({
            path: './screenshots/v2_final_check.png',
            fullPage: true
        });
        console.log('    Salvato: v2_final_check.png');

        // VERIFICA CSS
        console.log('\n[EXTRA] Verifica CSS...');
        const cssCheck = await page.evaluate(() => {
            const viewBtns = document.querySelectorAll('.view-btn');
            let btnTextVisible = false;
            viewBtns.forEach(btn => {
                const style = window.getComputedStyle(btn);
                if (style.color && style.color !== 'rgba(0, 0, 0, 0)') {
                    btnTextVisible = true;
                }
            });

            return {
                viewBtnCount: viewBtns.length,
                btnTextVisible,
                bodyFontSize: document.body.style.fontSize || 'default'
            };
        });
        console.log(`    Bottoni vista: ${cssCheck.viewBtnCount}`);
        console.log(`    Testo visibile: ${cssCheck.btnTextVisible ? 'SI' : 'NO'}`);
        console.log(`    Font size body: ${cssCheck.bodyFontSize}`);

    } catch (error) {
        results.errors.push(`Errore generale: ${error.message}`);
        console.error('\nERRORE:', error.message);
    }

    await browser.close();

    // RIEPILOGO
    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO TEST');
    console.log('='.repeat(60));

    console.log('\nFunzionalita base:');
    console.log(`  Login:          ${results.login ? 'OK' : 'FAIL'}`);
    console.log(`  Caricamento:    ${results.pageLoad ? 'OK' : 'FAIL'}`);
    console.log(`  View Controls:  ${results.viewControls ? 'OK' : 'FAIL'}`);
    console.log(`  Zoom Controls:  ${results.zoomControls ? 'OK' : 'FAIL'}`);
    console.log(`  Filtri Horiz:   ${results.filtersHorizontal ? 'OK' : 'WARN'}`);

    console.log('\nViste:');
    Object.entries(results.views).forEach(([vista, ok]) => {
        console.log(`  ${vista.padEnd(12)}: ${ok ? 'OK' : 'FAIL'}`);
    });

    console.log('\nZoom:');
    Object.entries(results.zoom).forEach(([level, ok]) => {
        console.log(`  ${level}%`.padEnd(12) + `: ${ok ? 'OK' : 'FAIL'}`);
    });

    if (results.errors.length > 0) {
        console.log('\nErrori riscontrati:');
        results.errors.forEach(e => console.log(`  - ${e}`));
    }

    const passCount = [
        results.login,
        results.pageLoad,
        results.viewControls,
        results.zoomControls,
        results.filtersHorizontal,
        ...Object.values(results.views),
        ...Object.values(results.zoom)
    ].filter(Boolean).length;

    const totalTests = 13; // 5 base + 4 viste + 4 zoom
    console.log(`\nRisultato: ${passCount}/${totalTests} test passati`);
    console.log('='.repeat(60));

    return results;
}

testCoachDashboardV2().catch(console.error);
