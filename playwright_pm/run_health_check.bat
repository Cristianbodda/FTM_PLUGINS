@echo off
REM FTM Health Check - Script per Task Scheduler
REM Esegue health check periodico

cd /d "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW\playwright_pm"
node scheduled_health_check.mjs >> logs\scheduler.log 2>&1

REM Crea cartella logs se non esiste
if not exist logs mkdir logs
