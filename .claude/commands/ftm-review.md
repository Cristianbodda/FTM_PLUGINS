# FTM Code Review Command

## Overview
Comprehensive code review for FTM Moodle plugins using 4 specialized agents in parallel.

## Workflow

### Phase 1: Git Diff Extraction
```bash
# Get changes to review
git diff main...HEAD
git diff HEAD
git diff --cached
```

Identify:
- Modified files
- Plugin affected
- Type of changes (new feature, bug fix, refactor)

### Phase 2: Parallel Agent Analysis

Launch 4 agents simultaneously, each analyzing the diff from their perspective:

#### Agent 1: Moodle Security Scanner (HIGHEST PRIORITY)
Scan for:
- SEC001-SEC002: SQL Injection (`$DB->execute` with concatenation)
- SEC003: Missing `require_sesskey()` in POST/AJAX
- SEC004: Raw `$_GET`/`$_POST` instead of `required_param`/`optional_param`
- SEC005-SEC006: Missing `require_login()` or `require_capability()`
- SEC007-SEC008: File inclusion and path traversal
- SEC009: Error disclosure (raw exception messages)
- SEC010: Hardcoded credentials
- SEC011: Missing ownership verification
- SEC012: Unnecessary PARAM_RAW usage

#### Agent 2: FTM Investigator (Logic & Correctness)
Verify:
- Logic matches requirements
- Database queries return expected data
- Framework/competency IDs correct (9=FTM-01, 10=FTM_GEN)
- Sector aliases handled properly
- Event observers configured correctly
- AJAX response format consistent

#### Agent 3: Moodle Refactor (Code Quality)
Check:
- DRY violations (duplicated code)
- Functions > 50 lines
- Magic numbers/strings (use constants)
- Missing lang strings (hardcoded text)
- Unused variables/imports
- Inconsistent naming
- Missing PHPDoc headers

#### Agent 4: Moodle Standards (Structure)
Validate:
- STR001: version.php correct
- STR002: Namespace matches plugin
- STR003: PHPDoc headers present
- STR004: `defined('MOODLE_INTERNAL')` in includes
- STR005-006: Lang files exist
- STR007: Capabilities defined
- STR008-009: $PAGE setup correct
- STR014: User fullname fields complete

### Phase 3: Consolidation

Prioritize findings:
1. **CRITICAL**: Build-breaking, security vulnerabilities
2. **HIGH**: Logic errors, missing authentication
3. **MEDIUM**: Code quality, standards violations
4. **LOW**: Style, minor improvements

Group by file for easier fixing.

### Phase 4: Sequential Fixes

Apply fixes in priority order using ftm-implementer:
1. Security issues (CRITICAL)
2. Logic errors (HIGH)
3. Code quality (MEDIUM)
4. Standards compliance (LOW)

### Phase 5: Verification

```bash
# PHP syntax check
php -l <modified_files>

# Run Playwright health check
node playwright_pm/ftm_health_check.mjs

# Verify no regressions
git diff
```

## Output Format

```markdown
## FTM Code Review Report

### Files Reviewed
- local/pluginname/file1.php
- local/pluginname/file2.php

### Security (Agent 1)
| Severity | Issue | File:Line | Fix |
|----------|-------|-----------|-----|
| CRITICAL | SQL Injection | manager.php:45 | Use placeholders |

### Logic (Agent 2)
| Severity | Issue | File:Line | Fix |
|----------|-------|-----------|-----|
| HIGH | Wrong framework ID | import.php:112 | Use constant |

### Quality (Agent 3)
| Severity | Issue | File:Line | Fix |
|----------|-------|-----------|-----|
| MEDIUM | Duplicated code | report.php:200-250 | Extract method |

### Standards (Agent 4)
| Severity | Issue | File:Line | Fix |
|----------|-------|-----------|-----|
| LOW | Missing PHPDoc | helper.php:1 | Add header |

### Summary
- CRITICAL: 1 | HIGH: 2 | MEDIUM: 3 | LOW: 5
- Estimated fix time: [X files to modify]

### Fixes Applied
[List of changes made]

### Verification
- PHP syntax: PASS
- Playwright health: PASS
- Regressions: NONE
```

## Tech Stack Reference

- **Moodle**: 4.5+ / 5.0
- **PHP**: 8.1+
- **Database**: MySQL/MariaDB/PostgreSQL
- **Frontend**: Mustache templates, AMD modules
- **Testing**: PHPUnit, Behat

## Key Moodle Patterns

```php
// Correct parameter handling
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Correct database query
$records = $DB->get_records('table', ['field' => $value]);

// Correct output escaping
echo s($userdata);
echo format_string($title);

// Correct AJAX endpoint
define('AJAX_SCRIPT', true);
require_login();
require_sesskey();
header('Content-Type: application/json');
```
