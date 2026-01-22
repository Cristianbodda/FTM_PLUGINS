# FTM Health Check Command

## Overview
Quick health check of all FTM plugins using Playwright automation.

---

## Quick Health Check

### Step 1: Run Playwright Health Check

```bash
node playwright_pm/ftm_health_check.mjs
```

### Step 2: Analyze Results

The script checks 10 plugins across 17 pages:

| Plugin | Pages | Priority |
|--------|-------|----------|
| coachmanager | 3 | HIGH |
| competencymanager | 3 | HIGH |
| competencyreport | 1 | MEDIUM |
| competencyxmlimport | 2 | HIGH |
| ftm_cpurc | 1 | LOW |
| ftm_hub | 1 | HIGH |
| ftm_scheduler | 1 | HIGH |
| ftm_testsuite | 2 | MEDIUM |
| labeval | 1 | MEDIUM |
| selfassessment | 2 | HIGH |

### Step 3: Interpret Status

| Status | Meaning | Action |
|--------|---------|--------|
| OK | Page loads, content present | None |
| WARN | Page loads, some content missing | Investigate |
| ERROR | Page fails to load or has errors | Fix immediately |

---

## Detailed Health Check

### Check Specific Plugin

```bash
# View screenshots for specific plugin
ls playwright_pm/screenshots/coachmanager_*.png
```

### Check Recent Reports

```bash
# View scheduled health check reports
node playwright_pm/view_reports.mjs
```

### Manual Page Verification

If automated check shows issues, manually verify:

```
https://test-urc.hizuvala.myhostpoint.ch/local/[plugin]/[page].php
```

---

## Common Issues & Fixes

### ERROR: Redirect to Login
**Cause**: Session expired or auth issue
**Fix**: Check require_login() placement

### ERROR: PHP Fatal Error
**Cause**: Syntax error or missing class
**Fix**: Check PHP error log, fix code

### ERROR: Database Error
**Cause**: Wrong table name or missing field
**Fix**: Verify table names, run upgrade

### WARN: Content Missing
**Cause**: Expected keywords not found
**Fix**: Check if page content changed

### ERROR: 500 Internal Server Error
**Cause**: Server configuration or PHP error
**Fix**: Check server error logs

---

## Health Check Report Format

```markdown
## FTM Health Check Report

**Date**: [Timestamp]
**Server**: test-urc.hizuvala.myhostpoint.ch

### Summary
| Status | Count |
|--------|-------|
| OK | 15 |
| WARN | 2 |
| ERROR | 0 |

### By Plugin

#### coachmanager
| Page | Status | Notes |
|------|--------|-------|
| Coach Dashboard V2 | OK | |
| Coach Dashboard V1 | OK | |
| Bilancio Competenze | OK | |

#### competencymanager
| Page | Status | Notes |
|------|--------|-------|
| Sector Admin | OK | |
| Student Report | OK | |
| Area Mapping | WARN | Missing 'mapping' |

[... continue for all plugins ...]

### Screenshots
Saved to: playwright_pm/screenshots/

### Errors to Fix
1. [None / List any ERROR items]

### Warnings to Investigate
1. competencymanager/Area Mapping - content check failed
```

---

## Scheduled Health Checks

Health checks run automatically:
- **Daily at 9:00 AM** via Windows Task Scheduler
- Reports saved to `playwright_pm/reports/`
- History logged to `playwright_pm/health_history.log`

### View Scheduled Task

```powershell
Get-ScheduledTask -TaskName "FTM_Health_Check"
```

### Run Manual Scheduled Check

```bash
node playwright_pm/scheduled_health_check.mjs
```

---

## Integration with Development

### Before Committing
```bash
# Quick health check
node playwright_pm/ftm_health_check.mjs

# If all OK, proceed with commit
git add . && git commit -m "..."
```

### After FTP Upload
```bash
# Verify deployment
node playwright_pm/ftm_health_check.mjs

# Compare with previous results
diff playwright_pm/reports/latest.json playwright_pm/reports/previous.json
```

### Continuous Monitoring
```bash
# Watch mode (run every 5 minutes)
while true; do
  node playwright_pm/ftm_health_check.mjs
  sleep 300
done
```

---

## Quick Commands

```bash
# Full health check
node playwright_pm/ftm_health_check.mjs

# View specific plugin screenshots
start playwright_pm/screenshots/coachmanager_*.png

# View recent reports
node playwright_pm/view_reports.mjs

# Check scheduled task status
schtasks /query /tn "FTM_Health_Check"
```
