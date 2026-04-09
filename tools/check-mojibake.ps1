param(
    [string]$Root = (Get-Location).Path
)

$ErrorActionPreference = 'Stop'
$regex = '[\u00C2\u00C3\u00D4\uFFFD]'

$matches = Get-ChildItem -Path $Root -Recurse -File |
    Where-Object {
        $_.Extension -in @('.php', '.js', '.css', '.md') -and
        $_.FullName -notmatch '\\node_modules\\' -and
        $_.FullName -notmatch '\\.git\\' -and
        $_.Name -notmatch 'app_backup_'
    } |
    Select-String -Pattern $regex

if ($matches) {
    Write-Host 'Mojibake detectado:'
    $matches | Select-Object -First 200 Path, LineNumber, Line | Format-Table -AutoSize
    exit 1
}

Write-Host 'OK: nenhum mojibake detectado.'
exit 0
