# FTM Release Command

## Overview
Prepare FTM plugins for release with complete validation, documentation, and deployment steps.

---

## Pre-Release Checklist

### Step 1: Version Bump

For each modified plugin, update `version.php`:

```php
$plugin->version = 2026012300;  // YYYYMMDDXX format
$plugin->release = '1.2.0';     // Semantic version
$plugin->maturity = MATURITY_STABLE;
```

Version format: `YYYYMMDDXX`
- YYYY: Year (2026)
- MM: Month (01-12)
- DD: Day (01-31)
- XX: Revision (00-99)

### Step 2: Changelog Update

Create/update CHANGES.md in each plugin:

```markdown
# Changelog

## [1.2.0] - 2026-01-23

### Added
- New feature X
- Support for Y

### Changed
- Improved Z performance
- Updated UI for W

### Fixed
- Bug in A (Issue #123)
- Error handling in B

### Security
- Fixed SQL injection in C (SEC001)
```

### Step 3: Full Validation

Run complete validation suite:

```bash
# PHP syntax on all files
find local/ -name "*.php" -exec php -l {} \;

# Playwright health check
node playwright_pm/ftm_health_check.mjs
```

### Step 4: Deep Review

Execute `/ftm-deepreview` for comprehensive analysis:
- Security scan all plugins
- Logic verification
- Code quality check
- Standards compliance

### Step 5: Documentation Review

Verify documentation is current:

- [ ] CLAUDE.md updated
- [ ] WORKFLOW.md updated
- [ ] README.md in each plugin
- [ ] Lang strings complete (EN, IT)

---

## Release Process

### Step 1: Create Release Branch (Optional)

```bash
git checkout -b release/v1.2.0
```

### Step 2: Final Commit

```bash
git add .
git commit -m "Release v1.2.0: [Summary of changes]

Changes:
- Feature 1
- Feature 2
- Bug fix 1

Plugins updated:
- local_coachmanager v2026012300
- local_competencymanager v2026012300

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

### Step 3: Tag Release

```bash
git tag -a v1.2.0 -m "Release v1.2.0"
git push origin v1.2.0
```

### Step 4: Push to GitHub

```bash
git push origin main
# Or if using release branch
git push origin release/v1.2.0
```

### Step 5: Create GitHub Release

```bash
gh release create v1.2.0 \
  --title "FTM Plugins v1.2.0" \
  --notes "## What's New

### Features
- Feature 1
- Feature 2

### Bug Fixes
- Fix 1
- Fix 2

### Plugins Updated
| Plugin | Version |
|--------|---------|
| coachmanager | 2026012300 |
| competencymanager | 2026012300 |

See CHANGES.md for full details."
```

---

## Deployment to Test Server

### Step 1: Upload via FTP

Upload modified files to:
```
https://test-urc.hizuvala.myhostpoint.ch/local/
```

### Step 2: Trigger Moodle Upgrade

Navigate to:
```
https://test-urc.hizuvala.myhostpoint.ch/admin/index.php
```

Or via CLI:
```bash
php admin/cli/upgrade.php
```

### Step 3: Verify Deployment

```bash
# Run Playwright health check
node playwright_pm/ftm_health_check.mjs
```

Check:
- All plugins show new version
- No PHP errors
- Functionality works
- Database upgrades completed

---

## Rollback Plan

If issues found after deployment:

### Quick Rollback
```bash
# Revert to previous commit
git revert HEAD

# Re-upload previous version
# Via FTP or deployment tool
```

### Database Rollback
If database changes need reverting:
```sql
-- Check current version
SELECT * FROM {config_plugins}
WHERE plugin LIKE 'local_%' AND name = 'version';

-- Manual version reset (CAREFUL!)
UPDATE {config_plugins}
SET value = 'previous_version'
WHERE plugin = 'local_pluginname' AND name = 'version';
```

---

## Release Report

```markdown
# FTM Release Report

**Version**: v1.2.0
**Date**: 2026-01-23
**Released by**: [Name]

## Plugins Released

| Plugin | Old Version | New Version |
|--------|-------------|-------------|
| coachmanager | 2026012200 | 2026012300 |
| competencymanager | 2026012200 | 2026012300 |

## Changes Summary

### New Features
- [List]

### Bug Fixes
- [List]

### Security Fixes
- [List]

## Validation Results

| Check | Status |
|-------|--------|
| PHP Syntax | PASS |
| Security Scan | PASS |
| Playwright Health | PASS |
| Standards | PASS |

## Deployment

| Server | Status | Verified |
|--------|--------|----------|
| Test | Deployed | Yes |
| Production | Pending | - |

## Post-Release Tasks

- [ ] Notify team
- [ ] Update documentation
- [ ] Monitor error logs
- [ ] Schedule production deployment

## Notes
[Any special considerations]
```

---

## Checklist Summary

Before release:
- [ ] All tests passing
- [ ] Security scan clean
- [ ] Documentation updated
- [ ] Version numbers bumped
- [ ] Changelog written
- [ ] Git tagged
- [ ] GitHub release created

After deployment:
- [ ] Health check passing
- [ ] Functionality verified
- [ ] Team notified
- [ ] Logs monitored
