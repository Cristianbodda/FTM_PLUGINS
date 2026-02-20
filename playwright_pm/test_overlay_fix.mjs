import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

const CREDENTIALS = {
    username: "admin_urc_test",
    password: "Brain666*"
};

async function main() {
    console.log("=== Overlay Chart Fix Test ===");
    console.log("Timestamp:", timestamp);
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        // Login
        console.log("Logging in...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.fill("input[name=username]", CREDENTIALS.username);
        await page.fill("input[name=password]", CREDENTIALS.password);
        await page.click("button[type=submit], input[type=submit]");
        await page.waitForTimeout(3000);
        
        const title = await page.title();
        console.log("After login title:", title);
        
        if (title.includes("Log in")) {
            console.log("Login failed!");
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_login_fail.png"), fullPage: true });
            return;
        }
        console.log("Login successful");

        // Go to Coach Dashboard V2 to find students
        console.log("Going to Coach Dashboard V2...");
        await page.goto(BASE_URL + "/local/coachmanager/coach_dashboard_v2.php");
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);
        
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_01_dashboard.png"), fullPage: true });
        
        const dashTitle = await page.title();
        console.log("Dashboard title:", dashTitle);

        // Find student report links
        const reportLinks = await page.locator("a[href*=student_report]").all();
        console.log("Report links found:", reportLinks.length);
        
        let foundReport = null;
        for (const link of reportLinks.slice(0, 5)) {
            const href = await link.getAttribute("href");
            console.log("  Link:", href ? href.substring(0, 80) : "");
            if (href && href.includes("student_report")) {
                foundReport = href;
            }
        }

        if (foundReport) {
            console.log("Opening student report:", foundReport);
            if (!foundReport.startsWith("http")) foundReport = BASE_URL + foundReport;
            
            await page.goto(foundReport);
            await page.waitForLoadState("networkidle");
            await page.waitForTimeout(2000);
            
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_02_report.png"), fullPage: true });

            // Analyze checkboxes
            const checkboxes = await page.locator("input[type=checkbox]").all();
            console.log("Checkboxes found:", checkboxes.length);
            
            let overlayCheckbox = null;
            for (const cb of checkboxes) {
                const name = await cb.getAttribute("name");
                const id = await cb.getAttribute("id");
                const checked = await cb.isChecked();
                console.log("  CB:", name || id, "checked:", checked);
                
                if (name === "show_overlay" || (id && id.includes("overlay"))) {
                    overlayCheckbox = cb;
                    console.log("  >>> Found overlay checkbox!");
                }
            }

            // Check page content for overlay-related elements
            const pageContent = await page.content();
            console.log("Page contains overlay:", pageContent.includes("overlay"));
            console.log("Page contains Sovrapposizione:", pageContent.includes("Sovrapposizione"));
            console.log("Page contains show_overlay:", pageContent.includes("show_overlay"));
            console.log("Page contains canvas:", pageContent.includes("<canvas"));
            console.log("Page contains svg:", pageContent.includes("<svg"));

            // Try to find and test the overlay checkbox
            if (overlayCheckbox) {
                const isChecked = await overlayCheckbox.isChecked();
                console.log("Overlay checkbox is checked:", isChecked);
                
                if (isChecked) {
                    console.log("SUCCESS: Overlay checkbox is checked by default!");
                } else {
                    console.log("FAIL: Overlay checkbox is NOT checked by default");
                }
            }

            // Count canvas and SVG elements
            const canvasElements = await page.locator("canvas").all();
            console.log("Canvas elements:", canvasElements.length);
            
            const svgElements = await page.locator("svg").all();
            console.log("SVG elements:", svgElements.length);

            // Final screenshot
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_03_final.png"), fullPage: true });
        } else {
            console.log("No report links found, trying direct URLs...");
            
            // Try some direct combinations
            const testUrls = [
                "/local/competencymanager/student_report.php?userid=4&courseid=2",
                "/local/competencymanager/student_report.php?userid=5&courseid=2",
                "/local/competencymanager/student_report.php?userid=6&courseid=2"
            ];
            
            for (const testUrl of testUrls) {
                console.log("Trying:", testUrl);
                await page.goto(BASE_URL + testUrl);
                await page.waitForLoadState("networkidle");
                await page.waitForTimeout(1000);
                
                const checkboxes = await page.locator("input[type=checkbox]").all();
                if (checkboxes.length > 0) {
                    console.log("Found page with checkboxes!");
                    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_04_direct.png"), fullPage: true });
                    break;
                }
            }
        }

        console.log("=== Test Complete ===");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_fix_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
