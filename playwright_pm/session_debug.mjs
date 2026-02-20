import { chromium } from "playwright";
import path from "path";

const BASE_URL = "https://test-urc.hizuvala.myhostpoint.ch";
const SCREENSHOTS_DIR = "./screenshots";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);

async function main() {
    console.log("=== Session Debug Test ===");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    
    try {
        console.log("Going to login page...");
        await page.goto(BASE_URL + "/login/index.php");
        await page.waitForLoadState("networkidle");
        
        const title1 = await page.title();
        console.log("Title before login:", title1);
        
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "session_" + timestamp + "_01_before.png"), fullPage: true });

        console.log("Filling credentials...");
        await page.fill("#username", "admin");
        await page.fill("#password", "Admin123!");
        
        console.log("Clicking login button...");
        await page.click("#loginbtn");
        
        // Wait longer for login to complete
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(3000);
        
        const title2 = await page.title();
        console.log("Title after login:", title2);
        
        const url2 = page.url();
        console.log("URL after login:", url2);
        
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "session_" + timestamp + "_02_after.png"), fullPage: true });

        // Check for any error messages
        const errorElements = await page.locator(".alert-danger, .loginerrors, #loginerrormessage").all();
        console.log("Error elements:", errorElements.length);
        for (const el of errorElements) {
            const text = await el.textContent();
            console.log("  Error:", text);
        }

        // Check cookies
        const cookies = await context.cookies();
        console.log("Cookies:", cookies.length);
        for (const cookie of cookies) {
            console.log("  -", cookie.name, ":", cookie.value.substring(0, 20) + "...");
        }

        // Try to go to a protected page
        console.log("Going to FTM Hub...");
        await page.goto(BASE_URL + "/local/ftm_hub/index.php");
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(2000);
        
        const title3 = await page.title();
        console.log("FTM Hub title:", title3);
        
        const url3 = page.url();
        console.log("FTM Hub URL:", url3);
        
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "session_" + timestamp + "_03_hub.png"), fullPage: true });

        // Check page content
        const pageText = await page.textContent("body");
        console.log("Page text length:", pageText.length);
        console.log("Contains login form:", pageText.includes("username") && pageText.includes("password"));
        console.log("Contains FTM:", pageText.includes("FTM"));

        console.log("=== Done ===");

    } catch (error) {
        console.error("Error:", error.message);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, "session_" + timestamp + "_error.png"), fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
