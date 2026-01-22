/**
 * Visualizza le due pagine colloqui per confronto
 */
import { chromium } from 'playwright';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const CREDENTIALS = { username: 'admin_urc_test', password: 'Brain666*' };

async function viewColloqui() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

    // Login
    console.log('Login...');
    await page.goto(`${BASE_URL}/login/index.php`);
    await page.waitForTimeout(1000);
    await page.fill('input[name="username"]', CREDENTIALS.username);
    await page.fill('input[name="password"]', CREDENTIALS.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForTimeout(3000);

    // Pagina 1: competencymanager/reports.php (SORGENTE)
    console.log('\n=== PAGINA 1: competencymanager/reports.php ===');
    await page.goto(`${BASE_URL}/local/competencymanager/reports.php?studentid=79&courseid=13`, {
        waitUntil: 'domcontentloaded'
    });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: './screenshots/colloqui_source.png', fullPage: true });
    console.log('Screenshot: colloqui_source.png');

    // Pagina 2: coachmanager/reports_v2.php (DESTINAZIONE)
    console.log('\n=== PAGINA 2: coachmanager/reports_v2.php ===');
    await page.goto(`${BASE_URL}/local/coachmanager/reports_v2.php?studentid=24`, {
        waitUntil: 'domcontentloaded'
    });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: './screenshots/colloqui_dest.png', fullPage: true });
    console.log('Screenshot: colloqui_dest.png');

    await browser.close();
    console.log('\nFatto! Screenshots salvati.');
}

viewColloqui().catch(console.error);
