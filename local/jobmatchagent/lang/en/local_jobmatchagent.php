<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'JobMatch Agent';

$string['jobmatchagent:configure'] = 'Configure JobMatch Agent';
$string['jobmatchagent:manage'] = 'Manage student filters and review matches';
$string['jobmatchagent:addoffer'] = 'Add manual job offers';
$string['jobmatchagent:viewown'] = 'View own published matches';

$string['score_threshold'] = 'Minimum score threshold (%)';
$string['score_threshold_desc'] = 'Matches below this deterministic score are discarded without calling AI. Default: 10%';
$string['openai_model'] = 'OpenAI model';
$string['openai_model_desc'] = 'AI model used for matching. API key is reused from JobAIDA. GPT-4o Mini is recommended to stay within budget.';
$string['budget_eur_month'] = 'Monthly budget (EUR)';
$string['budget_eur_month_desc'] = 'Indicative monthly AI spend cap (logging only, not enforced).';
$string['weights_heading'] = 'Scoring weights (must sum to 100)';
$string['weights_heading_desc'] = 'Default weights for global score calculation.';
$string['weight_sector'] = 'Sector weight (%)';
$string['weight_experience'] = 'CV experience weight (%)';
$string['weight_distance'] = 'Home distance weight (%)';
$string['weight_schedule'] = 'Work schedule weight (%)';
$string['weight_size'] = 'Company size weight (%)';

$string['coachdashboard'] = 'JobMatch (Coach)';
$string['mymatches'] = 'My opportunities';

$string['cd_title'] = 'JobMatch Agent — Coach Dashboard';
$string['cd_intro'] = 'Managed students and matches awaiting review.';
$string['cd_student'] = 'Student';
$string['cd_pending'] = 'Pending';
$string['cd_published'] = 'Published';
$string['cd_discarded'] = 'Discarded';
$string['cd_filters'] = 'Filters';
$string['cd_actions'] = 'Actions';
$string['cd_review'] = 'Review matches';
$string['cd_setfilters'] = 'Set filters';
$string['cd_addoffer'] = 'Add manual offer';
$string['cd_nostudents'] = 'No students assigned.';
$string['cd_agent_off'] = 'Agent OFF';
$string['cd_agent_on'] = 'Agent ON';
$string['cd_runsearch'] = 'Search opportunities';
$string['cd_runsearch_all'] = 'Search for all active';
$string['cd_fetch_now'] = 'Update catalog (RSS + AI Scraper)';
$string['cd_fetch_confirm'] = 'Update the catalog now? The system will fetch all RSS feeds AND run the AI Scraper on jobs.ch, randstad.ch and carriera.ch for active students\' sectors and activities. May take up to 5 minutes.';
$string['cd_manage_sources'] = 'Manage RSS sources';
$string['cd_search_settings'] = 'Search settings';
$string['cd_catalog'] = 'Catalog';
$string['cd_catalog_offers'] = 'active offers';
$string['cd_catalog_sources'] = 'active sources';

$string['src_title'] = 'RSS / feed sources';
$string['src_intro'] = 'Configure RSS or Atom sources from which the agent fetches job offers. Queried by the daily cron and the "Update catalog" button.';
$string['src_name'] = 'Name';
$string['src_url'] = 'Feed URL';
$string['src_url_desc'] = 'RSS 2.0, RSS 1.0, or Atom feed URL.';
$string['src_enabled'] = 'Enabled';
$string['src_on'] = 'ON';
$string['src_off'] = 'OFF';
$string['src_last_fetch'] = 'Last fetch';
$string['src_last_error'] = 'Last error';
$string['src_add'] = 'Add source';
$string['src_save'] = 'Save source';
$string['src_saved'] = 'Source saved.';
$string['src_deleted'] = 'Source deleted.';
$string['src_toggled'] = 'Source status updated.';
$string['src_confirm_delete'] = 'Delete this source permanently?';
$string['src_disable'] = 'Disable';
$string['src_enable'] = 'Enable';
$string['src_none'] = 'No sources configured. Add one to start populating the catalog automatically.';
$string['src_examples'] = 'Swiss RSS feed examples:';

