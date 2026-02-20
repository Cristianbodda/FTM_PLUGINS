"""
Genera screenshot della piattaforma FTM Moodle usando Playwright.
Naviga nelle pagine principali e cattura le schermate per i PowerPoint.
Selettori basati sul codice PHP reale della piattaforma.
"""
import asyncio
import os
from playwright.async_api import async_playwright

BASE_URL = 'https://test-urc.hizuvala.myhostpoint.ch'
USERNAME = 'admin'
PASSWORD = 'Brain666*'
OUT_DIR = os.path.join(os.path.dirname(__file__), 'screenshots')

os.makedirs(OUT_DIR, exist_ok=True)

async def login(page):
    """Login to Moodle."""
    print('  Navigazione login...')
    await page.goto(f'{BASE_URL}/login/index.php', wait_until='networkidle', timeout=30000)
    await page.fill('#username', USERNAME)
    await page.fill('#password', PASSWORD)
    await page.click('#loginbtn')
    await page.wait_for_load_state('networkidle', timeout=30000)
    print('  Login completato.')

async def take_screenshot(page, path, full_page=True, wait_ms=2000):
    """Take a screenshot with wait."""
    await page.wait_for_timeout(wait_ms)
    filepath = os.path.join(OUT_DIR, path)
    await page.screenshot(path=filepath, full_page=full_page)
    print(f'    -> {path}')

async def safe_goto(page, url, wait_until='networkidle', timeout=30000):
    """Navigate safely, handling errors."""
    try:
        await page.goto(url, wait_until=wait_until, timeout=timeout)
        return True
    except Exception as e:
        print(f'    WARN: Timeout/errore su {url}: {str(e)[:80]}')
        try:
            await page.wait_for_timeout(3000)
        except:
            pass
        return False

async def element_screenshot(page, selector, path, wait_ms=1000):
    """Screenshot of a specific element if found."""
    try:
        await page.wait_for_timeout(wait_ms)
        el = await page.query_selector(selector)
        if el:
            filepath = os.path.join(OUT_DIR, path)
            await el.screenshot(path=filepath)
            print(f'    -> {path} (element: {selector})')
            return True
        else:
            print(f'    SKIP: selettore non trovato: {selector}')
            return False
    except Exception as e:
        print(f'    WARN: errore screenshot elemento {selector}: {str(e)[:60]}')
        return False

