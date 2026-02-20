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
    console.log("=== Detailed Overlay Chart Test ===");
    console.log("Timestamp:", timestamp);
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        // Login
        console.log("Step 1: Logging in...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.fill("input[name=username]", CREDENTIALS.username);
        await page.fill("input[name=password]", CREDENTIALS.password);
        await page.click("button[type=submit], input[type=submit]");
        await page.waitForTimeout(3000);
        console.log("   Login successful");

        // Go directly to student report with known user/course
        console.log("Step 2: Opening student report...");
        await page.goto(BASE_URL + "/local/competencymanager/student_report.php?userid=4&courseid=2");
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);
        
        const reportTitle = await page.title();
        console.log("   Report title:", reportTitle);
        
        await page.screenshot({ 
            path: path.join(SCREENSHOTS_DIR, "overlay_detailed_" + timestamp + "_01_initial.png"), 
            fullPage: true 
        });

        // Step 3: List all checkboxes
        console.log("Step 3: Analyzing checkboxes...");
        const checkboxes = await page.locator("input[type=checkbox]").all();
        console.log("   Total checkboxes:", checkboxes.length);
        
        let overlayCheckboxInfo = null;
        let quizCheckboxes = [];
        
        for (const cb of checkboxes) {
            const name = await cb.getAttribute("name");
            const id = await cb.getAttribute("id");
            const checked = await cb.isChecked();
            
            console.log("   - Name:", name, "ID:", id, "Checked:", checked);
            
            if (name === "show_overlay" || (id && id.includes("overlay"))) {
                overlayCheckboxInfo = { name, id, checked };
                console.log("     >>> This is the overlay checkbox!");
            }
            
            if (name && name.includes("quiz")) {
                quizCheckboxes.push({ name, id, checked });
            }
        }

        // Step 4: Check quiz checkboxes state
        console.log("Step 4: Quiz checkbox analysis...");
        console.log("   Quiz checkboxes found:", quizCheckboxes.length);
        for (const qc of quizCheckboxes) {
            console.log("   -", qc.name, "checked:", qc.checked);
        }

        // Step 5: Check overlay checkbox state
        console.log("Step 5: Overlay checkbox analysis...");
        if (overlayCheckboxInfo) {
            console.log("   Overlay checkbox found!");
            console.log("   Is checked by default:", overlayCheckboxInfo.checked);
            
            if (quizCheckboxes.length > 0 && quizCheckboxes.some(qc => qc.checked)) {
                console.log("   Quiz checkboxes are checked");
                if (overlayCheckboxInfo.checked) {
                    console.log("   RESULT: PASS - Overlay checkbox is checked when quiz selected");
                } else {
                    console.log("   RESULT: FAIL - Overlay checkbox should be checked when quiz selected");
                }
            } else {
                console.log("   No quiz checkboxes are checked yet");
            }
        } else {
            console.log("   Overlay checkbox NOT found on page");
        }

        // Step 6: Check for chart elements
        console.log("Step 6: Chart element analysis...");
        
        const canvasElements = await page.locator("canvas").all();
        console.log("   Canvas elements:", canvasElements.length);
        
        const svgElements = await page.locator("svg").all();
        console.log("   SVG elements:", svgElements.length);

        // Step 7: Check for Sovrapposizione text
        console.log("Step 7: Text analysis...");
        const pageText = await page.textContent("body");
        console.log("   Contains Sovrapposizione:", pageText.includes("Sovrapposizione"));
        console.log("   Contains overlay:", pageText.includes("overlay"));
        console.log("   Contains Grafico:", pageText.includes("Grafico"));

        // Step 8: If quiz checkboxes exist, select one and submit
        console.log("Step 8: Testing form submission...");
        if (quizCheckboxes.length > 0) {
            console.log("   Selecting quiz checkboxes...");
            const quizCbs = await page.locator("input[name*=quiz]").all();
            for (const cb of quizCbs.slice(0, 2)) {
                try {
                    await cb.check();
                } catch (e) {}
            }
            
            const submitBtn = await page.locator("button[type=submit], input[type=submit]").first();
            try {
                if (await submitBtn.isVisible()) {
                    console.log("   Submitting form...");
                    await submitBtn.click();
                    await page.waitForLoadState("networkidle");
                    await page.waitForTimeout(2000);
                    
                    await page.screenshot({ 
                        path: path.join(SCREENSHOTS_DIR, "overlay_detailed_" + timestamp + "_02_after_submit.png"), 
                        fullPage: true 
                    });
                    
                    // Re-check overlay checkbox
                    const overlayCbAfter = await page.locator("input[name=show_overlay], input#show_overlay").first();
                    try {
                        const checkedAfter = await overlayCbAfter.isChecked();
                        console.log("   Overlay checkbox after submit:", checkedAfter);
                    } catch (e) {}
                    
                    const canvasAfter = await page.locator("canvas").all();
                    console.log("   Canvas after submit:", canvasAfter.length);
                    
                    const svgAfter = await page.locator("svg").all();
                    console.log("   SVG after submit:", svgAfter.length);
                }
            } catch (e) {
                console.log("   Submit error:", e.message.substring(0, 50));
            }
        }

        // Final screenshot
        await page.screenshot({ 
            path: path.join(SCREENSHOTS_DIR, "overlay_detailed_" + timestamp + "_03_final.png"), 
            fullPage: true 
        });

        console.log("=== Test Complete ===");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ 
            path: path.join(SCREENSHOTS_DIR, "overlay_detailed_" + timestamp + "_error.png"), 
            fullPage: true 
        });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
