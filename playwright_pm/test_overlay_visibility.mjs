import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Overlay Visibility Test ===");
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
        
        console.log("Title:", await page.title());
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_vis_" + timestamp + "_01.png"), fullPage: true });

        // Check overlay checkbox details
        const overlayCheckbox = await page.locator("input[name=show_overlay]");
        const count = await overlayCheckbox.count();
        console.log("Overlay checkbox count:", count);
        
        if (count > 0) {
            const isVisible = await overlayCheckbox.first().isVisible();
            console.log("Is visible:", isVisible);
            
            // Get bounding box
            try {
                const box = await overlayCheckbox.first().boundingBox();
                console.log("Bounding box:", box);
            } catch (e) {
                console.log("Bounding box error:", e.message.substring(0, 50));
            }
            
            // Get parent element
            const parent = await overlayCheckbox.first().evaluate(el => {
                let p = el.parentElement;
                let info = [];
                for (let i = 0; i < 5 && p; i++) {
                    info.push({
                        tag: p.tagName,
                        class: p.className,
                        id: p.id,
                        display: window.getComputedStyle(p).display,
                        visibility: window.getComputedStyle(p).visibility
                    });
                    p = p.parentElement;
                }
                return info;
            });
            console.log("Parent chain:");
            for (const p of parent) {
                console.log("  -", p.tag, "class:", p.class.substring(0, 30), "display:", p.display, "visibility:", p.visibility);
            }
            
            // Look for collapse buttons/accordions
            const collapseButtons = await page.locator("[data-toggle=collapse], [data-bs-toggle=collapse], .accordion-button, button.collapsed").all();
            console.log("Collapse buttons:", collapseButtons.length);
            
            // Try clicking collapse buttons
            for (const btn of collapseButtons.slice(0, 5)) {
                const text = await btn.textContent();
                console.log("  - Button:", text.trim().substring(0, 30));
                if (text.toLowerCase().includes("overlay") || text.toLowerCase().includes("grafico") || text.toLowerCase().includes("sovrapposizione")) {
                    console.log("    Clicking to expand...");
                    await btn.click();
                    await page.waitForTimeout(500);
                }
            }
            
            // Look for card headers that might be collapsible
            const cardHeaders = await page.locator(".card-header, .collapsible-header").all();
            console.log("Card headers:", cardHeaders.length);
            
            for (const h of cardHeaders.slice(0, 5)) {
                const text = await h.textContent();
                console.log("  - Header:", text.trim().substring(0, 40));
            }
            
            // Try to scroll to the overlay checkbox
            try {
                await overlayCheckbox.first().scrollIntoViewIfNeeded();
                await page.waitForTimeout(500);
            } catch (e) {}
            
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_vis_" + timestamp + "_02.png"), fullPage: true });
            
            // Try using JavaScript to check it
            console.log("Attempting JS click...");
            await overlayCheckbox.first().evaluate(el => el.click());
            await page.waitForTimeout(1000);
            
            const checkedNow = await overlayCheckbox.first().isChecked();
            console.log("Checked after JS click:", checkedNow);
            
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_vis_" + timestamp + "_03.png"), fullPage: true });
        }

        console.log("=== Done ===");
    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_vis_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
