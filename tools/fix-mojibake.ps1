param(
    [string]$Root = (Get-Location).Path,
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
$latin1 = [System.Text.Encoding]::GetEncoding(28591)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$pattern = '[\u00C2\u00C3\u00D4\uFFFD]'

function Repair-MojibakeText {
    param([string]$Text)

    $fixed = $Text

    # Attempt to decode common mojibake (UTF-8 bytes interpreted as Latin-1)
    for ($i = 0; $i -lt 3; $i++) {
        if ($fixed -notmatch $pattern) { break }
        $bytes = $latin1.GetBytes($fixed)
        $decoded = [System.Text.Encoding]::UTF8.GetString($bytes)
        if ($decoded -eq $fixed) { break }
        $fixed = $decoded
    }

    # Remove stray U+00C2 ("A-circumflex") before ascii punctuation/spaces.
    $fixed = [regex]::Replace($fixed, "`u00C2(?=[\x00-\x7F])", '')

    # Normalize a few known leftovers in this project.
    $fixed = $fixed.Replace("In`u00C3`u00ADcio", "In`u00EDcio")
    $fixed = $fixed.Replace("In`u00C3cio", "In`u00EDcio")

    return $fixed
}

$targets = Get-ChildItem -Path $Root -Recurse -File |
    Where-Object {
        $_.Extension -in @('.php', '.js', '.css', '.md') -and
        $_.FullName -notmatch '\\node_modules\\' -and
        $_.FullName -notmatch '\\.git\\' -and
        $_.Name -notmatch 'app_backup_'
    }

$changed = @()
foreach ($file in $targets) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    if ($content -notmatch $pattern) { continue }

    $fixed = Repair-MojibakeText -Text $content
    if ($fixed -ne $content) {
        $changed += $file.FullName
        if (-not $WhatIf) {
            [System.IO.File]::WriteAllText($file.FullName, $fixed, $utf8NoBom)
        }
    }
}

if ($WhatIf) {
    Write-Host "Arquivos que seriam corrigidos: $($changed.Count)"
} else {
    Write-Host "Arquivos corrigidos: $($changed.Count)"
}
$changed | ForEach-Object { Write-Host $_ }
