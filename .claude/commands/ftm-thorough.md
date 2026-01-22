# FTM 3-Pass Verification Protocol

## Overview
Mandatory 3-pass verification before completing any FTM development task. Each pass focuses on specific concerns and must be documented.

---

## Pass 1: Correctness & Functionality

**Goal**: Verify implementation matches requirements and works correctly.

### Checklist

#### Logic Verification
- [ ] Code logic matches the requested feature/fix
- [ ] Database queries return expected data
- [ ] Correct framework IDs used (9=FTM-01, 10=FTM_GEN)
- [ ] Sector aliases handled (AUTOMOBILE↔AUTOVEICOLO, etc.)
- [ ] Competency IDNumbers match expected format

#### Type & Import Verification
- [ ] All required files are included
- [ ] Namespaces match plugin structure
- [ ] Class autoloading works (PSR-4)
- [ ] No undefined variables or functions

#### Function Behavior
- [ ] Methods return correct types
- [ ] Side effects are intentional and documented
- [ ] Error conditions throw appropriate exceptions
- [ ] Success paths complete fully

#### Database Operations
- [ ] Correct table names used (especially `qbank_competenciesbyquestion`)
- [ ] Joins are correct
- [ ] WHERE conditions filter properly
- [ ] INSERT/UPDATE/DELETE affect intended records only

#### Testing
- [ ] Manual test performed or documented
- [ ] Edge cases considered
- [ ] Playwright health check passes

### Document Findings
```markdown
## Pass 1: Correctness

### Items Examined
- [List of files/functions checked]

### Issues Found
- [Issue 1]: [Resolution]
- [Issue 2]: [Resolution]

### Unresolved
- [Any remaining concerns]

### Status: PASS/FAIL
```

---

## Pass 2: Edge Cases & Security

**Goal**: Ensure robustness and security compliance.

### Checklist

#### Input Validation
- [ ] All parameters use `required_param`/`optional_param`
- [ ] Correct PARAM_* type used (INT, TEXT, ALPHANUMEXT, etc.)
- [ ] No `$_GET`, `$_POST`, `$_REQUEST` direct access
- [ ] No `PARAM_RAW` unless absolutely necessary

#### Null/Empty Handling
- [ ] Null checks before object access
- [ ] Empty array handling
- [ ] Zero value handling (0 vs null vs false)
- [ ] Empty string handling

#### Authentication & Authorization
- [ ] `require_login()` at page start
- [ ] `require_capability()` for restricted actions
- [ ] `require_sesskey()` for state-changing operations
- [ ] Context set correctly

#### SQL Security
- [ ] All queries use placeholders
- [ ] No string concatenation in SQL
- [ ] Table names use `{tablename}` format
- [ ] User input never in SQL directly

#### Output Security
- [ ] All output escaped with `s()` or `format_string()`
- [ ] HTML uses Mustache templates where possible
- [ ] No raw `echo $variable`
- [ ] JSON responses properly encoded

#### Error Handling
- [ ] Exceptions caught appropriately
- [ ] User-friendly error messages (no stack traces)
- [ ] Errors logged for debugging
- [ ] Graceful degradation

#### Boundary Conditions
- [ ] Empty collections handled
- [ ] Maximum limits respected
- [ ] Date/time edge cases (midnight, DST, etc.)
- [ ] Unicode/special characters handled

### Document Findings
```markdown
## Pass 2: Security & Edge Cases

### Items Examined
- [Security checks performed]

### Issues Found
- [SEC00X]: [File:line] - [Resolution]

### Edge Cases Tested
- [Case 1]: [Result]
- [Case 2]: [Result]

### Unresolved
- [Any remaining concerns]

### Status: PASS/FAIL
```

---

## Pass 3: Maintainability & Moodle Standards

**Goal**: Ensure code quality and standards compliance.

### Checklist

#### File Structure
- [ ] PHPDoc header with @package, @copyright, @license
- [ ] `defined('MOODLE_INTERNAL') || die();` in includes
- [ ] Correct namespace declaration
- [ ] Logical file organization

#### Code Style
- [ ] Consistent indentation (4 spaces)
- [ ] Meaningful variable/function names
- [ ] No functions > 50 lines
- [ ] Single responsibility per function/class

#### Documentation
- [ ] PHPDoc for public methods
- [ ] Complex logic has comments
- [ ] README updated if needed
- [ ] CLAUDE.md updated if patterns changed

#### Lang Strings
- [ ] All user-facing text uses `get_string()`
- [ ] Lang strings defined in lang/en and lang/it
- [ ] String keys are descriptive

#### Dead Code
- [ ] No unused variables
- [ ] No commented-out code
- [ ] No unreachable code
- [ ] No unused imports

#### DRY Compliance
- [ ] No duplicated code blocks
- [ ] Common logic in shared functions
- [ ] Constants for repeated values
- [ ] FTM_COMMON used where appropriate

#### Moodle Patterns
- [ ] $PAGE setup correct (context, url, title, heading)
- [ ] $OUTPUT->header() and footer() used
- [ ] Events triggered where appropriate
- [ ] Capabilities follow naming convention

### Document Findings
```markdown
## Pass 3: Maintainability & Standards

### Items Examined
- [Standards checks performed]

### Issues Found
- [STR00X]: [File:line] - [Resolution]

### Code Quality Improvements
- [Improvement 1]: [Applied/Deferred]

### Unresolved
- [Any remaining concerns]

### Status: PASS/FAIL
```

---

## Final Report

```markdown
# FTM 3-Pass Verification Complete

## Task
[Description of completed work]

## Pass Results

| Pass | Focus | Status |
|------|-------|--------|
| 1 | Correctness & Functionality | PASS/FAIL |
| 2 | Edge Cases & Security | PASS/FAIL |
| 3 | Maintainability & Standards | PASS/FAIL |

## Files Modified
- [file1.php]: [summary of changes]
- [file2.php]: [summary of changes]

## Issues Resolved
- [Total count and breakdown]

## Outstanding Items
- [Any deferred improvements]

## Verification
- [ ] PHP syntax valid
- [ ] Playwright health check passed
- [ ] Manual testing completed

## Overall Status: COMPLETE / NEEDS ATTENTION

---
Ready for commit: YES / NO
```

---

## When to Use

**Required for:**
- New features
- Bug fixes
- Refactoring
- Any code that will be committed

**Not required for:**
- Documentation only changes
- Comment updates
- Research/exploration tasks
