/**
 * Test Overlay Chart Fix - Student Report v2
 */
import { chromium } from 'playwright';
import path from 'path';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const SCREENSHOTS_DIR = './screenshots';
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);

async function main() {
    console.log('=== Test Overlay Chart Fix v2 ===');
    console.log('Timestamp:', timestamp);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        // Login
        console.log('Step 1: Logging in...');
        await page.goto(BASE_URL + '/login/index.php');
        await page.fill('#username', 'admin');
        await page.fill('#password', 'Admin123!');
        await page.click('#loginbtn');
        await page.waitForLoadState('networkidle');
        console.log('   Login successful');

        // Try CPURC dashboard
        console.log('Step 2: Trying CPURC dashboard...');
        await page.goto(BASE_URL + '/local/ftm_cpurc/index.php');
        await page.waitForLoadState('networkidle');
        
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_v2_' + timestamp + '_01_cpurc.png'),
            fullPage: true
        });

        let reportLinks = await page.locator('a[href*="student_report"]').all();
        console.log('   CPURC report links:', reportLinks.length);

        // If not found, try sector admin
        if (reportLinks.length === 0) {
            console.log('Step 2b: Trying sector_admin...');
            await page.goto(BASE_URL + '/local/competencymanager/sector_admin.php');
            await page.waitForLoadState('networkidle');
            
            await page.screenshot({
                path: path.join(SCREENSHOTS_DIR, 'overlay_test_v2_' + timestamp + '_02_sector.png'),
                fullPage: true
            });

            reportLinks = await page.locator('a[href*="student_report"]').all();
            console.log('   Sector admin links:', reportLinks.length);
        }

        // Try test combinations
        let url = null;
        if (reportLinks.length > 0) {
            url = await reportLinks[0].getAttribute('href');
        } else {
            const testUrls = [
                '/local/competencymanager/student_report.php?userid=4&courseid=2',
                '/local/competencymanager/student_report.php?userid=5&courseid=2',
                '/local/competencymanager/student_report.php?userid=6&courseid=2'
            ];
            
            for (const testUrl of testUrls) {
                console.log('   Trying:', testUrl);
                await page.goto(BASE_URL + testUrl);
                await page.waitForLoadState('networkidle');
                
                const cb = await page.locator('input[name="quiz_ids[]"]').all();
                if (cb.length > 0) {
                    url = testUrl;
                    console.log('   Found quiz checkboxes!');
                    break;
                }
            }
        }

        if (url && !url.startsWith('http')) {
            url = BASE_URL + url;
            await page.goto(url);
            await page.waitForLoadState('networkidle');
        }

        await page.waitForTimeout(2000);
        
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_v2_' + timestamp + '_03_report.png'),
            fullPage: true
        });

        // Analyze elements
        console.log('Step 4: Analyzing page...');
        
        const allCb = await page.locator('input[type="checkbox"]').all();
        console.log('   Total checkboxes:', allCb.length);

        for (let i = 0; i < Math.min(10, allCb.length); i++) {
            const name = await allCb[i].getAttribute('name');
            const id = await allCb[i].getAttribute('id');
            const checked = await allCb[i].isChecked();
            console.log('   - CB:', name || id, 'checked:', checked);
        }

        const canvas = await page.locator('canvas').all();
        console.log('   Canvas:', canvas.length);

        const svg = await page.locator('svg').all();
        console.log('   SVG:', svg.length);

        const overlayText = await page.locator('text=Sovrapposizione').all();
        console.log('   Sovrapposizione text:', overlayText.length);

        const overlayCb = await page.locator('#show_overlay, input[name="show_overlay"]').all();
        console.log('   Overlay CB elements:', overlayCb.length);

        if (overlayCb.length > 0) {
            const isChecked = await overlayCb[0].isChecked();
            console.log('   Overlay checked:', isChecked);
        }

        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_v2_' + timestamp + '_04_final.png'),
            fullPage: true
        });

        console.log('=== Done ===');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_v2_' + timestamp + '_error.png'),
            fullPage: true
        });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
