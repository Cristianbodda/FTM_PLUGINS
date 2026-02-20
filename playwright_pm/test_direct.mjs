/**
 * Direct test of student report page
 */
import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Direct Student Report Test ===");
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

        // Go directly to site administration users
        console.log("Getting users from site admin...");
        await page.goto(BASE_URL + "/admin/user.php");
        await page.waitForLoadState("networkidle");
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "direct_" + timestamp + "_01_users.png"), fullPage: true });

        // Find all user links
        const allLinks = await page.locator("a").all();
        const userIds = [];
        for (const link of allLinks) {
            const href = await link.getAttribute("href");
            if (href && href.includes("/user/view.php")) {
                const match = href.match(/id=(d+)/);
                if (match && !userIds.includes(match[1]) && match[1] !== "1") {
                    userIds.push(match[1]);
                }
            }
        }
        console.log("User IDs found:", userIds.slice(0, 10));

        // Go to course management to find courses
        console.log("Getting courses...");
        await page.goto(BASE_URL + "/course/management.php");
        await page.waitForLoadState("networkidle");
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "direct_" + timestamp + "_02_courses.png"), fullPage: true });

        // Find course links
        const courseIds = [];
        const allLinks2 = await page.locator("a").all();
        for (const link of allLinks2) {
            const href = await link.getAttribute("href");
            if (href && href.includes("/course/view.php")) {
                const match = href.match(/id=(d+)/);
                if (match && !courseIds.includes(match[1])) {
                    courseIds.push(match[1]);
                }
            }
        }
        console.log("Course IDs found:", courseIds.slice(0, 10));

        // Try combinations
        const combos = [];
        for (const uid of userIds.slice(0, 5)) {
            for (const cid of courseIds.slice(0, 3)) {
                combos.push({ uid, cid });
            }
        }
        console.log("Testing", combos.length, "user/course combinations");

        for (const combo of combos.slice(0, 10)) {
            const url = BASE_URL + "/local/competencymanager/student_report.php?userid=" + combo.uid + "&courseid=" + combo.cid;
            console.log("Trying uid=" + combo.uid + " cid=" + combo.cid);
            
            try {
                await page.goto(url);
                await page.waitForLoadState("networkidle");
                await page.waitForTimeout(1000);

                // Check for error messages
                const errorMsg = await page.locator(".alert-danger, .errormessage, .error").first();
                try {
                    const hasError = await errorMsg.isVisible();
                    if (hasError) {
                        const errorText = await errorMsg.textContent();
                        console.log("  Error:", errorText.substring(0, 50));
                        continue;
                    }
                } catch (e) {}

                // Check for checkboxes
                const checkboxes = await page.locator("input[type=checkbox]").all();
                if (checkboxes.length > 0) {
                    console.log("  SUCCESS! Found", checkboxes.length, "checkboxes");
                    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "direct_" + timestamp + "_03_report.png"), fullPage: true });
                    
                    // Analyze checkboxes
                    for (const cb of checkboxes.slice(0, 15)) {
                        const name = await cb.getAttribute("name");
                        const id = await cb.getAttribute("id");
                        const checked = await cb.isChecked();
                        console.log("    CB:", name || id, "checked:", checked);
                    }

                    // Check page for Sovrapposizione
                    const pageText = await page.textContent("body");
                    console.log("  Sovrapposizione:", pageText.includes("Sovrapposizione") || pageText.includes("overlay"));

                    // Check for SVG radar
                    const svgs = await page.locator("svg").all();
                    console.log("  SVG elements:", svgs.length);

                    // Check for canvas
                    const canvas = await page.locator("canvas").all();
                    console.log("  Canvas elements:", canvas.length);

                    break;
                }
            } catch (e) {
                console.log("  Error:", e.message.substring(0, 50));
            }
        }

        console.log("=== Test Complete ===");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "direct_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
