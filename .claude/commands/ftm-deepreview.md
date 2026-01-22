# FTM Deep Review Command

## Overview
Comprehensive deep review using all 4 FTM agents in parallel, followed by Playwright visual verification. This is the most thorough review available.

---

## Phase 1: Preparation

### Gather Context
```bash
# Get all changes since main
git diff main...HEAD > /tmp/ftm_diff.txt

# List modified files
git diff --name-only main...HEAD

# Get recent commits
git log --oneline -10
```

### Identify Scope
- Which plugins are affected?
- What type of changes? (feature/fix/refactor)
- Which frameworks/sectors involved?
- Any database changes?

---

## Phase 2: Launch 4 Parallel Deep Dive Agents

### Agent 1: Moodle Security Scanner (Red)

**Focus**: All security checklist items (SEC001-SEC012)

**Deep Dive Areas**:
1. **SQL Injection Analysis**
   - Trace all `$DB->` calls
   - Verify placeholder usage
   - Check for string concatenation in queries

2. **XSS Analysis**
   - Trace all `echo` statements
   - Verify escaping functions used
   - Check Mustache template safety

3. **Authentication Flow**
   - Map all entry points
   - Verify `require_login()` placement
   - Check capability requirements

4. **AJAX Security**
   - All endpoints have AJAX_SCRIPT
   - Sesskey verified
   - Proper error responses

**Output**: Security risk matrix with severity ratings

---

### Agent 2: FTM Investigator (Yellow)

**Focus**: Logic correctness and data flow

**Deep Dive Areas**:
1. **Database Query Analysis**
   - Trace query execution paths
   - Verify JOIN conditions
   - Check table name correctness

2. **Framework/Competency Logic**
   - Correct framework IDs (9, 10)
   - Sector alias resolution
   - Competency IDNumber parsing

3. **Event Flow**
   - Observer subscriptions correct
   - Events triggered at right times
   - Data passed correctly

4. **State Management**
   - Session data handled properly
   - Cache invalidation correct
   - Concurrent access safe

**Output**: Logic flow diagram with potential issues

---

### Agent 3: Moodle Refactor (Blue)

**Focus**: Code quality and architecture

**Deep Dive Areas**:
1. **Duplication Analysis**
   - Find similar code blocks across plugins
   - Identify extraction opportunities
   - Check ftm_common usage

2. **Complexity Analysis**
   - Functions > 50 lines
   - Cyclomatic complexity
   - Nesting depth

3. **Dependency Analysis**
   - Plugin interdependencies
   - Circular references
   - Tight coupling

4. **Pattern Compliance**
   - Moodle patterns followed
   - FTM patterns consistent
   - Anti-patterns present

**Output**: Refactoring recommendations with priority

---

### Agent 4: Standards Validator (Green)

**Focus**: Moodle coding standards and structure

**Deep Dive Areas**:
1. **File Structure**
   - Required files present
   - Correct locations
   - Naming conventions

2. **Code Style**
   - PHPDoc completeness
   - Naming consistency
   - Formatting compliance

3. **Internationalization**
   - All strings use get_string()
   - Lang files complete (EN, IT)
   - Placeholders correct

4. **Capability System**
   - Capabilities defined
   - Context levels correct
   - Role assignments appropriate

**Output**: Standards compliance report

---

## Phase 3: Consolidation Matrix

Combine all agent findings into priority matrix:

```
╔═══════════════════════════════════════════════════════════════════╗
║ PRIORITY │ CATEGORY      │ AGENT    │ FILE:LINE    │ ISSUE       ║
╠═══════════════════════════════════════════════════════════════════╣
║ CRITICAL │ Security      │ Scanner  │ ajax/x.php:12│ No sesskey  ║
║ CRITICAL │ Security      │ Scanner  │ lib.php:45   │ SQL inject  ║
║ HIGH     │ Logic         │ Investig │ manager:89   │ Wrong table ║
║ HIGH     │ Logic         │ Investig │ import:156   │ Bad FW ID   ║
║ MEDIUM   │ Quality       │ Refactor │ report:200   │ Duplicate   ║
║ MEDIUM   │ Standards     │ Validate │ index:1      │ No PHPDoc   ║
║ LOW      │ Standards     │ Validate │ lang/en:45   │ Unused str  ║
╚═══════════════════════════════════════════════════════════════════╝
```

---

## Phase 4: Sequential Fix Implementation

Using ftm-implementer, fix issues in priority order:

### Round 1: CRITICAL
```
1. Fix security vulnerabilities
2. Verify with security scanner
3. PHP syntax check
```

### Round 2: HIGH
```
1. Fix logic errors
2. Verify with investigator
3. Test affected functionality
```

### Round 3: MEDIUM
```
1. Apply refactoring
2. Verify no regressions
3. Check code quality
```

### Round 4: LOW
```
1. Fix standards issues
2. Update documentation
3. Final lint check
```

---

## Phase 5: Playwright Visual Verification

```bash
# Full health check
node playwright_pm/ftm_health_check.mjs

# Specific plugin tests if available
node playwright_pm/coach_dashboard_test.mjs
```

**Verify**:
- All 10 plugins load without errors
- Modified pages render correctly
- No visual regressions
- Screenshots captured for documentation

---

## Phase 6: Final Report

```markdown
# FTM Deep Review Report

**Date**: [Date]
**Reviewer**: Claude (4 Agents)
**Scope**: [Files/plugins reviewed]

## Executive Summary
[1-2 sentence overview of findings and fixes]

## Agent Reports

### Security Scanner (Red)
- Issues found: X
- Critical: Y
- Fixed: Z
- Remaining: W

### FTM Investigator (Yellow)
- Issues found: X
- Logic errors: Y
- Fixed: Z
- Remaining: W

### Moodle Refactor (Blue)
- Issues found: X
- Duplications: Y
- Refactored: Z
- Deferred: W

### Standards Validator (Green)
- Issues found: X
- Standards violations: Y
- Fixed: Z
- Remaining: W

## Changes Made

| File | Changes | Agent |
|------|---------|-------|
| file1.php | Fixed SQL injection | Security |
| file2.php | Corrected table name | Investigator |
| file3.php | Extracted method | Refactor |

## Playwright Verification

| Plugin | Before | After |
|--------|--------|-------|
| coachmanager | OK | OK |
| competencymanager | WARN | OK |

## Metrics

| Metric | Before | After |
|--------|--------|-------|
| Security issues | 5 | 0 |
| Logic errors | 3 | 0 |
| Code duplications | 8 | 2 |
| Standards violations | 12 | 0 |

## Recommendations

1. [Future improvement 1]
2. [Future improvement 2]

## Conclusion

**Status**: APPROVED FOR COMMIT / NEEDS ATTENTION

**Next Steps**:
- [ ] Commit changes
- [ ] Push to GitHub
- [ ] Upload to test server
- [ ] Final Playwright verification
```

---

## When to Use

**Use Deep Review for**:
- Major new features
- Significant refactoring
- Security-sensitive changes
- Pre-release validation
- After long development sessions

**Regular review sufficient for**:
- Minor bug fixes
- Small UI changes
- Documentation updates
