#!/bin/sh
# FTM Jobs - Cron script for Hostpoint Flex Server (FreeBSD)
# Runs nightly BEFORE the Moodle scheduled task (default: 02:00, Moodle task at 02:30)
#
# Setup:
#   1. Upload indeed-pp-cli-freebsd to ~/bin/indeed-pp-cli  (chmod +x)
#   2. mkdir -p ~/data/ftm_jobs
#   3. Add to crontab: 0 2 * * * /home/user/bin/ftm_jobs_cron.sh >> /home/user/logs/ftm_jobs.log 2>&1
#   4. In Moodle Admin > Scheduled Tasks: enable "FTM Job Search - Importa annunci"
#
# Adjust these paths to match your Flex Server setup:
INDEED_CLI="$HOME/bin/indeed-pp-cli"
OUTPUT_DIR="$HOME/data/ftm_jobs"
LOG_PREFIX="[$(date '+%Y-%m-%d %H:%M:%S')]"

mkdir -p "$OUTPUT_DIR"

echo "$LOG_PREFIX === FTM Jobs Cron Start ==="

# --- Indeed: sweep all 7 FTM sectors ---
if [ ! -x "$INDEED_CLI" ]; then
    echo "$LOG_PREFIX ERRORE: indeed-pp-cli non trovato in $INDEED_CLI"
    exit 1
fi

echo "$LOG_PREFIX Indeed: avvio sector sweep..."
"$INDEED_CLI" sector sweep --force 2>&1
SWEEP_EXIT=$?

if [ $SWEEP_EXIT -ne 0 ]; then
    echo "$LOG_PREFIX ATTENZIONE: sector sweep uscito con codice $SWEEP_EXIT (proseguo comunque)"
fi

echo "$LOG_PREFIX Indeed: esportazione JSON..."
"$INDEED_CLI" sector export --json > "$OUTPUT_DIR/indeed.json" 2>&1
EXPORT_EXIT=$?

if [ $EXPORT_EXIT -ne 0 ]; then
    echo "$LOG_PREFIX ERRORE: export indeed fallito (exit $EXPORT_EXIT)"
else
    LINES=$(wc -c < "$OUTPUT_DIR/indeed.json")
    echo "$LOG_PREFIX Indeed: indeed.json scritto ($LINES bytes)"
fi

echo "$LOG_PREFIX === FTM Jobs Cron Done ==="
