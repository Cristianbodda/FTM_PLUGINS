/**
 * FTM Manual Screenshots Capture Script
 *
 * Cattura automaticamente tutti gli screenshot per i manuali FTM
 *
 * Uso: node capture_manual_screenshots.mjs
 *
 * Richiede: npm install playwright
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// ============================================
// CONFIGURAZIONE - MODIFICA QUESTI VALORI
// ============================================

const CONFIG = {
    baseUrl: 'https://test-urc.hizuvala.myhostpoint.ch',

    // Credenziali Coach
    coach: {
        username: 'roberto.bravo',
        password: '123Roberto*'
    },

    // Credenziali Segreteria
    segreteria: {
        username: 'admin_urc_test',
        password: 'Brain666*'
    },

    // Cartella output
    outputDir: join(__dirname, '..', 'docs', 'manuali', 'screenshots'),

    // Viewport - Schermo intero
    viewport: { width: 1920, height: 1080 },

    // Timeout
    timeout: 30000
};

// ============================================
// LISTA SCREENSHOT DA CATTURARE
// ============================================

const SCREENSHOTS = {
    general: [
        { name: 'login', url: '/login/index.php', selector: '#login', description: 'Pagina login' },
    ],

    coach: [
        { name: 'dashboard_coach', url: '/local/coachmanager/coach_dashboard_v2.php', description: 'Dashboard Coach V2' },
        { name: 'dashboard_barra', url: '/local/coachmanager/coach_dashboard_v2.php', selector: '.filters-row, .view-controls', description: 'Barra filtri' },
    ],

    cpurc: [
        { name: 'dashboard_cpurc', url: '/local/ftm_cpurc/index.php', description: 'Dashboard CPURC' },
        { name: 'student_card', url: '/local/ftm_cpurc/student_card.php?id=1', description: 'Scheda studente' },
        { name: 'tab_anagrafica', url: '/local/ftm_cpurc/student_card.php?id=1&tab=anagrafica', description: 'Tab anagrafica' },
        { name: 'tab_percorso', url: '/local/ftm_cpurc/student_card.php?id=1&tab=percorso', description: 'Tab percorso' },
        { name: 'tab_assenze', url: '/local/ftm_cpurc/student_card.php?id=1&tab=assenze', description: 'Tab assenze' },
        { name: 'tab_stage', url: '/local/ftm_cpurc/student_card.php?id=1&tab=stage', description: 'Tab stage' },
        { name: 'report', url: '/local/ftm_cpurc/report.php?id=1', description: 'Pagina report' },
        { name: 'export_word_bulk', url: '/local/ftm_cpurc/export_word_bulk.php', description: 'Export Word massivo' },
    ],

    scheduler: [
        { name: 'calendario', url: '/local/ftm_scheduler/index.php', description: 'Calendario FTM' },
    ],

    competenze: [
        { name: 'report_competenze', url: '/local/competencymanager/student_report.php?userid=2&courseid=2', description: 'Report competenze' },
    ]
};

// ============================================
// FUNZIONI HELPER
// ============================================

async function ensureDir(dir) {
    if (!existsSync(dir)) {
        mkdirSync(dir, { recursive: true });
    }
}

async function login(page, credentials) {
    console.log(`\n🔐 Login come: ${credentials.username}`);

    await page.goto(`${CONFIG.baseUrl}/login/index.php`);
    await page.waitForLoadState('networkidle');

    // Compila form login
    await page.fill('#username', credentials.username);
    await page.fill('#password', credentials.password);
    await page.click('#loginbtn');

    // Attendi redirect
    await page.waitForLoadState('networkidle');

    // Verifica login riuscito
    const url = page.url();
    if (url.includes('/login/')) {
        throw new Error('Login fallito - verifica credenziali');
    }

    console.log('✅ Login riuscito');
}

async function captureScreenshot(page, config, outputPath) {
    const { name, url, selector, description } = config;
    const fullUrl = `${CONFIG.baseUrl}${url}`;

    console.log(`📸 Cattura: ${name} - ${description}`);

    try {
        await page.goto(fullUrl, { timeout: CONFIG.timeout });
        await page.waitForLoadState('networkidle');

        // Attendi un po' per caricamento completo
        await page.waitForTimeout(1000);

        const filePath = join(outputPath, `${name}.png`);

        if (selector) {
            // Screenshot di elemento specifico
            const element = await page.$(selector);
            if (element) {
                await element.screenshot({ path: filePath });
            } else {
                // Fallback a pagina intera
                await page.screenshot({ path: filePath, fullPage: false });
            }
        } else {
            // Screenshot pagina intera
            await page.screenshot({ path: filePath, fullPage: false });
        }

        console.log(`   ✅ Salvato: ${filePath}`);
        return true;

    } catch (error) {
        console.log(`   ❌ Errore: ${error.message}`);
        return false;
    }
}

// ============================================
// MAIN
// ============================================

async function main() {
    console.log('='.repeat(60));
    console.log('FTM - Cattura Screenshot per Manuali');
    console.log('='.repeat(60));

    // Verifica configurazione
    if (CONFIG.coach.username === 'INSERISCI_USERNAME_COACH') {
        console.error('\n❌ ERRORE: Devi configurare le credenziali nel file!');
        console.log('Apri capture_manual_screenshots.mjs e modifica la sezione CONFIG');
        process.exit(1);
    }

    // Crea cartella output
    await ensureDir(CONFIG.outputDir);
    console.log(`\n📁 Output: ${CONFIG.outputDir}`);

    // Avvia browser a SCHERMO INTERO
    console.log('\n🌐 Avvio browser a schermo intero...');
    const browser = await chromium.launch({
        headless: false, // Visibile per debug
        slowMo: 100,
        args: ['--start-maximized'] // Avvia massimizzato
    });

    const context = await browser.newContext({
        viewport: null, // Usa dimensioni finestra (schermo intero)
        locale: 'it-IT',
        screen: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    let successCount = 0;
    let failCount = 0;

    try {
        // Screenshot login (senza login)
        console.log('\n--- Screenshot Generali ---');
        await page.goto(`${CONFIG.baseUrl}/login/index.php`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({
            path: join(CONFIG.outputDir, 'login.png'),
            fullPage: false
        });
        console.log('📸 login.png salvato');
        successCount++;

        // Login come Coach
        await login(page, CONFIG.coach);

        // Screenshot Coach
        console.log('\n--- Screenshot Coach ---');
        for (const shot of SCREENSHOTS.coach) {
            const success = await captureScreenshot(page, shot, CONFIG.outputDir);
            if (success) successCount++; else failCount++;
        }

        // Screenshot Scheduler
        console.log('\n--- Screenshot Scheduler ---');
        for (const shot of SCREENSHOTS.scheduler) {
            const success = await captureScreenshot(page, shot, CONFIG.outputDir);
            if (success) successCount++; else failCount++;
        }

        // Logout
        await page.goto(`${CONFIG.baseUrl}/login/logout.php`);
        await page.waitForLoadState('networkidle');

        // Login come Segreteria
        await login(page, CONFIG.segreteria);

        // Screenshot CPURC
        console.log('\n--- Screenshot CPURC/Segreteria ---');
        for (const shot of SCREENSHOTS.cpurc) {
            const success = await captureScreenshot(page, shot, CONFIG.outputDir);
            if (success) successCount++; else failCount++;
        }

        // Screenshot Competenze
        console.log('\n--- Screenshot Competenze ---');
        for (const shot of SCREENSHOTS.competenze) {
            const success = await captureScreenshot(page, shot, CONFIG.outputDir);
            if (success) successCount++; else failCount++;
        }

    } catch (error) {
        console.error(`\n❌ Errore fatale: ${error.message}`);
    }

    // Chiudi browser
    await browser.close();

    // Riepilogo
    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO');
    console.log('='.repeat(60));
    console.log(`✅ Successi: ${successCount}`);
    console.log(`❌ Falliti: ${failCount}`);
    console.log(`📁 Screenshot salvati in: ${CONFIG.outputDir}`);
    console.log('='.repeat(60));
}

main().catch(console.error);
