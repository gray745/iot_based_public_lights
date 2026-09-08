$file = 'C:\php\php.ini'
$content = Get-Content $file
$content = $content -replace ';extension=pdo_pgsql', 'extension=pdo_pgsql'
$content = $content -replace ';extension=pgsql', 'extension=pgsql'
$content = $content -replace ';extension=openssl', 'extension=openssl'
$content = $content -replace ';extension=curl', 'extension=curl'
$content = $content -replace ';extension=mbstring', 'extension=mbstring'
$content | Set-Content $file
Write-Host "Done - extensions enabled"
