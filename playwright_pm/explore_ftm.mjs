import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== FTM Pages Exploration ===");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        console.log("Logging in...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.fill("#username", "admin");
        await page.fill("#password", "Admin123!");
        await page.click("#loginbtn");
        await page.waitForLoadState("networkidle");
        console.log("Login successful");

        const pages = [
            "/local/ftm_hub/index.php",
            "/local/competencymanager/sector_admin.php",
            "/local/coachmanager/coach_dashboard_v2.php",
            "/local/ftm_cpurc/index.php"
        ];

        let foundReportUrl = null;

        for (let i = 0; i < pages.length; i++) {
            const url = BASE_URL + pages[i];
            console.log("Page:", pages[i]);
            
            try {
                const response = await page.goto(url);
                if (!response || response.status() >= 400) continue;
                await page.waitForLoadState("networkidle");
                
                const title = await page.title();
                console.log("  Title:", title.substring(0, 60));

                const studentLinks = await page.locator("a[href*=student_report]").all();
                console.log("  Report links:", studentLinks.length);
                
                for (const link of studentLinks.slice(0, 2)) {
                    const href = await link.getAttribute("href");
                    console.log("    Link:", href ? href.substring(0, 80) : "");
                    if (href && href.includes("student_report")) foundReportUrl = href;
                }

                await page.screenshot({ 
                    path: path.join(SCREENSHOTS_DIR, "ftm_explore_" + timestamp + "_" + (i+1) + ".png"), 
                    fullPage: true 
                });

            } catch (e) {
                console.log("  Error:", e.message.substring(0, 50));
            }
        }

        if (foundReportUrl) {
            console.log("=== Testing report URL ===");
            console.log("URL:", foundReportUrl);
            
            if (!foundReportUrl.startsWith("http")) foundReportUrl = BASE_URL + foundReportUrl;
            
            await page.goto(foundReportUrl);
            await page.waitForLoadState("networkidle");
            await page.waitForTimeout(2000);
            
            await page.screenshot({ 
                path: path.join(SCREENSHOTS_DIR, "ftm_explore_" + timestamp + "_report.png"), 
                fullPage: true 
            });

            const checkboxes = await page.locator("input[type=checkbox]").all();
            console.log("Checkboxes:", checkboxes.length);
            
            for (const cb of checkboxes.slice(0, 15)) {
                const name = await cb.getAttribute("name");
                const id = await cb.getAttribute("id");
                const checked = await cb.isChecked();
                console.log("  CB:", name || id, "checked:", checked);
            }

            const pageHtml = await page.content();
            console.log("Page contains overlay:", pageHtml.includes("overlay"));
            console.log("Page contains Sovrapposizione:", pageHtml.includes("Sovrapposizione"));
            console.log("Page contains show_overlay:", pageHtml.includes("show_overlay"));
            console.log("Page contains canvas:", pageHtml.includes("<canvas"));
        }

        console.log("=== Done ===");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "ftm_explore_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
