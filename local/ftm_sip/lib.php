<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library functions for local_ftm_sip.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * SIP color constant (teal).
 */
define('LOCAL_FTM_SIP_COLOR', '#0891B2');
define('LOCAL_FTM_SIP_COLOR_BG', '#ECFEFF');
define('LOCAL_FTM_SIP_COLOR_BORDER', '#06B6D4');
define('LOCAL_FTM_SIP_COLOR_TEXT', '#155E75');

/**
 * SIP total weeks.
 */
define('LOCAL_FTM_SIP_TOTAL_WEEKS', 10);

/**
 * SIP total phases.
 */
define('LOCAL_FTM_SIP_TOTAL_PHASES', 6);

/**
 * Extend main navigation with SIP links.
 *
 * @param global_navigation $navigation
 */
function local_ftm_sip_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    // Coach/Segreteria: Dashboard SIP.
    if (has_capability('local/ftm_sip:view', $context)) {
        $navigation->add(
            get_string('sip_manager', 'local_ftm_sip'),
            new moodle_url('/local/ftm_sip/sip_dashboard.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'ftm_sip_dashboard',
            new pix_icon('i/report', '')
        );
    }

    // Studente: Area personale SIP.
    if (has_capability('local/ftm_sip:viewown', $context) &&
        !has_capability('local/ftm_sip:view', $context)) {
        global $USER;
        if (local_ftm_sip_has_active_enrollment($USER->id)) {
            $navigation->add(
                get_string('student_area_title', 'local_ftm_sip'),
                new moodle_url('/local/ftm_sip/sip_my.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'ftm_sip_my',
                new pix_icon('i/user', '')
            );
        }
    }
}

/**
 * Check if a user has an active SIP enrollment.
 *
 * @param int $userid
 * @return bool
 */
function local_ftm_sip_has_active_enrollment($userid) {
    global $DB;
    return $DB->record_exists('local_ftm_sip_enrollments', [
        'userid' => $userid,
        'status' => 'active',
    ]);
}

/**
 * Get SIP enrollment for a user.
 *
 * @param int $userid
 * @return object|false
 */
function local_ftm_sip_get_enrollment($userid) {
    global $DB;
    return $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
}

/**
 * Calculate SIP week number relative to enrollment start.
 *
 * @param int $date_start Unix timestamp of SIP start.
 * @param int|null $reference_time Reference timestamp (default: now). Pass the event date to get its week.
 * @return int Week number (1-10+), or 0 if not started yet.
 */
function local_ftm_sip_calculate_week($date_start, $reference_time = null) {
    $now = ($reference_time !== null) ? (int)$reference_time : time();
    if (!$date_start || $date_start > $now) {
        return 0;
    }
    // Normalize both to start-of-day (local timezone) so that the time-of-day
    // when the enrollment was activated never shifts week boundaries.
    $start_day = mktime(0, 0, 0, (int)date('n', $date_start), (int)date('j', $date_start), (int)date('Y', $date_start));
    $ref_day   = mktime(0, 0, 0, (int)date('n', $now),        (int)date('j', $now),        (int)date('Y', $now));
    if ($ref_day < $start_day) {
        return 0;
    }
    $weeks = (int)floor(($ref_day - $start_day) / (7 * 86400)) + 1;
    return max(1, $weeks);
}

/**
 * Get current phase based on week number.
 *
 * @param int $week Week number (1-10).
 * @return int Phase number (1-6).
 */
function local_ftm_sip_get_phase($week) {
    if ($week <= 1) {
        return 1; // Analisi e orientamento.
    }
    if ($week <= 2) {
        return 2; // Costruzione strategia.
    }
    if ($week <= 4) {
        return 3; // Attivazione ricerca.
    }
    if ($week <= 6) {
        return 4; // Rafforzamento strategia.
    }
    if ($week <= 8) {
        return 5; // Contatto mercato lavoro.
    }
    return 6; // Consolidamento e valutazione.
}

/**
 * Get the 7 activation areas definition.
 *
 * @return array Area key => [name_key, desc_key, obj_key, verify_key, icon, color]
 */
function local_ftm_sip_get_activation_areas() {
    return [
        // --- 10 aree quantitative (conteggio contatti/azioni per settimana) ---
        'target_companies' => [
            'name' => 'area_target_companies',
            'desc' => 'area_target_companies_desc',
            'objective' => 'area_target_companies_obj',
            'verify' => 'area_target_companies_verify',
            'icon' => 'fa-list-ol',
            'color' => '#2563EB',
            'week_start' => 1, 'week_end' => 4,
            'type' => 'quantitative',
            'default_target' => 30,
        ],
        'mandatory_searches' => [
            'name' => 'area_mandatory_searches',
            'desc' => 'area_mandatory_searches_desc',
            'objective' => 'area_mandatory_searches_obj',
            'verify' => 'area_mandatory_searches_verify',
            'icon' => 'fa-search',
            'color' => '#7C3AED',
            'week_start' => 1, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 40,
        ],
        'search_channels' => [
            'name' => 'area_search_channels',
            'desc' => 'area_search_channels_desc',
            'objective' => 'area_search_channels_obj',
            'verify' => 'area_search_channels_verify',
            'icon' => 'fa-sitemap',
            'color' => '#059669',
            'week_start' => 2, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 15,
        ],
        'social_network' => [
            'name' => 'area_social_network',
            'desc' => 'area_social_network_desc',
            'objective' => 'area_social_network_obj',
            'verify' => 'area_social_network_verify',
            'icon' => 'fa-share-alt',
            'color' => '#0EA5E9',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        'personal_network' => [
            'name' => 'area_personal_network',
            'desc' => 'area_personal_network_desc',
            'objective' => 'area_personal_network_obj',
            'verify' => 'area_personal_network_verify',
            'icon' => 'fa-users',
            'color' => '#0891B2',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 10,
        ],
        'targeted_applications' => [
            'name' => 'area_targeted_applications',
            'desc' => 'area_targeted_applications_desc',
            'objective' => 'area_targeted_applications_obj',
            'verify' => 'area_targeted_applications_verify',
            'icon' => 'fa-paper-plane',
            'color' => '#D97706',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 30,
        ],
        'unsolicited_applications' => [
            'name' => 'area_unsolicited_applications',
            'desc' => 'area_unsolicited_applications_desc',
            'objective' => 'area_unsolicited_applications_obj',
            'verify' => 'area_unsolicited_applications_verify',
            'icon' => 'fa-envelope-open',
            'color' => '#DC2626',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 10,
        ],
        'agencies_urc' => [
            'name' => 'area_agencies_urc',
            'desc' => 'area_agencies_urc_desc',
            'objective' => 'area_agencies_urc_obj',
            'verify' => 'area_agencies_urc_verify',
            'icon' => 'fa-building',
            'color' => '#64748B',
            'week_start' => 5, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 5,
        ],
        'interview_training' => [
            'name' => 'area_interview_training',
            'desc' => 'area_interview_training_desc',
            'objective' => 'area_interview_training_obj',
            'verify' => 'area_interview_training_verify',
            'icon' => 'fa-microphone',
            'color' => '#E11D48',
            'week_start' => 5, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        'stage_trials' => [
            'name' => 'area_stage_trials',
            'desc' => 'area_stage_trials_desc',
            'objective' => 'area_stage_trials_obj',
            'verify' => 'area_stage_trials_verify',
            'icon' => 'fa-briefcase',
            'color' => '#9333EA',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        // --- 2 aree qualitative (valutazione coach 1-10 per settimana) ---
        'strategy_improvement' => [
            'name' => 'area_strategy_improvement',
            'desc' => 'area_strategy_improvement_desc',
            'objective' => 'area_strategy_improvement_obj',
            'verify' => 'area_strategy_improvement_verify',
            'icon' => 'fa-line-chart',
            'color' => '#F59E0B',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'qualitative',
            'default_target' => 5,
        ],
        'growing_autonomy' => [
            'name' => 'area_growing_autonomy',
            'desc' => 'area_growing_autonomy_desc',
            'objective' => 'area_growing_autonomy_obj',
            'verify' => 'area_growing_autonomy_verify',
            'icon' => 'fa-graduation-cap',
            'color' => '#10B981',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'qualitative',
            'default_target' => 5,
        ],
    ];
}

/**
 * Get the old 7 activation areas (for backward compatibility with existing data).
 * @return array
 */
function local_ftm_sip_get_legacy_areas() {
    return ['professional_strategy', 'job_monitoring', 'targeted_applications',
            'unsolicited_applications', 'direct_company_contact', 'personal_network', 'intermediaries'];
}

/**
 * Get the 6 roadmap phases definition.
 *
 * @return array Phase number => [name_key, desc_key, weeks]
 */
function local_ftm_sip_get_phases() {
    return [
        1 => ['name' => 'phase_1', 'desc' => 'phase_1_desc', 'weeks' => '1'],
        2 => ['name' => 'phase_2', 'desc' => 'phase_2_desc', 'weeks' => '2'],
        3 => ['name' => 'phase_3', 'desc' => 'phase_3_desc', 'weeks' => '3-4'],
        4 => ['name' => 'phase_4', 'desc' => 'phase_4_desc', 'weeks' => '5-6'],
        5 => ['name' => 'phase_5', 'desc' => 'phase_5_desc', 'weeks' => '7-8'],
        6 => ['name' => 'phase_6', 'desc' => 'phase_6_desc', 'weeks' => '9-10'],
    ];
}

/**
 * Get the activation scale labels (0-6).
 *
 * @return array Level => lang string key
 */
function local_ftm_sip_get_activation_scale() {
    return [
        0 => 'score_0',
        1 => 'score_1',
        2 => 'score_2',
        3 => 'score_3',
        4 => 'score_4',
        5 => 'score_5',
        6 => 'score_6',
    ];
}

/**
 * Get the 10 channel assessment data (levels 0-6 descriptions and actions).
 * Data from the official "Piano d'azione" Excel template.
 *
 * @return array channel_key => [label, icon, color, levels[0..6], actions[1..6]]
 */
function local_ftm_sip_get_channel_assessment_data() {
    return [
        'annunci_quotidiani' => [
            'label' => 'Annunci su quotidiani o riviste',
            'icon'  => 'fa-newspaper-o',
            'color' => '#1D4ED8',
            'levels' => [
                0 => 'Non so che quotidiani e riviste pubblicano annunci di lavoro.',
                1 => 'So che quotidiani e riviste pubblicano annunci, ma non li guardo mai.',
                2 => "So che quotidiani e riviste pubblicano annunci, mi piacerebbe rispondere ma non l'ho mai fatto.",
                3 => 'Se vedo un annuncio per un lavoro che so fare bene, rispondo inviando una breve risposta e un CV, se richiesto.',
                4 => 'Se trovo un annuncio per il mio settore professionale, valuto i requisiti richiesti e se ne possiedo una buona parte rispondo, anche se non corrisponde a quello che ho sempre fatto.',
                5 => 'Leggo sempre gli annunci di lavoro pubblicati per sapere quali sono i profili più ricercati; rispondo a molti annunci in diversi settori, mi dichiaro disponibile ad apprendere se non possiedo tutti i requisiti.',
                6 => 'Quando leggo un annuncio di lavoro cerco di capire se i requisiti richiesti siano davvero tutti necessari e immagino me stesso nel ruolo richiesto. In base a queste riflessioni costruisco risposte strutturate.',
            ],
            'actions' => [
                1 => 'Sapere che vi sono annunci di lavoro sui quotidiani.',
                2 => 'Iniziare a leggere e analizzare annunci sui quotidiani.',
                3 => 'Iniziare ad analizzare e rispondere agli annunci pubblicati sui quotidiani.',
                4 => 'Valutare i requisiti del ruolo e rispondere agli annunci pubblicati sui quotidiani se se ne possiede una buona parte.',
                5 => 'Rispondere ad annunci pubblicati sui quotidiani anche se non si possiede tutti i requisiti, indicando la propria disposizione a colmare le lacune.',
                6 => 'Analizzare gli annunci pubblicati sui quotidiani, cercare di capire se i requisiti richiesti sono tutti necessari; confrontare il proprio profilo con quanto richiesto e rispondere in modo strutturato.',
            ],
        ],
        'annunci_web' => [
            'label' => 'Annunci su siti web specializzati o aziendali',
            'icon'  => 'fa-globe',
            'color' => '#0891B2',
            'levels' => [
                0 => "Ignoro l'esistenza di portali di ricerca impiego e non so che le aziende pubblicano annunci di lavoro.",
                1 => 'So che esistono portali di ricerca impiego e so che le aziende pubblicano annunci di lavoro, ma non li utilizzo.',
                2 => 'So che portali e aziende pubblicano annunci, a volte vi accedo e li consulto, ma non ho mai risposto.',
                3 => 'So che portali e aziende pubblicano annunci, a volte vi accedo e li consulto e rispondo se è cercato un profilo per il quale possiedo tutti i requisiti.',
                4 => 'Mi collego regolarmente a portali di lavoro e siti aziendali; se sono richiesti profili simili al mio invio una candidatura standard.',
                5 => 'Possiedo una lista di aziende e portali, li consulto regolarmente e se viene richiesto un profilo simile al mio, realizzo e invio una candidatura mirata.',
                6 => 'Cerco sempre di ampliare la banca dati di portali e aziende in mio possesso, in modo da avere più possibilità di proporre il mio profilo in maniera mirata.',
            ],
            'actions' => [
                1 => 'Sapere che vi sono annunci di lavoro su siti web specializzati o aziendali.',
                2 => 'Iniziare a consultare annunci su siti web specializzati o aziendali.',
                3 => 'Utilizzare e consultare gli annunci su siti web specializzati o aziendali, rispondendo a quelli che richiedono i requisiti che possiedo.',
                4 => 'Consultare regolarmente siti web specializzati o aziendali e rispondere agli annunci per i profili che possiedo.',
                5 => 'Creare una banca dati dei siti web specializzati o aziendali più usati che pubblicano annunci e rispondere con candidature mirate.',
                6 => 'Trovare e salvare nella banca dati nuovi siti web specializzati o aziendali che pubblicano annunci e rispondere con candidature mirate per i ruoli che so ricoprire.',
            ],
        ],
        'foglio_ufficiale' => [
            'label' => 'Concorsi Foglio Ufficiale',
            'icon'  => 'fa-file-text-o',
            'color' => '#7C3AED',
            'levels' => [
                0 => 'Ignoro che sul foglio ufficiale siano presenti i concorsi pubblicati per posti nella pubblica amministrazione.',
                1 => 'So che sul foglio ufficiale sono pubblicati i concorsi per posti nella pubblica amministrazione.',
                2 => 'So che sul foglio ufficiale sono pubblicati i concorsi per posti nella pubblica amministrazione; ogni tanto lo consulto ma non ho mai risposto poiché troppo complicato.',
                3 => 'È già successo che rispondessi a un concorso nella pubblica amministrazione, pubblicato sul foglio ufficiale.',
                4 => 'Consulto regolarmente il foglio ufficiale per verificare i concorsi e, nel caso in cui sia ricercato un profilo come il mio, concorro.',
                5 => 'Consulto regolarmente il foglio ufficiale, verifico i requisiti per i posti richiesti e se ne possiedo una buona parte concorro.',
                6 => 'Valuto i concorsi pubblicati sul foglio ufficiale; verifico i requisiti, decido anche di migliorare il mio profilo tramite formazioni mirate. Mi candido spesso per i posti a concorso.',
            ],
            'actions' => [
                1 => 'Sapere dove trovare i concorsi pubblicati sul foglio ufficiale.',
                2 => 'Consultare saltuariamente i concorsi pubblicati sul foglio ufficiale.',
                3 => 'Rispondere a un concorso pubblicato sul foglio ufficiale, usando la documentazione minima richiesta.',
                4 => 'Consultare regolarmente il foglio ufficiale per trovare concorsi ai quali è possibile concorrere.',
                5 => 'Consultare regolarmente il foglio ufficiale per rispondere ai concorsi per i quali si possiede una buona parte dei requisiti.',
                6 => 'Candidarsi regolarmente per i posti a concorso sul foglio ufficiale, indicando la disponibilità a migliorare il profilo tramite formazione per raggiungere i livelli richiesti.',
            ],
        ],
        'personalmente' => [
            'label' => 'Personalmente (visita diretta in azienda)',
            'icon'  => 'fa-user',
            'color' => '#059669',
            'levels' => [
                0 => 'Non sapevo fosse possibile presentarsi di persona per candidarsi a un posto di lavoro.',
                1 => 'So che è possibile presentarsi di persona in azienda per candidarsi a un posto di lavoro, ma non ho mai pensato di farlo.',
                2 => "So che è possibile presentarsi di persona in azienda per candidarsi, perché potrebbe verificarsi il caso per cui proprio in quel momento c'è bisogno di un profilo come il mio.",
                3 => 'Mi sono già presentato di persona presso aziende candidandomi per un posto di lavoro.',
                4 => 'Mi presento regolarmente presso le aziende: consegno la mia documentazione e verifico successivamente lo stato della situazione.',
                5 => "Prima di presentarmi di persona prendo informazioni sull'azienda, verifico chi potrebbe essere il mio interlocutore, preparo una documentazione mirata e verifico l'esito in seguito.",
                6 => "Mi presento personalmente dopo aver preso informazioni e preparato un dossier mirato; presento i vantaggi incluso il tryout; contatto successivamente chi si occupa della selezione.",
            ],
            'actions' => [
                1 => 'Sapere che si può proporre il proprio profilo di persona direttamente in azienda.',
                2 => 'Valutare la possibilità di presentarsi in aziende scelte precedentemente.',
                3 => 'Presentarsi in azienda consegnando la propria documentazione standard.',
                4 => "Presentarsi in azienda, consegnare il proprio dossier; verificare successivamente l'esito della propria candidatura.",
                5 => 'Prendere informazioni, realizzare una documentazione mirata e presentarsi in azienda; chiedere chi valuterà la candidatura per contattarla successivamente.',
                6 => 'Presentarsi in azienda con dossier mirato; presentare i vantaggi inclusa la possibilità del tryout; contattare successivamente chi si occupa della selezione.',
            ],
        ],
        'contatto_telefonico' => [
            'label' => 'Contatto telefonico',
            'icon'  => 'fa-phone',
            'color' => '#D97706',
            'levels' => [
                0 => 'Non sapevo fosse possibile contattare telefonicamente le aziende per cercare lavoro.',
                1 => "So che è possibile contattare telefonicamente l'azienda ma non ho mai pensato fosse possibile farlo in relazione alla ricerca impiego.",
                2 => "So che è possibile contattare telefonicamente l'azienda ma non l'ho mai fatto.",
                3 => 'A volte ho contattato telefonicamente le aziende per chiedere se ci fossero ricerche di collaboratori in corso.',
                4 => 'A volte ho contattato telefonicamente le aziende per sapere se fossero alla ricerca di profili come il mio.',
                5 => 'Contatto le aziende da un elenco per verificare se è possibile parlare con qualche responsabile e capire se posso ottenere un colloquio.',
                6 => "Contatto regolarmente aziende che ho in elenco; so a chi rivolgermi per informarmi su eventuali esigenze di personale e so come ottenere le informazioni rilevanti per la mia ricerca d'impiego.",
            ],
            'actions' => [
                1 => "Sapere che vi è la possibilità di contattare telefonicamente le aziende per proporre il proprio profilo.",
                2 => 'Valutare la possibilità di contattare telefonicamente le aziende scelte precedentemente.',
                3 => 'Contattare telefonicamente le aziende per verificare se sono alla ricerca di nuovi collaboratori.',
                4 => 'Contattare telefonicamente le aziende per verificare se hanno ruoli vacanti che so ricoprire.',
                5 => 'Contattare telefonicamente le aziende per verificare se è possibile parlare con un responsabile e ottenere un colloquio.',
                6 => "Telefonare ad aziende selezionate, parlare con persone di contatto già conosciute e informarsi su eventuali necessità, inclusa la possibilità di un incontro.",
            ],
        ],
        'rete_conoscenze' => [
            'label' => 'Rete di conoscenze personali e professionali',
            'icon'  => 'fa-users',
            'color' => '#0EA5E9',
            'levels' => [
                0 => 'Non so cosa è una rete di contatti.',
                1 => 'So cosa sono i contatti personali ma non ho mai pensato di usarli consapevolmente per la mia ricerca impiego.',
                2 => 'So che la mia rete dei contatti personali potrebbe essermi utile nella ricerca impiego, ma non ho mai attivato questo canale.',
                3 => 'Mi è capitato di chiedere a qualche conoscente se sapesse di possibilità di lavoro per me.',
                4 => "Ho un elenco dei miei contatti e so quali tra loro potrebbero essermi utili per la mia ricerca d'impiego: li contatto regolarmente.",
                5 => 'Sono in grado di valorizzare i contatti della mia rete, chiedendo loro di approfondire le informazioni disponibili per verificare se ci sono opportunità di impiego per me.',
                6 => "Gestisco la mia rete dei contatti andando alla ricerca attiva di nuove conoscenze, che valuto anche in funzione delle possibilità lavorative.",
            ],
            'actions' => [
                1 => "Sapere che è possibile trovare un impiego attraverso i contatti della propria rete di conoscenze.",
                2 => "Creare una lista di possibili contatti che potrebbero essere d'aiuto nella ricerca impiego.",
                3 => 'Fare sapere ai contatti più prossimi (amici, parenti) che si sta cercando un impiego.',
                4 => "Selezionare da una lista di contatti quelli che potrebbero essere maggiormente d'aiuto nella ricerca d'impiego e contattarli regolarmente.",
                5 => 'Utilizzare e valorizzare i contatti personali per approfondire informazioni utili a proporre il proprio profilo in maniera più efficace.',
                6 => "Trovare nuovi contatti all'interno di aziende e valutarli in funzione delle possibilità lavorative.",
            ],
        ],
        'autocandidatura_cartacea' => [
            'label' => 'Lettere di autocandidatura cartacee',
            'icon'  => 'fa-envelope',
            'color' => '#DC2626',
            'levels' => [
                0 => 'Non sono a conoscenza della possibilità di scrivere lettere per candidarmi alle aziende.',
                1 => "So che vi è la possibilità di redigere lettere per autocandidarsi alle aziende ma è un sistema che non uso.",
                2 => 'Uso lettere standard (brevi, spesso uguali, cambio il destinatario) per proporre il mio profilo alle aziende.',
                3 => 'Propongo il mio profilo alle aziende che già conosco o che mi sono state direttamente consigliate, scrivendo ogni volta una lettera mirata.',
                4 => 'Propongo il mio profilo sia ad aziende che già conosco sia a ditte nuove; cerco di scrivere lettere interessanti, cambiando il testo per ogni lettera.',
                5 => "Prendo informazioni sull'azienda a cui miro, realizzo lettere di autocandidatura in funzione di ciò che ho appreso, proponendo il mio profilo professionale per le attività svolte nell'azienda bersaglio.",
                6 => "Prendo informazioni sull'azienda a cui miro, realizzo lettere di autocandidatura mirate a una persona di riferimento con cui possibilmente sono già entrato in contatto prima di scrivere.",
            ],
            'actions' => [
                1 => "Sapere che vi è la possibilità di utilizzare lettere di autocandidatura per proporsi alle aziende anche in assenza di annunci.",
                2 => 'Utilizzare lettere di autocandidatura brevi e standard, cambiando solo il destinatario e allegando un CV standard.',
                3 => 'Proporre il proprio profilo ad aziende già conosciute, scrivendo ogni volta una lettera di autocandidatura nuova.',
                4 => 'Proporre il proprio profilo sia ad aziende conosciute sia a ditte nuove, scrivendo lettere di autocandidatura interessanti e personalizzate.',
                5 => "Prendere informazioni sull'azienda per inviare lettere di autocandidatura mirate in base alle attività che si svolgono al suo interno.",
                6 => "Prendere informazioni sull'azienda e inviare lettere di autocandidatura mirate a una persona di riferimento con cui è già stato stabilito un contatto.",
            ],
        ],
        'autocandidatura_online' => [
            'label' => 'Autocandidatura online (siti, e-mail)',
            'icon'  => 'fa-paper-plane',
            'color' => '#9333EA',
            'levels' => [
                0 => 'Non sono a conoscenza del fatto che nei siti aziendali posso trovare ricerche di collaboratori.',
                1 => 'So che nei siti aziendali vi possono essere delle rubriche dove sono visionabili le figure professionali ricercate, ma non li utilizzo.',
                2 => 'So che nei siti aziendali vi possono essere delle rubriche dove sono visionabili le figure professionali ricercate; a volte li consulto ma non ho mai risposto.',
                3 => 'Consulto i siti aziendali e mi propongo se viene cercato il mio profilo e sono in possesso di tutti i requisiti richiesti.',
                4 => 'Consulto i siti aziendali e mi propongo se possiedo le caratteristiche essenziali per il posto.',
                5 => "Possiedo una banca dati di aziende che mi interessano e consulto regolarmente i loro siti; se ritengo di possedere o poter acquisire rapidamente le competenze richieste, mi candido.",
                6 => "Vado alla ricerca di nuove aziende e se ne trovo di interessanti le inserisco nella banca dati; consulto regolarmente i loro siti per candidarmi in maniera mirata.",
            ],
            'actions' => [
                1 => 'Sapere che si può cercare lavoro su siti di aziende e realtà conosciute.',
                2 => 'Iniziare a consultare siti di aziende e realtà conosciute.',
                3 => 'Consultare siti aziendali e cercare la rubrica inerente i posti vacanti.',
                4 => 'Consultare regolarmente siti aziendali e rispondere se si è in possesso dei requisiti minimi, usando una candidatura standard.',
                5 => 'Creare una banca dati dei siti delle aziende più interessanti, consultarli regolarmente e rispondere con candidature mirate.',
                6 => 'Trovare e salvare nuovi siti aziendali nella banca dati; candidarsi in maniera mirata per i ruoli che si sa ricoprire.',
            ],
        ],
        'urc' => [
            'label' => 'URC (Ufficio Regionale di Collocamento)',
            'icon'  => 'fa-building',
            'color' => '#64748B',
            'levels' => [
                0 => 'Non sapevo che gli URC effettuassero assegnazioni del mio profilo alle ditte.',
                1 => 'So che gli URC effettuano assegnazioni dei profili alle ditte, ma non mi è mai successo.',
                2 => 'So che quando ci si iscrive agli URC i profili dei candidati vengono segnalati alle aziende che fanno le ricerche, ma non mi è mai successo.',
                3 => "Ho già ricevuto una segnalazione dall'URC e ho contattato l'azienda.",
                4 => "Ho già ricevuto segnalazioni dall'URC e in alcuni casi ho visto che non sono del tutto soddisfacenti, né per i candidati, né per le aziende.",
                5 => 'Mi è capitato di discutere insieme al mio consulente URC per valutare la coincidenza del mio profilo con i posti di lavoro disponibili nella loro banca dati.',
                6 => "Consulto spesso la banca dati dell'URC e quando posso discuto col mio CP URC dei posti disponibili per i quali il mio profilo potrebbe essere adeguato.",
            ],
            'actions' => [
                1 => "Sapere che quando si è in disoccupazione posso ricevere assegnazioni dagli URC.",
                2 => "Essere in grado di proporsi nella maniera corretta quando si ricevono assegnazioni dagli URC.",
                3 => "Inviare la propria candidatura alle aziende a seguito di assegnazioni ricevute dagli URC.",
                4 => "Inviare la propria candidatura a seguito di assegnazioni URC, anche se quanto ricercato non corrisponde appieno al mio profilo.",
                5 => "Discutere con il proprio CP URC per valutare la coincidenza del proprio profilo con i posti presenti nella jobroom o disponibili nella loro banca dati.",
                6 => "Consultare regolarmente la jobroom o la banca dati degli URC e discutere con il proprio CP URC quando si trova un ruolo che si sa ricoprire.",
            ],
        ],
        'agenzie' => [
            'label' => 'Agenzie di collocamento',
            'icon'  => 'fa-briefcase',
            'color' => '#E11D48',
            'levels' => [
                0 => "Non sono a conoscenza dell'esistenza di agenzie di collocamento che propongono posti di lavoro.",
                1 => 'So che vi sono agenzie che propongono posti di lavoro, ma non mi sono mai mosso in questa direzione.',
                2 => 'Conosco alcune agenzie di collocamento e so che tipo di lavoro fanno (interinale o intermediazione), ma non è mai successo nulla di concreto.',
                3 => "In passato ho già trovato lavoro (interinale o in intermediazione) tramite un'agenzia di collocamento.",
                4 => 'Sono già iscritto a diverse agenzie, sia come interinale sia per intermediazione con aziende che cercano collaboratori.',
                5 => 'Conosco diverse agenzie; ho lavorato con alcune e poi ho selezionato quelle più adatte al mio settore professionale e profilo, con cui sono regolarmente in contatto.',
                6 => 'Collaboro spesso con aziende tramite agenzia; ho buoni rapporti con alcune agenzie che ho selezionato nel tempo come le più serie ed efficaci per me.',
            ],
            'actions' => [
                1 => 'Sapere che si può trovare lavoro anche tramite le agenzie.',
                2 => 'Iniziare a visionare gli annunci pubblicati dalle agenzie.',
                3 => 'Iniziare a rispondere agli annunci pubblicati dalle agenzie.',
                4 => 'Iscriversi ad alcune agenzie, come interinale o per intermediazione.',
                5 => 'Selezionare le agenzie secondo il proprio settore di provenienza, consultarle regolarmente e rispondere agli annunci pubblicati.',
                6 => 'Collaborare attivamente con i consulenti di agenzie selezionate precedentemente e reputate come le più serie ed efficaci.',
            ],
        ],
    ];
}
