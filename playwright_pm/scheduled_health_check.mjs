/**
 * FTM PLUGINS - Scheduled Health Check
 * Esegue health check e salva report con timestamp
 *
 * Uso manuale: node scheduled_health_check.mjs
 * Uso schedulato: Aggiungi a Windows Task Scheduler
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync, appendFileSync } from 'fs';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const CREDENTIALS = {
    username: 'admin_urc_test',
    password: 'Brain666*'
};

// Cartelle output
const SCREENSHOTS_DIR = './screenshots';
const REPORTS_DIR = './reports';
const HISTORY_FILE = './reports/health_history.log';

// Crea cartelle se non esistono
[SCREENSHOTS_DIR, REPORTS_DIR].forEach(dir => {
    if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
});

// Plugin da verificare
const HEALTH_CHECKS = [
    {
        name: 'Coach Dashboard V2',
        url: '/local/coachmanager/coach_dashboard_v2.php',
        checks: ['view-controls', 'zoom-controls', 'filters-body'],
        priority: 'HIGH'
    },
    {
        name: 'Coach Dashboard',
        url: '/local/coachmanager/coach_dashboard.php',
        checks: ['dashboard-header', 'student-card'],
        priority: 'MEDIUM'
    },
    {
        name: 'FTM Scheduler',
        url: '/local/ftm_scheduler/index.php',
        checks: ['calendar'],
        priority: 'MEDIUM'
    },
    {
        name: 'Setup Universale',
        url: '/local/competencyxmlimport/setup_universale.php',
        checks: ['step-indicator'],
        priority: 'MEDIUM'
    }
];

function getTimestamp() {
    const now = new Date();
    return now.toISOString().replace(/[:.]/g, '-').slice(0, 19);
}

function formatDate() {
    return new Date().toLocaleString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

async function runScheduledCheck() {
    const timestamp = getTimestamp();
    const dateStr = formatDate();

    console.log(`\n${'='.repeat(60)}`);
    console.log(`FTM HEALTH CHECK - ${dateStr}`);
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    let results = {
        timestamp: dateStr,
        checks: [],
        summary: { ok: 0, warn: 0, error: 0 }
    };

    try {
        // Login
        console.log('\n[LOGIN]...');
        await page.goto(`${BASE_URL}/login/index.php`);
        await page.waitForTimeout(1000);
        await page.fill('input[name="username"]', CREDENTIALS.username);
        await page.fill('input[name="password"]', CREDENTIALS.password);
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForTimeout(3000);

        const afterLoginUrl = page.url();
        if (afterLoginUrl.includes('login/index.php')) {
            throw new Error('Login fallito');
        }
        console.log('[LOGIN] OK');

        // Check ogni plugin
        for (const check of HEALTH_CHECKS) {
            console.log(`\n[${check.name}]...`);

            try {
                await page.goto(`${BASE_URL}${check.url}`, {
                    waitUntil: 'domcontentloaded',
                    timeout: 30000
                });
                await page.waitForTimeout(2000);

                // Verifica elementi
                let found = 0;
                for (const selector of check.checks) {
                    const el = await page.$(`[class*="${selector}"], #${selector}, .${selector}`);
                    if (el) found++;
                }

                // Screenshot con timestamp
                const screenshotName = `${timestamp}_${check.name.replace(/\s+/g, '_').toLowerCase()}.png`;
                await page.screenshot({ path: `${SCREENSHOTS_DIR}/${screenshotName}` });

                // Verifica errori
                const errors = await page.$$('.alert-danger, .error, .errorbox');
                const hasErrors = errors.length > 0;

                const status = found === check.checks.length && !hasErrors ? 'OK' : 'WARN';
                results.checks.push({
                    name: check.name,
                    status,
                    found: `${found}/${check.checks.length}`,
                    hasErrors,
                    screenshot: screenshotName
                });

                if (status === 'OK') results.summary.ok++;
                else results.summary.warn++;

                console.log(`[${check.name}] ${status} (${found}/${check.checks.length})`);

            } catch (err) {
                results.checks.push({
                    name: check.name,
                    status: 'ERROR',
                    error: err.message
                });
                results.summary.error++;
                console.log(`[${check.name}] ERROR: ${err.message}`);
            }
        }

    } catch (err) {
        console.error('Errore generale:', err.message);
        results.error = err.message;
    }

    await browser.close();

    // Salva report JSON
    const reportFile = `${REPORTS_DIR}/report_${timestamp}.json`;
    writeFileSync(reportFile, JSON.stringify(results, null, 2));
    console.log(`\nReport salvato: ${reportFile}`);

    // Aggiungi a history log
    const historyLine = `${dateStr} | OK:${results.summary.ok} WARN:${results.summary.warn} ERR:${results.summary.error}\n`;
    appendFileSync(HISTORY_FILE, historyLine);

    // Riepilogo
    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO');
    console.log('='.repeat(60));
    console.log(`OK:    ${results.summary.ok}`);
    console.log(`WARN:  ${results.summary.warn}`);
    console.log(`ERROR: ${results.summary.error}`);

    // Alert se ci sono problemi
    if (results.summary.error > 0 || results.summary.warn > 0) {
        console.log('\n⚠️  ATTENZIONE: Verificare i problemi rilevati!');
    } else {
        console.log('\n✅ Tutti i controlli OK!');
    }

    return results;
}

runScheduledCheck().catch(console.error);
