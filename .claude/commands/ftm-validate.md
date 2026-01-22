# FTM Validate Build Command

## Overview
Validates FTM plugin code for syntax, standards compliance, and functionality.

## Workflow

### Step 1: PHP Syntax Validation

```bash
# Check all modified PHP files
for file in $(git diff --name-only HEAD | grep '\.php$'); do
    php -l "$file"
done
```

If syntax errors found:
1. Document the error and file
2. Fix the syntax error
3. Re-validate

### Step 2: Moodle Structure Validation

Check each modified plugin for required files:

```
local/pluginname/
├── version.php          # REQUIRED
├── lang/en/             # REQUIRED
│   └── local_pluginname.php
├── db/
│   └── access.php       # If capabilities used
├── classes/             # PSR-4 autoload
└── index.php            # Entry point
```

Validate version.php:
```php
$plugin->component = 'local_pluginname';  // Must match folder
$plugin->version = 2026012200;            // YYYYMMDDXX format
$plugin->requires = 2024100700;           // Moodle 4.5
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0';
```

### Step 3: Security Quick Scan

Fast scan for obvious issues:

```php
// FAIL: Direct superglobals
$_GET['id'], $_POST['action'], $_REQUEST['data']

// FAIL: SQL concatenation
"SELECT * FROM {table} WHERE id = " . $id

// FAIL: Unescaped output
echo $variable;  // Without s() or format_string()

// FAIL: Missing auth
// Page without require_login()

// FAIL: Missing sesskey
// AJAX without require_sesskey()
```

### Step 4: Database Schema Validation

If db/install.xml modified:
```bash
# Check XMLDB syntax
php admin/cli/check_database_schema.php
```

Verify:
- Primary key is 'id' BIGINT
- Table name follows `local_pluginname_tablename`
- Foreign keys defined
- Indexes on search fields
- timecreated/timemodified present

### Step 5: Lang String Validation

Check for hardcoded strings:
```php
// FAIL: Hardcoded
echo '<h1>My Page Title</h1>';
echo 'Error: Something went wrong';

// PASS: Lang strings
echo '<h1>' . get_string('pagetitle', 'local_pluginname') . '</h1>';
echo get_string('error_general', 'local_pluginname');
```

Verify lang files exist:
```bash
ls local/pluginname/lang/en/local_pluginname.php
ls local/pluginname/lang/it/local_pluginname.php  # For FTM
```

### Step 6: Playwright Health Check

```bash
node playwright_pm/ftm_health_check.mjs
```

Verify:
- All 10 plugins accessible
- No PHP errors on pages
- Expected content present
- Screenshots captured

### Step 7: Report

```markdown
## FTM Validation Report

### PHP Syntax
| File | Status |
|------|--------|
| manager.php | PASS |
| ajax/save.php | PASS |

### Structure
| Check | Status |
|-------|--------|
| version.php | PASS |
| lang/en | PASS |
| lang/it | PASS |
| access.php | PASS |

### Security Quick Scan
| Issue | Count |
|-------|-------|
| Direct superglobals | 0 |
| SQL concatenation | 0 |
| Unescaped output | 0 |
| Missing auth | 0 |

### Database
| Check | Status |
|-------|--------|
| XMLDB valid | PASS |
| Naming correct | PASS |
| Keys defined | PASS |

### Playwright Health
| Plugin | Status |
|--------|--------|
| coachmanager | OK |
| competencymanager | OK |
| ... | ... |

### Overall: PASS/FAIL
```

## Failure Handling

### Syntax Error
```
Error: Parse error in local/plugin/file.php line 45
Action: Fix syntax, re-run validation
```

### Security Issue
```
Error: SQL injection in local/plugin/manager.php:123
Action: Replace with parameterized query
```

### Missing File
```
Error: Missing lang/it/local_pluginname.php
Action: Create Italian translation file
```

### Playwright Failure
```
Error: coachmanager/index.php returns 500
Action: Check PHP error log, fix issue
```

## Success Criteria

All checks must pass:
- [ ] PHP syntax valid
- [ ] Structure complete
- [ ] No security issues
- [ ] Database schema valid
- [ ] Lang strings present
- [ ] Playwright health OK

Only then report: **VALIDATION PASSED**
