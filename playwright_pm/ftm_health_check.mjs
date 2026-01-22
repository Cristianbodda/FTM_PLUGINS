/**
 * FTM PLUGINS - Health Check Suite COMPLETO
 * Playwright as Project Manager
 *
 * Verifica automatica di TUTTI i plugin FTM nella cartella local/
 * Esegui: node ftm_health_check.mjs
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const CREDENTIALS = {
    username: 'admin_urc_test',
    password: 'Brain666*'
};

// ============================================
// TUTTI I PLUGIN FTM - 11 plugin, 18 pagine
// ============================================
const HEALTH_CHECKS = [
    // ========== 1. COACHMANAGER ==========
    {
        name: 'Coach Dashboard V2',
        plugin: 'coachmanager',
        url: '/local/coachmanager/coach_dashboard_v2.php',
        checks: ['view-controls', 'zoom-controls'],
        priority: 'HIGH'
    },
    {
        name: 'Coach Dashboard V1',
        plugin: 'coachmanager',
        url: '/local/coachmanager/coach_dashboard.php',
        checks: ['dashboard', 'student'],
        priority: 'MEDIUM'
    },
    {
        name: 'Bilancio Competenze',
        plugin: 'coachmanager',
        url: '/local/coachmanager/index.php',
        checks: ['competenc', 'student'],
        priority: 'MEDIUM'
    },

    // ========== 2. COMPETENCYMANAGER ==========
    {
        name: 'Sector Admin',
        plugin: 'competencymanager',
        url: '/local/competencymanager/sector_admin.php',
        checks: ['sector', 'table'],
        priority: 'HIGH'
    },
    {
        name: 'Student Report',
        plugin: 'competencymanager',
        url: '/local/competencymanager/student_report.php?userid=2',
        checks: ['report', 'competenc'],
        priority: 'MEDIUM'
    },
    {
        name: 'Area Mapping',
        plugin: 'competencymanager',
        url: '/local/competencymanager/area_mapping.php',
        checks: ['mapping', 'area'],
        priority: 'LOW'
    },

    // ========== 3. COMPETENCYREPORT ==========
    {
        name: 'Competency Report',
        plugin: 'competencyreport',
        url: '/local/competencyreport/index.php',
        checks: ['report', 'competenc'],
        priority: 'MEDIUM'
    },

    // ========== 4. COMPETENCYXMLIMPORT ==========
    {
        name: 'Setup Universale',
        plugin: 'competencyxmlimport',
        url: '/local/competencyxmlimport/setup_universale.php?courseid=2',
        checks: ['step', 'framework'],
        priority: 'HIGH'
    },
    {
        name: 'XML Import',
        plugin: 'competencyxmlimport',
        url: '/local/competencyxmlimport/index.php',
        checks: ['import', 'file'],
        priority: 'MEDIUM'
    },

    // ========== 5. FTM_COMMON ==========
    // Libreria condivisa, nessuna pagina web

    // ========== 6. FTM_CPURC ==========
    {
        name: 'CPURC Import',
        plugin: 'ftm_cpurc',
        url: '/local/ftm_cpurc/index.php',
        checks: ['import', 'csv'],
        priority: 'LOW'
    },

    // ========== 7. FTM_HUB ==========
    {
        name: 'FTM Hub',
        plugin: 'ftm_hub',
        url: '/local/ftm_hub/index.php',
        checks: ['hub', 'menu'],
        priority: 'HIGH'
    },

    // ========== 8. FTM_SCHEDULER ==========
    {
        name: 'FTM Scheduler',
        plugin: 'ftm_scheduler',
        url: '/local/ftm_scheduler/index.php',
        checks: ['calendar', 'schedule'],
        priority: 'HIGH'
    },

    // ========== 9. FTM_TESTSUITE ==========
    {
        name: 'Test Suite Index',
        plugin: 'ftm_testsuite',
        url: '/local/ftm_testsuite/index.php',
        checks: ['test', 'run'],
        priority: 'MEDIUM'
    },
    {
        name: 'Agent Tests',
        plugin: 'ftm_testsuite',
        url: '/local/ftm_testsuite/agent_tests.php',
        checks: ['agent', 'test'],
        priority: 'MEDIUM'
    },

    // ========== 10. LABEVAL ==========
    {
        name: 'LabEval Index',
        plugin: 'labeval',
        url: '/local/labeval/index.php',
        checks: ['lab', 'eval'],
        priority: 'MEDIUM'
    },
    // LabEval Valutazione richiede assignmentid - testato solo con index


    // ========== 11. SELFASSESSMENT ==========
    {
        name: 'Self Assessment',
        plugin: 'selfassessment',
        url: '/local/selfassessment/index.php',
        checks: ['self', 'assess'],
        priority: 'HIGH'
    },
    {
        name: 'Self Assessment Compile',
        plugin: 'selfassessment',
        url: '/local/selfassessment/compile.php',
        checks: ['compil', 'form'],
        priority: 'MEDIUM'
    }
];

// Crea cartella screenshots
if (!existsSync('./screenshots')) {
    mkdirSync('./screenshots');
}

async function runHealthCheck() {
    console.log('='.repeat(70));
    console.log('FTM PLUGINS - HEALTH CHECK COMPLETO');
    console.log('Data:', new Date().toLocaleString('it-IT'));
    console.log('='.repeat(70));

    // Conta plugin unici
    const uniquePlugins = [...new Set(HEALTH_CHECKS.map(c => c.plugin))];
    console.log(`\nPlugin monitorati: ${uniquePlugins.length}`);
    console.log(`Pagine verificate: ${HEALTH_CHECKS.length}`);
    console.log('Plugins:', uniquePlugins.join(', '));

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    // Login
    console.log('\n[LOGIN] Accesso al sistema...');
    await page.goto(`${BASE_URL}/login/index.php`);
    await page.waitForTimeout(1000);
    await page.fill('input[name="username"]', CREDENTIALS.username);
    await page.fill('input[name="password"]', CREDENTIALS.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForTimeout(3000);

    const afterLoginUrl = page.url();
    if (afterLoginUrl.includes('login/index.php')) {
        console.log('[LOGIN] ERRORE - Login fallito');
        await browser.close();
        return [];
    }
    console.log('[LOGIN] OK - Accesso effettuato');

    const results = [];
    let currentPlugin = '';

    // Verifica ogni pagina
    for (const check of HEALTH_CHECKS) {
        // Intestazione per nuovo plugin
        if (check.plugin !== currentPlugin) {
            currentPlugin = check.plugin;
            console.log(`\n${'─'.repeat(50)}`);
            console.log(`📦 ${currentPlugin.toUpperCase()}`);
            console.log('─'.repeat(50));
        }

        try {
            await page.goto(`${BASE_URL}${check.url}`, {
                waitUntil: 'domcontentloaded',
                timeout: 30000
            });
            await page.waitForTimeout(1500);

            // Verifica redirect al login
            const currentUrl = page.url();
            if (currentUrl.includes('login/index.php')) {
                results.push({
                    name: check.name,
                    plugin: check.plugin,
                    priority: check.priority,
                    status: 'ERROR',
                    error: 'Redirect al login'
                });
                console.log(`  ❌ ${check.name}: REDIRECT LOGIN`);
                continue;
            }

            // Verifica elementi nella pagina (ricerca nel body text)
            const pageContent = await page.content();
            const pageText = pageContent.toLowerCase();

            let foundCount = 0;
            for (const keyword of check.checks) {
                if (pageText.includes(keyword.toLowerCase())) {
                    foundCount++;
                }
            }

            // Verifica errori Moodle
            const errors = await page.$$('.alert-danger, .error, .errorbox, .notifyproblem');
            const hasErrors = errors.length > 0;

            // Screenshot
            const screenshotName = `${check.plugin}_${check.name.replace(/\s+/g, '_').toLowerCase()}.png`;
            await page.screenshot({ path: `./screenshots/${screenshotName}` });

            // Determina status
            let status = 'OK';
            if (hasErrors) {
                status = 'ERROR';
            } else if (foundCount < check.checks.length) {
                status = 'WARN';
            }

            results.push({
                name: check.name,
                plugin: check.plugin,
                priority: check.priority,
                status,
                found: `${foundCount}/${check.checks.length}`,
                hasErrors,
                screenshot: screenshotName
            });

            const icon = status === 'OK' ? '✅' : status === 'WARN' ? '⚠️' : '❌';
            console.log(`  ${icon} ${check.name}: ${status} (${foundCount}/${check.checks.length})`);

        } catch (error) {
            results.push({
                name: check.name,
                plugin: check.plugin,
                priority: check.priority,
                status: 'ERROR',
                error: error.message
            });
            console.log(`  ❌ ${check.name}: ERROR - ${error.message.substring(0, 50)}`);
        }
    }

    await browser.close();

    // Riepilogo per plugin
    console.log('\n' + '='.repeat(70));
    console.log('RIEPILOGO PER PLUGIN');
    console.log('='.repeat(70));

    for (const plugin of uniquePlugins) {
        const pluginResults = results.filter(r => r.plugin === plugin);
        const ok = pluginResults.filter(r => r.status === 'OK').length;
        const warn = pluginResults.filter(r => r.status === 'WARN').length;
        const err = pluginResults.filter(r => r.status === 'ERROR').length;

        let icon = '✅';
        if (err > 0) icon = '❌';
        else if (warn > 0) icon = '⚠️';

        console.log(`${icon} ${plugin.padEnd(20)} OK:${ok} WARN:${warn} ERR:${err}`);
    }

    // Riepilogo totale
    console.log('\n' + '='.repeat(70));
    console.log('RIEPILOGO TOTALE');
    console.log('='.repeat(70));

    const totalOk = results.filter(r => r.status === 'OK').length;
    const totalWarn = results.filter(r => r.status === 'WARN').length;
    const totalErr = results.filter(r => r.status === 'ERROR').length;

    console.log(`\nPlugin: ${uniquePlugins.length}`);
    console.log(`Pagine: ${results.length}`);
    console.log(`\n  ✅ OK:    ${totalOk}`);
    console.log(`  ⚠️  WARN:  ${totalWarn}`);
    console.log(`  ❌ ERROR: ${totalErr}`);

    if (totalErr > 0) {
        console.log('\n🔴 ERRORI DA VERIFICARE:');
        results.filter(r => r.status === 'ERROR').forEach(r => {
            console.log(`   - ${r.plugin}/${r.name}: ${r.error || 'Errore pagina'}`);
        });
    }

    console.log('\nScreenshots salvati in ./screenshots/');
    console.log('='.repeat(70));

    return results;
}

runHealthCheck().catch(console.error);
