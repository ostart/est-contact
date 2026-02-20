# Экспорт MySQL в SQL-дамп (Windows PowerShell).
# Запуск из корня проекта: .\scripts\export-db.ps1
# Или с именем файла: .\scripts\export-db.ps1 -DumpFile mydump.sql

param(
    [string] $DumpFile = "dump_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql"
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot\..

if (Test-Path .env) {
    Get-Content .env | ForEach-Object {
        if ($_ -match '^\s*([^#][^=]+)=(.*)$') {
            [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim(), 'Process')
        }
    }
}

$dbHost = if ($env:DB_HOST) { $env:DB_HOST } else { "127.0.0.1" }
$dbPort = if ($env:DB_PORT) { $env:DB_PORT } else { "3306" }
$dbName = $env:DB_DATABASE
$dbUser = $env:DB_USERNAME
$dbPass = $env:DB_PASSWORD

if (-not $dbName -or -not $dbUser) {
    Write-Host "Задайте DB_DATABASE и DB_USERNAME в .env"
    exit 1
}

Write-Host "==> Экспорт: $dbName -> $DumpFile"
if ($dbPass) { $env:MYSQL_PWD = $dbPass }
try {
    & mysqldump -h $dbHost -P $dbPort -u $dbUser --single-transaction --routines --triggers -r $DumpFile $dbName
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}
Write-Host "==> Готово: $DumpFile"
