$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open('C:\Users\cristian.bodda\desktop\planning.xlsx')

Write-Host "=== FOGLI ==="
for ($i = 1; $i -le $workbook.Sheets.Count; $i++) {
    Write-Host ("  " + $i + ". " + $workbook.Sheets.Item($i).Name)
}

Write-Host "`n=== PRIMO FOGLIO - Prime 50 righe ==="
$sheet = $workbook.Sheets.Item(1)
for ($r = 1; $r -le 50; $r++) {
    $line = ""
    for ($c = 1; $c -le 25; $c++) {
        $val = $sheet.Cells.Item($r, $c).Text
        if ($val.Length -gt 18) { $val = $val.Substring(0, 18) }
        if ($val) { $line += "$val | " }
    }
    if ($line -and $line.Trim() -ne "|") { Write-Host ("R" + $r + ": " + $line) }
}

$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
