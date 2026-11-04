$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskRuntime = Join-Path $taskRoot '.runtime'
$taskRegistry = Join-Path $taskRuntime 'servers.json'
if (Test-Path -LiteralPath $taskRegistry) {
    throw 'Catatan server masih ada. Jalankan .\scripts\stop.ps1 sebelum memulai ulang.'
}
foreach ($taskPort in @(8001, 8002, 8080)) {
    $taskProbe = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $taskPort)
    $taskProbe.Server.ExclusiveAddressUse = $true
    try {
        $taskProbe.Start()
    } catch {
        throw "Port $taskPort sudah dipakai. Hentikan server tersebut atau jalankan manual dengan port lain."
    } finally {
        $taskProbe.Stop()
    }
}
foreach ($taskService in @('prodi', 'mahasiswa')) {
    if (!(Test-Path -LiteralPath (Join-Path $taskRoot "services/$taskService/vendor/autoload.php"))) {
        throw "Jalankan composer install di services/$taskService terlebih dahulu."
    }
    if (!(Test-Path -LiteralPath (Join-Path $taskRoot "services/$taskService/.env"))) {
        throw 'Jalankan php scripts/setup.php terlebih dahulu.'
    }
}
New-Item -ItemType Directory -Path $taskRuntime -Force | Out-Null
$taskPhp = (Get-Command php -ErrorAction Stop).Source
$taskServers = @()
try {
    foreach ($taskService in @('prodi', 'mahasiswa')) {
        $taskPort = if ($taskService -eq 'prodi') { 8001 } else { 8002 }
        $taskDirectory = Join-Path $taskRoot "services/$taskService"
        $taskRouter = Join-Path $taskDirectory 'server.php'
        $taskArgs = '-S 127.0.0.1:' + $taskPort + ' -t "' + (Join-Path $taskDirectory 'public') + '" "' + $taskRouter + '"'
        $taskProcess = Start-Process -FilePath $taskPhp -ArgumentList $taskArgs -WorkingDirectory $taskDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime "$taskService.out.log") -RedirectStandardError (Join-Path $taskRuntime "$taskService.err.log")
        $taskServers += [PSCustomObject]@{ Name = $taskService; Id = $taskProcess.Id; StartedTicks = $taskProcess.StartTime.ToUniversalTime().Ticks.ToString() }
    }
    $taskFrontend = Join-Path $taskRoot 'frontend'
    $taskArgs = '-S 127.0.0.1:8080 -t "' + $taskFrontend + '"'
    $taskProcess = Start-Process -FilePath $taskPhp -ArgumentList $taskArgs -WorkingDirectory $taskFrontend -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime 'frontend.out.log') -RedirectStandardError (Join-Path $taskRuntime 'frontend.err.log')
    $taskServers += [PSCustomObject]@{ Name = 'frontend'; Id = $taskProcess.Id; StartedTicks = $taskProcess.StartTime.ToUniversalTime().Ticks.ToString() }
    $taskServers | ConvertTo-Json | Set-Content -LiteralPath $taskRegistry -Encoding utf8
    foreach ($taskAddress in @('http://127.0.0.1:8001/up', 'http://127.0.0.1:8002/up', 'http://127.0.0.1:8080')) {
        $taskReady = $false
        for ($taskAttempt = 0; $taskAttempt -lt 20; $taskAttempt++) {
            try {
                $taskResponse = Invoke-WebRequest -Uri $taskAddress -UseBasicParsing -TimeoutSec 2
                if ($taskResponse.StatusCode -eq 200) { $taskReady = $true; break }
            } catch { Start-Sleep -Milliseconds 250 }
        }
        if (!$taskReady) { throw "Server $taskAddress belum siap. Periksa log di .runtime." }
    }
    foreach ($taskServer in $taskServers) {
        $taskProcessCheck = Get-Process -Id $taskServer.Id -ErrorAction SilentlyContinue
        if (!$taskProcessCheck) { throw "Proses $($taskServer.Name) berhenti. Periksa log di .runtime." }
    }
    Write-Host 'Frontend      http://127.0.0.1:8080'
    Write-Host 'API Prodi     http://127.0.0.1:8001/docs/api'
    Write-Host 'API Mahasiswa http://127.0.0.1:8002/docs/api'
    Write-Host 'Hentikan: .\scripts\stop.ps1'
} catch {
    foreach ($taskServer in $taskServers) { Stop-Process -Id $taskServer.Id -ErrorAction SilentlyContinue }
    if (Test-Path -LiteralPath $taskRegistry) { Remove-Item -LiteralPath $taskRegistry }
    throw
}
