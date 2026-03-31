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
 * English language strings for local_jobaida.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'JobAIDA - Letter Generator';
$string['pluginname_desc'] = 'Generate cover letters based on the AIDA model using artificial intelligence.';

// Capabilities.
$string['jobaida:use'] = 'Use the letter generator';
$string['jobaida:authorize'] = 'Authorize students to use the generator';
$string['jobaida:viewall'] = 'View all generated letters';

// Settings.
$string['openai_apikey'] = 'OpenAI API Key';
$string['openai_apikey_desc'] = 'Enter the OpenAI API key for letter generation.';
$string['openai_model'] = 'OpenAI Model';
$string['openai_model_desc'] = 'Select the AI model to use for generation.';
$string['letter_language'] = 'Letter language';
$string['letter_language_desc'] = 'Language in which cover letters will be generated.';
$string['max_tokens'] = 'Max tokens';
$string['max_tokens_desc'] = 'Maximum number of tokens for generation (default: 2000).';

// Navigation.
$string['generator'] = 'Letter Generator';
$string['history'] = 'Letter History';
$string['manage_auth'] = 'Manage Authorizations';

// Form.
$string['job_ad'] = 'Job Advertisement';
$string['job_ad_help'] = 'Paste the full text of the job advertisement you want to apply for.';
$string['job_ad_placeholder'] = 'Paste the job advertisement here...';
$string['cv_text'] = 'Your CV';
$string['cv_text_help'] = 'Paste the content of your curriculum vitae or your main experiences.';
$string['cv_text_placeholder'] = 'Paste your CV or your experiences here...';
$string['objectives'] = 'Your Objectives';
$string['objectives_help'] = 'Describe what you are looking for in this job, your values and personal motivations.';
$string['objectives_placeholder'] = 'What motivates you? What are your professional goals?';
$string['generate'] = 'Generate Letter';
$string['generating'] = 'Generating...';

// AIDA sections.
$string['aida_model'] = 'AIDA Model';
$string['aida_explanation'] = 'The AIDA model is a persuasive communication technique used in marketing and in writing effective cover letters.';
$string['attention'] = 'ATTENTION - Capture Attention';
$string['attention_desc'] = 'An opening hook that immediately captures the recruiter\'s attention, based on the job advertisement.';
$string['interest'] = 'INTEREST - Build Interest';
$string['interest_desc'] = 'Connection between the job requirements and your skills/experiences from the CV.';
$string['desire'] = 'DESIRE - Create Desire';
$string['desire_desc'] = 'Connection between your values/personal goals and the company\'s culture/mission.';
$string['action'] = 'ACTION - Call to Action';
$string['action_desc'] = 'Closing with a clear and professional call to action.';
$string['rationale'] = 'Why this choice';
$string['full_letter'] = 'Full Letter';
$string['copy_letter'] = 'Copy Letter';
$string['copied'] = 'Copied!';

// History.
$string['history_title'] = 'Your Letters';
$string['no_letters'] = 'You have not generated any letters yet.';
$string['generated_on'] = 'Generated on';
$string['view_letter'] = 'View';
$string['delete_letter'] = 'Delete';
$string['delete_confirm'] = 'Are you sure you want to delete this letter?';
$string['letter_deleted'] = 'Letter deleted';

// Authorization.
$string['not_authorized'] = 'You are not authorized to use the letter generator. Ask your coach to enable access.';
$string['authorize_student'] = 'Authorize Student';
$string['revoke_student'] = 'Revoke Authorization';
$string['student_authorized'] = 'Student authorized successfully';
$string['student_revoked'] = 'Authorization revoked';
$string['authorized_students'] = 'Authorized Students';
$string['search_student'] = 'Search student to authorize...';

// Errors.
$string['error_no_apikey'] = 'OpenAI API key not configured. Contact the administrator.';
$string['error_api_failed'] = 'Generation error: {$a}';
$string['error_empty_fields'] = 'Please fill in all required fields.';
$string['error_too_short'] = 'The entered text is too short. Please enter at least 50 characters per field.';

// Stats.
$string['letters_generated'] = 'Letters generated';
$string['tokens_consumed'] = 'Tokens consumed';
$string['last_generated'] = 'Last generated';
