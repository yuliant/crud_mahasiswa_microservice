$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskRegistry = Join-Path $taskRoot '.runtime/servers.json'
if (!(Test-Path -LiteralPath $taskRegistry)) { Write-Host 'Tidak ada server yang dicatat.'; return }
$taskServers = Get-Content -LiteralPath $taskRegistry -Raw | ConvertFrom-Json
foreach ($taskServer in $taskServers) {
    $taskProcess = Get-Process -Id $taskServer.Id -ErrorAction SilentlyContinue
    if (!$taskProcess) { continue }
    # Do not stop an unrelated process if Windows has reused a PID.
    $taskStarted = $taskProcess.StartTime.ToUniversalTime().Ticks.ToString()
    $taskCommand = (Get-CimInstance Win32_Process -Filter "ProcessId = $($taskServer.Id)").CommandLine
    if ($taskProcess.ProcessName -eq 'php' -and $taskStarted -eq [string]$taskServer.StartedTicks -and $taskCommand -and $taskCommand.Contains($taskRoot)) {
        Stop-Process -Id $taskServer.Id
        Write-Host "Server $($taskServer.Name) dihentikan."
    } else {
        Write-Warning "PID $($taskServer.Id) bukan proses server yang semula dicatat; dilewati."
    }
}
Remove-Item -LiteralPath $taskRegistry
