<?php
/**
 * Scheduled task: import job listings from CLI export files (Indeed) and scrape job-room.ch via PHP.
 *
 * Runs nightly. Expects the Flex Server cron to have already written:
 *   $CFG->dataroot . '/ftm_jobs/indeed.json'
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_jobsearch\task;

defined('MOODLE_INTERNAL') || die();

class scrape_jobs extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_scrape_jobs', 'local_ftm_jobsearch');
    }

    public function execute(): void {
        global $CFG;

        $sectors = ['MECCANICA', 'AUTOMOBILE', 'ELETTRICITA', 'AUTOMAZIONE', 'CHIMFARM', 'LOGISTICA', 'METALCOSTRUZIONE'];

        // --- Phase 1: job-room.ch via PHP (direct REST API) ---
        mtrace('ftm_jobsearch: avvio scraping job-room.ch...');
        require_once($CFG->dirroot . '/local/ftm_jobsearch/classes/ai_scraper.php');

        $jr_total = 0;
        foreach ($sectors as $settore) {
            try {
                $result = \local_ftm_jobsearch\ai_scraper::scrape_sector($settore, '', true);
                $saved  = $result['saved'] ?? 0;
                $jr_total += $saved;
                mtrace("  {$settore}: {$saved} nuovi annunci da job-room.ch");
            } catch (\Exception $e) {
                mtrace("  {$settore}: ERRORE job-room.ch — " . $e->getMessage());
            }
        }
        mtrace("ftm_jobsearch: job-room.ch completato — {$jr_total} nuovi annunci totali");

        // --- Phase 2: Indeed via CLI export file ---
        $indeed_file = $CFG->dataroot . '/ftm_jobs/indeed.json';
        if (!file_exists($indeed_file)) {
            mtrace('ftm_jobsearch: indeed.json non trovato (' . $indeed_file . ') — skipping Indeed');
            mtrace('  Assicurati che il cron del Flex Server abbia eseguito indeed-pp-cli sector export.');
            return;
        }

        $age_minutes = (time() - filemtime($indeed_file)) / 60;
        if ($age_minutes > 1440) { // File più vecchio di 24h.
            mtrace("ftm_jobsearch: indeed.json troppo vecchio ({$age_minutes} min) — skipping");
            return;
        }

        $raw = file_get_contents($indeed_file);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            mtrace('ftm_jobsearch: indeed.json non è JSON valido — skipping');
            return;
        }

        $indeed_total = 0;
        foreach ($data as $sector_block) {
            $settore = strtoupper($sector_block['sector'] ?? '');
            $jobs    = $sector_block['jobs'] ?? [];
            if (empty($settore) || empty($jobs)) {
                continue;
            }
            $saved = $this->import_indeed_jobs($jobs, $settore);
            $indeed_total += $saved;
            mtrace("  {$settore}: {$saved} nuovi annunci da indeed.ch");
        }
        mtrace("ftm_jobsearch: Indeed completato — {$indeed_total} nuovi annunci totali");
    }

    /**
     * Import a list of Indeed job objects into local_ftm_jobsearch_offers.
     * Returns the number of new records saved.
     */
    private function import_indeed_jobs(array $jobs, string $settore): int {
        global $DB;

        $max_age_days   = (int)(get_config('local_ftm_jobsearch', 'max_offer_age_days') ?: 90);
        $age_cutoff_ts  = strtotime("-{$max_age_days} days");
        $now            = time();
        $saved          = 0;

        foreach ($jobs as $job) {
            $url = trim($job['url'] ?? '');
            if (empty($url)) {
                continue;
            }

            $url_hash = hash('sha256', $url);

            // Refresh data_scraping if already known.
            if ($DB->record_exists('local_ftm_jobsearch_offers', ['url_hash' => $url_hash])) {
                $DB->execute(
                    'UPDATE {local_ftm_jobsearch_offers} SET data_scraping = :now, attivo = 1 WHERE url_hash = :hash',
                    ['now' => $now, 'hash' => $url_hash]
                );
                continue;
            }

            // Parse publication date from "15 days ago", "Today", "Just posted", etc.
            $data_pub = $this->parse_indeed_date($job['date_posted'] ?? '');
            if ($data_pub) {
                $pub_ts = strtotime($data_pub);
                if ($pub_ts && $pub_ts < $age_cutoff_ts) {
                    continue;
                }
            }

            // City: "Lugano, TI" → "Lugano"
            $location_raw = $job['location'] ?? '';
            $citta = trim(explode(',', $location_raw)[0]);

            // Job type from job_types array.
            $job_types = $job['job_types'] ?? [];
            $tipo = null;
            foreach ($job_types as $jt) {
                $jt_lower = strtolower($jt);
                if (strpos($jt_lower, 'full') !== false) {
                    $tipo = 'fulltime';
                    break;
                }
                if (strpos($jt_lower, 'part') !== false) {
                    $tipo = 'parttime';
                    break;
                }
                if (strpos($jt_lower, 'temp') !== false || strpos($jt_lower, 'hire') !== false) {
                    $tipo = 'temporaneo';
                    break;
                }
                if (strpos($jt_lower, 'stage') !== false || strpos($jt_lower, 'intern') !== false) {
                    $tipo = 'stage';
                    break;
                }
            }

            $record                   = new \stdClass();
            $record->titolo           = mb_substr($job['title'] ?? 'Offerta di lavoro', 0, 255);
            $record->azienda          = mb_substr($job['company'] ?? '', 0, 255) ?: null;
            $record->descrizione      = mb_substr($job['description'] ?? '', 0, 1000) ?: null;
            $record->settore          = $settore;
            $record->tipo_lavoro      = $tipo;
            $record->citta            = mb_substr($citta, 0, 100) ?: null;
            $record->lat              = null;
            $record->lng              = null;
            $record->url              = $url;
            $record->fonte            = 'indeed.ch';
            $record->data_pubblicazione = $data_pub;
            $record->data_scraping    = $now;
            $record->attivo           = 1;
            $record->url_hash         = $url_hash;

            try {
                $DB->insert_record('local_ftm_jobsearch_offers', $record);
                $saved++;
            } catch (\Exception $e) {
                // Duplicate or error — skip.
            }
        }

        return $saved;
    }

    /**
     * Convert Indeed relative date strings to YYYY-MM-DD.
     * "15 days ago" → date 15 days ago
     * "Today" / "Just posted" → today
     * null/empty → null
     */
    private function parse_indeed_date(string $raw): ?string {
        $raw = strtolower(trim($raw));
        if (empty($raw)) {
            return null;
        }
        if (strpos($raw, 'today') !== false || strpos($raw, 'just') !== false || $raw === '0') {
            return date('Y-m-d');
        }
        if (preg_match('/(\d+)\s*day/', $raw, $m)) {
            return date('Y-m-d', strtotime('-' . (int)$m[1] . ' days'));
        }
        if (preg_match('/(\d+)\s*hour/', $raw, $m)) {
            return date('Y-m-d');
        }
        return null;
    }
}
