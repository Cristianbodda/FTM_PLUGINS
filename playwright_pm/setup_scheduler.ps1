# FTM Health Check - Setup Windows Task Scheduler
# Esegui come Amministratore: powershell -ExecutionPolicy Bypass -File setup_scheduler.ps1

$TaskName = "FTM_Health_Check"
$TaskPath = "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW\playwright_pm\run_health_check.bat"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "FTM Health Check - Setup Scheduler" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Rimuovi task esistente se presente
$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Write-Host "`nRimuovo task esistente..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

Write-Host "`nSeleziona frequenza:" -ForegroundColor Green
Write-Host "1. Ogni ora"
Write-Host "2. Ogni 4 ore"
Write-Host "3. Ogni giorno alle 9:00"
Write-Host "4. Ogni giorno alle 9:00 e 18:00"
Write-Host "5. Solo manuale (non schedulare)"

$choice = Read-Host "`nScelta (1-5)"

switch ($choice) {
    "1" {
        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1)
        $freq = "ogni ora"
    }
    "2" {
        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 4)
        $freq = "ogni 4 ore"
    }
    "3" {
        $trigger = New-ScheduledTaskTrigger -Daily -At "09:00"
        $freq = "ogni giorno alle 9:00"
    }
    "4" {
        $trigger1 = New-ScheduledTaskTrigger -Daily -At "09:00"
        $trigger2 = New-ScheduledTaskTrigger -Daily -At "18:00"
        $trigger = @($trigger1, $trigger2)
        $freq = "ogni giorno alle 9:00 e 18:00"
    }
    "5" {
        Write-Host "`nNessuna schedulazione configurata." -ForegroundColor Yellow
        Write-Host "Esegui manualmente con: node scheduled_health_check.mjs" -ForegroundColor Gray
        exit
    }
    default {
        Write-Host "Scelta non valida" -ForegroundColor Red
        exit
    }
}

# Crea azione
$action = New-ScheduledTaskAction -Execute $TaskPath -WorkingDirectory "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW\playwright_pm"

# Crea settings
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

# Registra task
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "FTM Plugins Health Check automatico"

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "Task schedulato con successo!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "Nome: $TaskName"
Write-Host "Frequenza: $freq"
Write-Host "Script: $TaskPath"
Write-Host "`nPer verificare: Get-ScheduledTask -TaskName '$TaskName'"
Write-Host "Per rimuovere: Unregister-ScheduledTask -TaskName '$TaskName'"