$string['fn_title'] = 'Catalog update';
$string['fn_intro'] = 'The agent is fetching offers from all configured RSS sources and computing matches for active students.';
$string['fn_no_sources'] = 'No RSS sources configured. Add at least one in "Manage sources".';
$string['fn_configure_sources'] = 'Configure RSS sources';
$string['fn_success'] = 'Added {$a->offers} new offers to the catalog. Generated {$a->matches} new matches for active students.';
$string['fn_no_new'] = 'No new offers found. All feed offers are already in the catalog.';
$string['fn_with_errors'] = 'Fetch completed with errors. See per-source detail below.';
$string['fn_sources_run'] = 'Sources queried';
$string['fn_offers_added'] = 'new offers';
$string['fn_matches_created'] = 'Matches generated for active students';
$string['fn_per_source'] = 'Per-source detail';
$string['fn_status'] = 'Status';
$string['fn_rss_sources'] = 'RSS Sources';
$string['fn_ai_scraper'] = 'AI Scraper (jobs.ch / randstad / carriera)';
$string['fn_ai_not_available'] = 'Plugin local_ftm_jobsearch not installed';
$string['fn_sectors_scraped'] = 'sectors scraped';
$string['fn_offers_imported'] = 'offers imported';
$string['fn_total_offers'] = 'Total new offers in catalog';
$string['fn_rss_detail'] = 'RSS sources detail';
$string['fn_ai_detail'] = 'AI Scraper detail';
$string['fn_ai_explanation'] = 'The AI Scraper queries jobs.ch, randstad.ch and carriera.ch for each combination of active students\' sector + desired activity.';
$string['fn_sector_mansione'] = 'Sector | activity combo';
$string['fn_errors'] = 'Errors';

$string['rs_title'] = 'Search result for {$a}';
$string['rs_intro'] = 'The agent compared {$a}\'s filters against all offers in the catalog. Here\'s what it found:';
$string['rs_offers_checked'] = 'Offers checked in catalog';
$string['rs_new_matches'] = 'New matches above threshold (visible in review)';
$string['rs_below_threshold'] = 'Auto-discarded (below threshold {$a}%)';
$string['rs_already_done'] = 'Already done from previous search (skipped)';
$string['rs_no_offers'] = 'No offers in catalog. Add one with "Add manual offer" or wait for the daily cron (in F2).';
$string['rs_no_new'] = 'No new matches found in this search. All offers in catalog were already evaluated for this student, or none passes the configured threshold ({$a}%).';
$string['rs_success'] = '{$a} new matches found. Click "Review matches" to examine them.';
$string['rs_view_matches'] = 'Review the matches found';
$string['rs_back_dashboard'] = 'Back to dashboard';
$string['rs_no_filters'] = 'You must set search filters for this student first.';
$string['rs_agent_off'] = 'The agent is OFF for this student. Activate it in filters before searching.';
$string['rs_confirm'] = 'Run job opportunity search for {$a} now? The agent will compare the student\'s CV and filters against all offers in catalog.';

$string['sf_title'] = 'Search filters for {$a}';
$string['sf_intro'] = 'Configure search filters for the agent. All filters are AND (must all match).';
$string['sf_active'] = 'Agent active for this student';
$string['sf_active_desc'] = 'If disabled, no new searches will be done for this student.';
$string['sf_home_address'] = 'Home address';
$string['sf_home_address_desc'] = 'Street, city. Geocoding entered manually (lat/lng optional).';
$string['sf_home_lat'] = 'Latitude';
$string['sf_home_lng'] = 'Longitude';
$string['sf_max_distance'] = 'Max distance (km)';
$string['sf_max_distance_desc'] = 'Maximum acceptable distance from home to work.';
$string['sf_company_sizes'] = 'Company size';
$string['sf_company_size_s'] = 'Small (1-49)';
$string['sf_company_size_m'] = 'Medium (50-249)';
$string['sf_company_size_l'] = 'Large (250+)';
$string['sf_work_schedules'] = 'Work schedule';
$string['sf_schedule_fulltime'] = 'Full time';
$string['sf_schedule_parttime'] = 'Part time';
$string['sf_schedule_shifts'] = 'Shifts';
$string['sf_schedule_flex'] = 'Flexible';
$string['sf_desired_activities'] = 'Desired activities';
$string['sf_desired_activities_desc'] = 'One activity per line. Example: building maintenance, electrician, warehouse worker.';
$string['sf_extra_notes'] = 'Extra notes (for AI)';
$string['sf_extra_notes_desc'] = 'Free context that will be passed to the AI to improve matching.';
$string['sf_save'] = 'Save filters';
$string['sf_saved'] = 'Filters saved successfully.';
$string['sf_cv_section'] = 'CV used for matching';
$string['sf_cv_using_manual'] = 'The agent is using the manually pasted CV below (override).';
$string['sf_cv_using_jobaida'] = 'The agent is using the latest CV saved in JobAIDA. If you want to use a specific CV, paste it below.';
$string['sf_cv_none'] = 'No CV available for this student. Paste one below, or have the student generate at least one AIDA letter in JobAIDA.';
$string['sf_manual_cv'] = 'Custom CV (paste text)';
$string['sf_manual_cv_desc'] = 'If filled, this CV takes priority over the one saved in JobAIDA. Leave empty to automatically use the latest JobAIDA CV.';
$string['sf_manual_cv_placeholder'] = 'Paste full student CV here.';
$string['sf_clear_cv'] = 'Clear custom CV and revert to JobAIDA CV';

