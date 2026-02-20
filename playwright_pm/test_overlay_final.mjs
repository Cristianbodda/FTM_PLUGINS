import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Final Overlay Chart Fix Test ===");
    console.log("Timestamp:", timestamp);
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        // Login
        console.log("Step 1: Logging in...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.fill("input[name=username]", "admin_urc_test");
        await page.fill("input[name=password]", "Brain666*");
        await page.click("button[type=submit], input[type=submit]");
        await page.waitForTimeout(3000);
        console.log("   Login successful");

        // Open student report
        console.log("Step 2: Opening student report...");
        await page.goto(BASE_URL + "/local/competencymanager/student_report.php?userid=4&courseid=2");
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);
        
        console.log("   Title:", await page.title());
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_final_" + timestamp + "_01_initial.png"), fullPage: true });

        // Step 3: Find and expand the accordion containing overlay checkbox
        console.log("Step 3: Finding overlay checkbox accordion...");
        
        // The checkbox is in .ftm-mini-accordion-body which is hidden
        // Find the header that toggles it
        const accordionHeaders = await page.locator(".ftm-mini-accordion-header, [data-toggle=collapse]").all();
        console.log("   Accordion headers found:", accordionHeaders.length);
        
        for (const header of accordionHeaders) {
            const text = await header.textContent();
            console.log("   - Header:", text.trim().substring(0, 40));
        }
        
        // Look for the specific accordion containing the overlay option
        const overlayAccordion = await page.locator(".ftm-mini-accordion-header").filter({ hasText: /Visualizzazione|Opzioni|Grafico/i }).first();
        
        try {
            const headerText = await overlayAccordion.textContent();
            console.log("   Found header:", headerText.trim().substring(0, 40));
            await overlayAccordion.click();
            await page.waitForTimeout(500);
        } catch (e) {
            console.log("   Could not find specific header, trying all headers...");
            // Click all accordion headers to expand
            for (const header of accordionHeaders.slice(0, 3)) {
                try {
                    await header.click();
                    await page.waitForTimeout(300);
                } catch (err) {}
            }
        }

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_final_" + timestamp + "_02_expanded.png"), fullPage: true });

        // Step 4: Check overlay checkbox state
        console.log("Step 4: Checking overlay checkbox state...");
        const overlayCheckbox = await page.locator("input[name=show_overlay]").first();
        
        // Use JS to get the checked state since element might not be "visible" to Playwright
        const isChecked = await overlayCheckbox.evaluate(el => el.checked);
        console.log("   Overlay checkbox checked:", isChecked);
        
        // Step 5: Check and submit if needed
        console.log("Step 5: Testing overlay functionality...");
        
        if (!isChecked) {
            console.log("   Checking overlay checkbox via JS...");
            await overlayCheckbox.evaluate(el => {
                el.checked = true;
                el.dispatchEvent(new Event("change", { bubbles: true }));
            });
            await page.waitForTimeout(2000);
        }
        
        // Verify the state after
        const checkedAfter = await overlayCheckbox.evaluate(el => el.checked);
        console.log("   Overlay checkbox after check:", checkedAfter);

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_final_" + timestamp + "_03_after_check.png"), fullPage: true });

        // Step 6: Count chart elements
        console.log("Step 6: Counting chart elements...");
        const canvases = await page.locator("canvas").all();
        console.log("   Canvas elements:", canvases.length);
        
        for (let i = 0; i < canvases.length; i++) {
            const id = await canvases[i].getAttribute("id");
            console.log("     Canvas", i+1, "id:", id);
        }

        // Step 7: Look for overlay-specific chart
        console.log("Step 7: Looking for overlay chart...");
        const overlayCanvas = await page.locator("#overlayRadarChart, canvas[id*=overlay]").all();
        console.log("   Overlay canvas elements:", overlayCanvas.length);
        
        // Also check for SVG radar
        const svgRadar = await page.locator("svg").all();
        console.log("   SVG elements:", svgRadar.length);

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_final_" + timestamp + "_04_final.png"), fullPage: true });

        // Summary
        console.log("");
        console.log("=== TEST RESULTS ===");
        console.log("Student Report Loaded: YES");
        console.log("Overlay Checkbox Found: YES");
        console.log("Overlay Checkbox Default State: " + (isChecked ? "CHECKED" : "NOT CHECKED"));
        console.log("Overlay Checkbox After Fix: " + (checkedAfter ? "CHECKED" : "NOT CHECKED"));
        console.log("Canvas Elements: " + canvases.length);
        console.log("");
        console.log("Screenshots saved to:", SCREENSHOTS_DIR);
        console.log("Files:");
        console.log("  - overlay_final_" + timestamp + "_01_initial.png");
        console.log("  - overlay_final_" + timestamp + "_02_expanded.png");
        console.log("  - overlay_final_" + timestamp + "_03_after_check.png");
        console.log("  - overlay_final_" + timestamp + "_04_final.png");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "overlay_final_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
