$cssPath='D:\work\WDH-files\catalog\view\theme\tt_uren1\stylesheet\critical-category-mobile.css'
$xmlPath='D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml'

$css=Get-Content $cssPath -Raw
$xml=Get-Content $xmlPath -Raw

$pattern='(?s)(<style>\s*)(.*?)(\s*</style>)'
$m=[regex]::Matches($xml,$pattern)

Write-Host "STYLE BLOCKS:" $m.Count

if($m.Count -ne 1){
    Write-Host 'STOP: expected exactly one <style>...</style> block'
    exit 1
}

$backup=$xmlPath+'.bak-before-sync-'+(Get-Date -Format 'yyyyMMdd-HHmmss')
Copy-Item $xmlPath $backup

$replacement='$1'+$css+'$3'
$xml=[regex]::Replace($xml,$pattern,$replacement,1)

[System.IO.File]::WriteAllText($xmlPath,$xml,(New-Object System.Text.UTF8Encoding($false)))

Write-Host 'SYNCED'
Write-Host 'BACKUP:' $backup