async def click_and_screenshot(page, selector, path, full_page=False, wait_ms=2000):
    """Click an element and take a screenshot. If path is None, just click."""
    try:
        el = await page.query_selector(selector)
        if el:
            await el.click()
            await page.wait_for_timeout(wait_ms)
            if path:
                await take_screenshot(page, path, full_page=full_page, wait_ms=500)
            return True
        else:
            print(f'    SKIP: pulsante non trovato: {selector}')
            return False
    except Exception as e:
        print(f'    WARN: errore click {selector}: {str(e)[:60]}')
        return False

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            viewport={'width': 1366, 'height': 900},
            locale='it-IT',
        )
        page = await context.new_page()

        # === LOGIN ===
        await login(page)

        # === MODULO 1: Introduzione e Accesso ===
        print('\nModulo 1: Introduzione e Accesso')

        # Login page (logout first then capture)
        await safe_goto(page, f'{BASE_URL}/login/index.php?redirect=0')
        await take_screenshot(page, 'mod01_login.png', full_page=False)

        # Re-login
        await login(page)

        # FTM Hub
        if await safe_goto(page, f'{BASE_URL}/local/ftm_hub/index.php'):
            await take_screenshot(page, 'mod01_ftm_hub.png', full_page=False)

        # === MODULO 2: Dashboard - Vista Standard (default) ===
        print('\nModulo 2: Coach Dashboard')

        # Standard view (default or explicit)
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=standard'):
            await take_screenshot(page, 'mod02_dashboard_full.png', full_page=True, wait_ms=3000)
            await take_screenshot(page, 'mod02_stats.png', full_page=False)

            # Quick filters area
            await element_screenshot(page, '.quick-filters', 'mod02_quick_filters.png')

        # Vista Compatta
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=compatta'):
            await take_screenshot(page, 'mod02_vista_compatta.png', full_page=False, wait_ms=3000)

        # Vista Standard (back for clear shot)
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=standard'):
            await take_screenshot(page, 'mod02_vista_standard.png', full_page=False, wait_ms=3000)

        # Vista Dettagliata
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=dettagliata'):
            await take_screenshot(page, 'mod02_vista_dettagliata.png', full_page=False, wait_ms=3000)

        # Filtri: toggle filters-header and capture expanded filter section
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=standard'):
            await page.wait_for_timeout(2000)
            clicked = await click_and_screenshot(page, '.filters-header', 'mod02_filtri.png', full_page=False, wait_ms=1500)
            if not clicked:
                # Try JS approach
                try:
                    await page.evaluate('if(typeof toggleFilters === "function") toggleFilters()')
                    await page.wait_for_timeout(1500)
                    await take_screenshot(page, 'mod02_filtri.png', full_page=False)
                except:
                    pass

        # === MODULO 3: Card Studente ===
        print('\nModulo 3: Card Studente')

        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=standard'):
            await page.wait_for_timeout(3000)

            # Try to expand first student card by clicking its header
            try:
                card_header = await page.query_selector('.student-card-header')
                if card_header:
                    await card_header.click()
                    await page.wait_for_timeout(2000)
                    # Screenshot the first student card (now expanded)
                    card = await page.query_selector('.student-card')
                    if card:
                        filepath = os.path.join(OUT_DIR, 'mod03_card_completa.png')
                        await card.screenshot(path=filepath)
                        print('    -> mod03_card_completa.png (first student card)')
                    else:
                        await take_screenshot(page, 'mod03_card_completa.png', full_page=False)
                else:
                    print('    SKIP: nessuna card studente trovata')
                    await take_screenshot(page, 'mod03_card_completa.png', full_page=False)
            except Exception as e:
                print(f'    WARN: errore card: {str(e)[:60]}')
                await take_screenshot(page, 'mod03_card_completa.png', full_page=False)

        # === MODULO 4: Report Studente ===
        print('\nModulo 4: Report Studente')

        # Need a student ID. Try multiple methods.
        student_id = None

        # Method 1: from dashboard card
        if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=standard'):
            await page.wait_for_timeout(2000)
            try:
                first_card = await page.query_selector('.student-card[id^="student-"]')
                if first_card:
                    card_id = await first_card.get_attribute('id')
                    if card_id and card_id.startswith('student-'):
                        student_id = card_id.replace('student-', '')
                        print(f'    Studente trovato (dashboard): ID={student_id}')
            except:
                pass

        # Method 2: from compact view rows
        if not student_id:
            if await safe_goto(page, f'{BASE_URL}/local/coachmanager/coach_dashboard_v2.php?view=compatta'):
                await page.wait_for_timeout(2000)
                try:
                    row = await page.query_selector('.student-row[data-studentid]')
                    if row:
                        student_id = await row.get_attribute('data-studentid')
                        print(f'    Studente trovato (compatta): ID={student_id}')
                except:
                    pass

        # Method 3: from any Report link in the page
        if not student_id:
            try:
                report_link = await page.query_selector('a[href*="student_report.php?userid="]')
                if report_link:
                    href = await report_link.get_attribute('href')
                    if 'userid=' in href:
                        student_id = href.split('userid=')[1].split('&')[0]
                        print(f'    Studente trovato (link): ID={student_id}')
            except:
                pass

        if not student_id:
            print('    WARN: nessuno studente trovato, report con dati vuoti')
        else:
            print(f'    Usando studente ID={student_id} per report')

        # Tab Panoramica (default) - include show_ params for FTM panel
        report_base = f'{BASE_URL}/local/competencymanager/student_report.php'
        ftm_params = '&show_dual_radar=1&show_gap=1&show_spunti=1&show_coach_eval=1'
        report_url = f'{report_base}?userid={student_id}&tab=overview{ftm_params}' if student_id else report_base
        if await safe_goto(page, report_url):
            await take_screenshot(page, 'mod04_header.png', full_page=False, wait_ms=3000)
            await take_screenshot(page, 'mod04_panoramica.png', full_page=True, wait_ms=2000)

            # Tab Dettagli
            details_url = f'{report_base}?userid={student_id}&tab=details' if student_id else f'{report_base}?tab=details'
            if await safe_goto(page, details_url):
                await take_screenshot(page, 'mod04_dettagli.png', full_page=False, wait_ms=3000)

            # Tab Quiz
            quiz_url = f'{report_base}?userid={student_id}&tab=quiz' if student_id else f'{report_base}?tab=quiz'
            if await safe_goto(page, quiz_url):
                await take_screenshot(page, 'mod04_quiz.png', full_page=False, wait_ms=3000)

            # Back to overview for FTM panel tabs
            await safe_goto(page, report_url)
            await page.wait_for_timeout(2000)

            # FTM Tab: Ultimi 7gg
            await click_and_screenshot(page, '.ftm-tab-btn[data-tab="ultimi7gg"]', 'mod04_ultimi7gg.png', wait_ms=2000)

            # FTM Tab: Configurazione
            await click_and_screenshot(page, '.ftm-tab-btn[data-tab="config-report"]', 'mod04_config.png', wait_ms=2000)

            # FTM Tab: Gap Analysis
            await click_and_screenshot(page, '.ftm-tab-btn[data-tab="gap-analysis"]', 'mod05_gap.png', wait_ms=2000)

            # FTM Tab: Spunti Colloquio
            await click_and_screenshot(page, '.ftm-tab-btn[data-tab="spunti"]', 'mod05_spunti.png', wait_ms=2000)

            # FTM Panel overview (click settori first to show panel)
            await click_and_screenshot(page, '.ftm-tab-btn[data-tab="settori"]', 'mod04_ftm_panel.png', wait_ms=2000)

            # Stampa personalizzata modal
            try:
                print_btn = await page.query_selector('button:has-text("Stampa"), [onclick*="stampa"], [onclick*="print"]')
                if print_btn:
                    await print_btn.click()
                    await page.wait_for_timeout(2000)
                    await take_screenshot(page, 'mod04_stampa.png', full_page=False)
                    await page.keyboard.press('Escape')
                    await page.wait_for_timeout(500)
            except:
                pass

        # === MODULO 6: Valutazione ===
        print('\nModulo 6: Valutazione Formatore')

        eval_url = f'{BASE_URL}/local/competencymanager/coach_evaluation.php'
        if student_id:
            eval_url += f'?userid={student_id}'
        if await safe_goto(page, eval_url):
            await take_screenshot(page, 'mod06_eval_header.png', full_page=False, wait_ms=3000)
            await take_screenshot(page, 'mod06_eval_form.png', full_page=True)

            # Scroll to buttons
            await page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
            await page.wait_for_timeout(1000)
            await take_screenshot(page, 'mod06_eval_buttons.png', full_page=False)

        # === MODULO 7: Bilancio ===
        print('\nModulo 7: Bilancio Competenze')

        bilancio_url = f'{BASE_URL}/local/coachmanager/reports_v2.php'
        if student_id:
            bilancio_url += f'?studentid={student_id}'
        if await safe_goto(page, bilancio_url):
            await take_screenshot(page, 'mod07_bilancio_panoramica.png', full_page=False, wait_ms=3000)

            # Tab Radar - use JS showTab()
            try:
                await page.evaluate('if(typeof showTab === "function") showTab("radar")')
                await page.wait_for_timeout(2500)
                await take_screenshot(page, 'mod07_radar_confronto.png', full_page=False)
            except:
                # Fallback: click nav-tab
                await click_and_screenshot(page, 'a.nav-tab:has-text("Radar")', 'mod07_radar_confronto.png', wait_ms=2500)

            # Tab Mappa
            try:
                await page.evaluate('if(typeof showTab === "function") showTab("competenze")')
                await page.wait_for_timeout(2500)
                await take_screenshot(page, 'mod07_mappa.png', full_page=False)
            except:
                await click_and_screenshot(page, 'a.nav-tab:has-text("Mappa")', 'mod07_mappa.png', wait_ms=2500)

            # Tab Confronta
            try:
                await page.evaluate('if(typeof showTab === "function") showTab("confronta")')
                await page.wait_for_timeout(2500)
                await take_screenshot(page, 'mod07_confronta.png', full_page=False)
            except:
                await click_and_screenshot(page, 'a.nav-tab:has-text("Confronta")', 'mod07_confronta.png', wait_ms=2500)

        # === MODULO 8: Self-Assessment + Scheduler ===
        print('\nModulo 8: Self-Assessment e Scheduler')

        if await safe_goto(page, f'{BASE_URL}/local/selfassessment/index.php'):
            await take_screenshot(page, 'mod08_selfassessment.png', full_page=False, wait_ms=3000)

        if await safe_goto(page, f'{BASE_URL}/local/ftm_scheduler/index.php'):
            await take_screenshot(page, 'mod08_scheduler_full.png', full_page=False, wait_ms=3000)
            await take_screenshot(page, 'mod08_calendario.png', full_page=True)

            # Tab Aule - navigate directly via URL
            pass

        # Scheduler Tab Aule (direct URL navigation)
        if await safe_goto(page, f'{BASE_URL}/local/ftm_scheduler/index.php?tab=aule'):
            await take_screenshot(page, 'mod08_aule.png', full_page=False, wait_ms=3000)

        # Scheduler Tab Atelier
        if await safe_goto(page, f'{BASE_URL}/local/ftm_scheduler/index.php?tab=atelier'):
            await take_screenshot(page, 'mod08_atelier.png', full_page=False, wait_ms=3000)

        # Scheduler Tab Gruppi + modal
        if await safe_goto(page, f'{BASE_URL}/local/ftm_scheduler/index.php?tab=gruppi'):
            await take_screenshot(page, 'mod08_gruppi.png', full_page=False, wait_ms=3000)
            # New Group modal
            try:
                new_group_btn = await page.query_selector('button:has-text("Nuovo Gruppo"), button:has-text("Crea"), [onclick*="newGroup"], [onclick*="nuovoGruppo"], [onclick*="createGroup"]')
                if new_group_btn:
                    await new_group_btn.click()
                    await page.wait_for_timeout(2000)
                    await take_screenshot(page, 'mod08_nuovo_gruppo.png', full_page=False)
                    await page.keyboard.press('Escape')
                    await page.wait_for_timeout(500)
                else:
                    print('    SKIP: pulsante nuovo gruppo non trovato')
            except Exception as e:
                print(f'    WARN: nuovo gruppo: {str(e)[:60]}')

        # Presenze
        if await safe_goto(page, f'{BASE_URL}/local/ftm_scheduler/attendance.php'):
            await take_screenshot(page, 'mod08_presenze.png', full_page=False, wait_ms=3000)

        # === FINE ===
        await browser.close()

    # Report finale
    files = [f for f in os.listdir(OUT_DIR) if f.endswith('.png')]
    print(f'\n=== COMPLETATO ===')
    print(f'Screenshot generati: {len(files)}')
    for f in sorted(files):
        size_kb = os.path.getsize(os.path.join(OUT_DIR, f)) / 1024
        print(f'  {f} ({size_kb:.0f} KB)')

if __name__ == '__main__':
    asyncio.run(main())