$string['ao_title'] = 'Add manual job offer';
$string['ao_intro'] = 'Paste URL and/or text of the offer. Match will be computed for all active students.';
$string['ao_url'] = 'Offer URL';
$string['ao_jobtitle'] = 'Title';
$string['ao_company'] = 'Company';
$string['ao_location'] = 'Location';
$string['ao_company_size'] = 'Company size';
$string['ao_company_size_unknown'] = 'Unknown';
$string['ao_work_schedule'] = 'Schedule';
$string['ao_text'] = 'Offer text';
$string['ao_text_desc'] = 'Paste full offer text here. Used for AI matching.';
$string['ao_save'] = 'Save and compute matches';
$string['ao_saved'] = 'Offer saved. {$a} matches computed.';
$string['ao_duplicate'] = 'Offer already in catalog (duplicate fingerprint).';

$string['cr_title'] = 'Matches for {$a}';
$string['cr_intro'] = 'Matches awaiting review. Decide whether to publish to the student, discard or hold.';
$string['cr_offer'] = 'Offer';
$string['cr_score'] = 'Suitability';
$string['cr_breakdown'] = 'Score breakdown';
$string['cr_explanation'] = 'AI explanation';
$string['cr_no_ai_yet'] = 'AI explanation not yet generated (queued).';
$string['cr_publish'] = 'Publish to student';
$string['cr_discard'] = 'Discard';
$string['cr_onhold'] = 'Hold';
$string['cr_published'] = 'Published on {$a}';
$string['cr_discarded'] = 'Discarded on {$a}';
$string['cr_onhold_at'] = 'On hold since {$a}';
$string['cr_note'] = 'Coach note';
$string['cr_no_pending'] = 'No pending matches for this student.';
$string['cr_show_published'] = 'Show published';
$string['cr_show_discarded'] = 'Show discarded';

$string['score_global'] = 'Global score';
$string['score_sector'] = 'Sector';
$string['score_experience'] = 'CV experience';
$string['score_distance'] = 'Home distance';
$string['score_schedule'] = 'Schedule';
$string['score_size'] = 'Company size';
$string['score_activity'] = 'Desired activity';

$string['sv_title'] = 'My opportunities';
$string['sv_intro'] = 'Opportunities selected by your coach based on your CV and preferences.';
$string['sv_no_matches'] = 'No opportunities available right now.';
$string['sv_published_at'] = 'Published on {$a}';
$string['sv_view_offer'] = 'View original offer';
$string['sv_cv_used'] = 'CV used for evaluation';
$string['sv_show_cv'] = 'Show CV';
$string['sv_hide_cv'] = 'Hide CV';
$string['sv_why_match'] = 'Why this is a good match for you';
$string['sv_action_interested'] = 'I\'m interested, I want to apply';
$string['sv_action_not_interested'] = 'Not interested';
$string['sv_action_already_applied'] = 'I have already applied elsewhere';
$string['sv_reason'] = 'Reason (optional)';
$string['sv_action_saved'] = 'Response saved. Thanks for the feedback.';
$string['sv_generate_letter'] = 'Generate AIDA letter for this offer';

$string['err_no_capability'] = 'You do not have permission to access this page.';
$string['err_invalid_student'] = 'Invalid student or not managed by you.';
$string['err_invalid_match'] = 'Match not found.';
$string['err_no_text'] = 'You must paste the offer text.';
$string['err_no_title'] = 'You must enter a title.';
