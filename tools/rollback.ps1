param(
  [switch] $Latest,
  [string] $ToBackupDir = '',
  [string] $Message = ''
)

$ErrorActionPreference = 'Stop'

function Write-Info($msg) { Write-Host $msg -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host $msg -ForegroundColor Green }
function Write-Warn($msg) { Write-Host $msg -ForegroundColor Yellow }

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$SandboxRoot = Resolve-Path (Join-Path $ScriptDir '..') | Select-Object -ExpandProperty Path

$ProdRoot = 'C:\xampp\htdocs\controlepcp'
$BackupRoot = 'C:\xampp\backups\controlepcp\releases'
$ReleaseJson = Join-Path $SandboxRoot '.tmp\release-center.json'

if (!(Test-Path $ProdRoot)) { throw "Pasta de produção não encontrada: $ProdRoot" }
if (!(Test-Path $BackupRoot)) { throw "Pasta de backups não encontrada: $BackupRoot" }

function Resolve-TargetBackupDir {
  param([switch] $Latest, [string] $ToBackupDir, [string] $BackupRoot)

  if ($ToBackupDir -and (Test-Path $ToBackupDir)) {
    return (Resolve-Path $ToBackupDir).Path
  }

  if ($Latest) {
    $dirs = Get-ChildItem -Path $BackupRoot -Directory | Sort-Object Name
    if ($dirs.Count -eq 0) { throw "Nenhum backup encontrado em $BackupRoot" }
    return $dirs[$dirs.Count - 1].FullName
  }

  throw "Informe -Latest ou -ToBackupDir (caminho completo)."
}

$targetBackupDir = Resolve-TargetBackupDir -Latest:$Latest -ToBackupDir $ToBackupDir -BackupRoot $BackupRoot

Write-Info "Rollback: restaurando produção a partir de:"
Write-Host "  $targetBackupDir"

Write-Info "Restaurando arquivos (robocopy /MIR)"
& robocopy $targetBackupDir $ProdRoot /MIR /R:1 /W:1 /NFL /NDL /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Falha no rollback via robocopy. ExitCode=$LASTEXITCODE" }
Write-Ok "Rollback OK"

# Registrar evento no release-center.json (sandbox)
if (Test-Path $ReleaseJson) {
  $raw = Get-Content $ReleaseJson -Raw -ErrorAction SilentlyContinue
  $state = $null
  try { $state = $raw | ConvertFrom-Json -ErrorAction Stop } catch { $state = $null }
  if ($null -eq $state) {
    $state = [pscustomobject]@{ schema = 1; env = 'sandbox'; items = @(); releases = @(); updated_at = (Get-Date).ToString('o') }
  }

  $releases = @()
  if ($state.releases) { $releases = @($state.releases) }

  $ts = Get-Date -Format 'yyyyMMdd_HHmmss'
  $now = (Get-Date).ToString('o')
  $who = $env:USERNAME

  $releases += [pscustomobject]@{
    id = "RB_$ts"
    action = 'rollback'
    restored_at = $now
    restored_by = $who
    restore_from = $targetBackupDir
    message = $Message
  }

  $state.releases = $releases
  $state.updated_at = $now

  ($state | ConvertTo-Json -Depth 12) | Set-Content -Encoding UTF8 $ReleaseJson
  Write-Ok "Rollback registrado na Central: RB_$ts"
} else {
  Write-Warn "release-center.json não encontrado; rollback não foi registrado na Central."
}

Write-Host ""
Write-Ok "Concluído."
Write-Host "Produção: $ProdRoot"
