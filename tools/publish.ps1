param(
  [switch] $AllApproved,
  [int[]] $ItemIds = @(),
  [string] $Message = '',
  [switch] $SkipChecklist
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
if (!(Test-Path $SandboxRoot)) { throw "Pasta do sandbox não encontrada: $SandboxRoot" }

if (!(Test-Path $BackupRoot)) { New-Item -ItemType Directory -Path $BackupRoot | Out-Null }

function Read-ReleaseState([string] $Path) {
  if (!(Test-Path $Path)) { return $null }
  try {
    $raw = Get-Content $Path -Raw -ErrorAction Stop
    return $raw | ConvertFrom-Json -ErrorAction Stop
  } catch {
    return $null
  }
}

# Enforce checklist antes de publicar
if (-not $SkipChecklist) {
  $statePre = Read-ReleaseState $ReleaseJson
  $check = $statePre.publish_checklist
  $required = @('login_ok','import_excel_ok','calcular_ok','historico_ok','impressao_ok')
  $missing = @()
  foreach ($k in $required) {
    if (-not ($check.PSObject.Properties.Name -contains $k) -or ($check.$k -ne $true)) {
      $missing += $k
    }
  }
  if ($missing.Count -gt 0) {
    throw ("Checklist pendente. Marque na Central antes de publicar. Faltando: " + ($missing -join ', ') + ". (Use -SkipChecklist se for emergencial)")
  }
}

$ts = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupDir = Join-Path $BackupRoot $ts
New-Item -ItemType Directory -Path $backupDir | Out-Null

Write-Info "Backup da produção -> $backupDir"
& robocopy $ProdRoot $backupDir /MIR /R:1 /W:1 /NFL /NDL /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Falha no backup via robocopy. ExitCode=$LASTEXITCODE" }
Write-Ok "Backup OK"

Write-Info "Publicando arquivos do sandbox -> produção"
$excludeDirs = @('.tmp', '.git', '.idea', '.vscode', 'tools')
$excludeFiles = @('Thumbs.db', 'desktop.ini')

$robocopyArgs = @(
  $SandboxRoot,
  $ProdRoot,
  '/E',
  '/COPY:DAT',
  '/DCOPY:T',
  '/R:1',
  '/W:1',
  '/NFL',
  '/NDL',
  '/NP',
  '/XF'
) + $excludeFiles + @('/XD') + $excludeDirs

& robocopy @robocopyArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Falha no deploy via robocopy. ExitCode=$LASTEXITCODE" }
Write-Ok "Deploy OK"

if (!(Test-Path $ReleaseJson)) {
  Write-Warn "release-center.json não encontrado; criando um novo."
  $initial = @{
    schema = 1
    env = 'sandbox'
    items = @()
    releases = @()
    updated_at = (Get-Date).ToString('o')
  } | ConvertTo-Json -Depth 10
  $initial | Set-Content -Encoding UTF8 $ReleaseJson
}

Write-Info "Atualizando log de publicação: $ReleaseJson"
$raw = Get-Content $ReleaseJson -Raw -ErrorAction SilentlyContinue
$state = $null
try { $state = $raw | ConvertFrom-Json -ErrorAction Stop } catch { $state = $null }
if ($null -eq $state) {
  $state = [pscustomobject]@{ schema = 1; env = 'sandbox'; items = @(); releases = @(); updated_at = (Get-Date).ToString('o') }
}

$items = @()
if ($state.items) { $items = @($state.items) }

$selected = @()
if ($AllApproved) {
  $selected = $items | Where-Object { (($_.status | ForEach-Object { "$_".ToLower() }) -eq 'approved') }
} elseif ($ItemIds.Count -gt 0) {
  $idSet = @{}
  foreach ($id in $ItemIds) { $idSet[$id] = $true }
  $selected = $items | Where-Object { $idSet[[int]$_.id] }
} else {
  throw "Informe -AllApproved ou -ItemIds."
}

if ($selected.Count -eq 0) {
  throw "Nenhum item selecionado para publicar."
}

$who = $env:USERNAME
$now = (Get-Date).ToString('o')
$selectedIds = @($selected | ForEach-Object { [int]$_.id })

# Marca items como published
foreach ($it in $items) {
  if ($selectedIds -contains [int]$it.id) {
    $it.status = 'published'
    if ($it.PSObject.Properties.Name -contains 'published_at') { $it.published_at = $now } else { $it | Add-Member -NotePropertyName 'published_at' -NotePropertyValue $now -Force }
    if ($it.PSObject.Properties.Name -contains 'published_by') { $it.published_by = $who } else { $it | Add-Member -NotePropertyName 'published_by' -NotePropertyValue $who -Force }
    if ($it.PSObject.Properties.Name -contains 'updated_at') { $it.updated_at = $now } else { $it | Add-Member -NotePropertyName 'updated_at' -NotePropertyValue $now -Force }
  }
}

$releases = @()
if ($state.releases) { $releases = @($state.releases) }
$releases += [pscustomobject]@{
  id = $ts
  action = 'publish'
  published_at = $now
  published_by = $who
  items = $selectedIds
  message = $Message
  backup_dir = $backupDir
}

$state.items = $items
$state.releases = $releases
$state.updated_at = $now

($state | ConvertTo-Json -Depth 12) | Set-Content -Encoding UTF8 $ReleaseJson
Write-Ok "Release registrada: $ts"

Write-Host ""
Write-Ok "Concluído."
Write-Host "Sandbox: $SandboxRoot"
Write-Host "Produção: $ProdRoot"
Write-Host "Backup: $backupDir"
