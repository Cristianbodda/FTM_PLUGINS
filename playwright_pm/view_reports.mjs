/**
 * FTM Health Check - Report Viewer
 * Mostra lo storico degli health check
 *
 * Uso: node view_reports.mjs
 */

import { readdirSync, readFileSync, existsSync } from 'fs';

const REPORTS_DIR = './reports';
const HISTORY_FILE = './reports/health_history.log';

console.log('='.repeat(60));
console.log('FTM HEALTH CHECK - STORICO REPORT');
console.log('='.repeat(60));

// Mostra history log
if (existsSync(HISTORY_FILE)) {
    console.log('\n📊 STORICO ESECUZIONI:');
    console.log('-'.repeat(50));
    const history = readFileSync(HISTORY_FILE, 'utf-8').trim().split('\n');
    const lastEntries = history.slice(-10); // Ultime 10 esecuzioni

    lastEntries.forEach(line => {
        if (line.includes('ERR:0') && line.includes('WARN:0')) {
            console.log(`✅ ${line}`);
        } else if (line.includes('ERR:')) {
            console.log(`❌ ${line}`);
        } else {
            console.log(`⚠️  ${line}`);
        }
    });

    console.log(`\nTotale esecuzioni: ${history.length}`);
} else {
    console.log('\nNessuno storico disponibile. Esegui prima un health check.');
}

// Mostra ultimi report
if (existsSync(REPORTS_DIR)) {
    const reports = readdirSync(REPORTS_DIR)
        .filter(f => f.endsWith('.json'))
        .sort()
        .reverse()
        .slice(0, 5);

    if (reports.length > 0) {
        console.log('\n📋 ULTIMI 5 REPORT DETTAGLIATI:');
        console.log('-'.repeat(50));

        reports.forEach((file, idx) => {
            try {
                const report = JSON.parse(readFileSync(`${REPORTS_DIR}/${file}`, 'utf-8'));
                const status = report.summary.error > 0 ? '❌' :
                              report.summary.warn > 0 ? '⚠️' : '✅';

                console.log(`\n${idx + 1}. ${status} ${report.timestamp}`);
                console.log(`   File: ${file}`);
                console.log(`   OK: ${report.summary.ok} | WARN: ${report.summary.warn} | ERR: ${report.summary.error}`);

                if (report.checks) {
                    report.checks.forEach(check => {
                        const icon = check.status === 'OK' ? '✓' :
                                    check.status === 'WARN' ? '!' : '✗';
                        console.log(`   ${icon} ${check.name}: ${check.status}`);
                    });
                }
            } catch (e) {
                console.log(`   Errore lettura: ${file}`);
            }
        });
    }
}

// Statistiche
console.log('\n' + '='.repeat(60));
console.log('COMANDI DISPONIBILI:');
console.log('='.repeat(60));
console.log('node ftm_health_check.mjs        - Health check rapido');
console.log('node scheduled_health_check.mjs  - Health check con report');
console.log('node view_reports.mjs            - Visualizza storico');
console.log('node coach_dashboard_test.mjs    - Test Dashboard V2');
console.log('');
