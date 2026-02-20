/**
 * Explore available students and courses
 */
import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Exploring Students and Courses ===");
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

        console.log("Checking courses...");
        await page.goto(BASE_URL + "/course/index.php");
        await page.waitForLoadState("networkidle");
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "explore_" + timestamp + "_01.png"), fullPage: true });

        const courseLinks = await page.locator("a.coursename").all();
        console.log("Courses:", courseLinks.length);
        for (let i = 0; i < Math.min(5, courseLinks.length); i++) {
            const text = await courseLinks[i].textContent();
            const href = await courseLinks[i].getAttribute("href");
            console.log(" -", text.trim().substring(0,50));
        }

        if (courseLinks.length > 0) {
            const href = await courseLinks[0].getAttribute("href");
            console.log("Going to:", href);
            await page.goto(href);
            await page.waitForLoadState("networkidle");
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "explore_" + timestamp + "_02.png"), fullPage: true });

            const courseMatch = href.match(/id=(d+)/);
            const courseid = courseMatch ? courseMatch[1] : "2";
            console.log("Course ID:", courseid);

            await page.goto(BASE_URL + "/user/index.php?id=" + courseid);
            await page.waitForLoadState("networkidle");
            await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "explore_" + timestamp + "_03.png"), fullPage: true });

            const userLinks = await page.locator("a.fullname").all();
            console.log("Users:", userLinks.length);
            const userIds = [];
            for (let i = 0; i < Math.min(10, userLinks.length); i++) {
                const h = await userLinks[i].getAttribute("href");
                const t = await userLinks[i].textContent();
                const m = h ? h.match(/id=(d+)/) : null;
                if (m && !userIds.includes(m[1])) {
                    userIds.push(m[1]);
                    console.log(" -", t.trim().substring(0,30), ": uid=", m[1]);
                }
            }

            for (const uid of userIds.slice(0, 3)) {
                const url = BASE_URL + "/local/competencymanager/student_report.php?userid=" + uid + "&courseid=" + courseid;
                console.log("Trying:", url);
                await page.goto(url);
                await page.waitForLoadState("networkidle");
                await page.waitForTimeout(1000);

                const checkboxes = await page.locator("input[type=checkbox]").all();
                console.log("  Checkboxes:", checkboxes.length);

                if (checkboxes.length > 0) {
                    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "explore_" + timestamp + "_report.png"), fullPage: true });
                    for (const cb of checkboxes.slice(0, 8)) {
                        const n = await cb.getAttribute("name");
                        const id = await cb.getAttribute("id");
                        const ch = await cb.isChecked();
                        console.log("   CB:", n || id, "checked:", ch);
                    }
                    break;
                }
            }
        }

        console.log("=== Done ===");
    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "explore_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
