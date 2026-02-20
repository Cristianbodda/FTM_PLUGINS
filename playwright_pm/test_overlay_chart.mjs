/**
 * Test Overlay Chart Fix - Student Report
 */

import { chromium } from 'playwright';
import path from 'path';

const BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch';
const SCREENSHOTS_DIR = './screenshots';
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);

async function main() {
    console.log('=== Test Overlay Chart Fix ===');
    console.log('Timestamp:', timestamp);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    try {
        console.log('Step 1: Logging in...');
        await page.goto(BASE_URL + '/login/index.php');
        await page.fill('#username', 'admin');
        await page.fill('#password', 'Admin123!');
        await page.click('#loginbtn');
        await page.waitForLoadState('networkidle');
        console.log('   Login successful');

        console.log('Step 2: Finding students...');
        await page.goto(BASE_URL + '/local/coachmanager/coach_dashboard_v2.php');
        await page.waitForLoadState('networkidle');
        
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_' + timestamp + '_01_dashboard.png')
        });

        const reportLinks = await page.locator('a[href*="student_report.php"]').all();
        console.log('   Found', reportLinks.length, 'report links');

        let url = reportLinks.length > 0 
            ? await reportLinks[0].getAttribute('href')
            : '/local/competencymanager/student_report.php?userid=3&courseid=2';

        console.log('Step 3: Opening Student Report:', url);
        if (!url.startsWith('http')) url = BASE_URL + url;
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_' + timestamp + '_02_report.png'),
            fullPage: true
        });

        console.log('Step 4: Checking checkboxes...');
        const quizCb = await page.locator('input[name="quiz_ids[]"], input[type="checkbox"][name*="quiz"]').all();
        console.log('   Quiz checkboxes:', quizCb.length);

        let overlayExists = false, overlayChecked = false;
        try {
            const cb = page.locator('input[name="show_overlay"], input#show_overlay');
            overlayExists = await cb.isVisible();
            if (overlayExists) overlayChecked = await cb.isChecked();
            console.log('   Overlay exists:', overlayExists, 'checked:', overlayChecked);
        } catch (e) { console.log('   Overlay not found'); }

        console.log('Step 5: Submitting form...');
        try {
            const btn = page.locator('button[type="submit"], input[type="submit"]').first();
            if (await btn.isVisible()) {
                await btn.click();
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(2000);
            }
        } catch (e) {}

        const canvas = await page.locator('canvas').all();
        console.log('Step 6: Canvas elements:', canvas.length);

        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_' + timestamp + '_03_final.png'),
            fullPage: true
        });

        console.log('=== Summary ===');
        console.log('Quiz checkboxes:', quizCb.length);
        console.log('Overlay exists:', overlayExists);
        console.log('Overlay checked:', overlayChecked);
        console.log('Canvas elements:', canvas.length);

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({
            path: path.join(SCREENSHOTS_DIR, 'overlay_test_' + timestamp + '_error.png'),
            fullPage: true
        });
    } finally {
        await browser.close();
        console.log('Done.');
    }
}

main().catch(console.error);

