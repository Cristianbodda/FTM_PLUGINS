import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Overlay Chart Test ===");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        console.log("Logging in...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.fill("input[name=username]", "admin_urc_test");
        await page.fill("input[name=password]", "Brain666*");
        await page.click("button[type=submit], input[type=submit]");
        await page.waitForTimeout(3000);

        console.log("Opening student report...");
        await page.goto(BASE_URL + "/local/competencymanager/student_report.php?userid=4&courseid=2");
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);
        
        const reportTitle = await page.title();
        console.log("Title:", reportTitle);

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_test_" + timestamp + "_01.png"), fullPage: true });

        const overlayCheckbox = await page.locator("input[name=show_overlay]").first();
        const isChecked = await overlayCheckbox.isChecked();
        console.log("Overlay checkbox checked:", isChecked);

        console.log("Checking overlay checkbox...");
        await overlayCheckbox.check();

        const submitBtn = await page.locator("button[type=submit], input[type=submit]").first();
        console.log("Submitting...");
        await submitBtn.click();
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_test_" + timestamp + "_02.png"), fullPage: true });

        const canvases = await page.locator("canvas").all();
        console.log("Canvas elements after submit:", canvases.length);

        const overlayAfter = await page.locator("input[name=show_overlay]").first();
        const checkedAfter = await overlayAfter.isChecked();
        console.log("Overlay checkbox after submit:", checkedAfter);

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_test_" + timestamp + "_03_final.png"), fullPage: true });

        console.log("=== Done ===");
    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_test_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
