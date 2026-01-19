$xl = New-Object -ComObject Excel.Application
$xl.DisplayAlerts = $false
$wb = $xl.Workbooks.Open("C:\Users\cristian.bodda\Desktop\planning.xlsx")
$ws = $wb.Sheets.Item(1)
$range = $ws.UsedRange
$maxRows = [Math]::Min($range.Rows.Count, 60)
$maxCols = [Math]::Min($range.Columns.Count, 20)

for($r=1; $r -le $maxRows; $r++) {
    $row = ""
    for($c=1; $c -le $maxCols; $c++) {
        $val = $ws.Cells.Item($r,$c).Text
        if ($val) {
            $row += $val + "|"
        } else {
            $row += "|"
        }
    }
    Write-Host $row
}

$wb.Close($false)
$xl.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($xl) | Out-Null
