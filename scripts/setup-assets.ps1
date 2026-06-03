# Memastikan aset wajib AntriV2 terpasang (vendor + audio).
# Jalankan dari root project: powershell -ExecutionPolicy Bypass -File scripts/setup-assets.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

function Ensure-Dir([string]$path) {
    if (-not (Test-Path $path)) {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

Ensure-Dir "$root\assets\vendor\bootstrap"
Ensure-Dir "$root\assets\vendor\lucide"
Ensure-Dir "$root\audio\custom"
Ensure-Dir "$root\audio\default"
Ensure-Dir "$root\audio\ardi"
Ensure-Dir "$root\audio\gadis"
Ensure-Dir "$root\assets\img\backgrounds"

$vendorBootstrap = "$root\assets\vendor\bootstrap\bootstrap.min.css"
$vendorLucide = "$root\assets\vendor\lucide\lucide.min.js"

if (-not (Test-Path $vendorBootstrap) -or (Get-Item $vendorBootstrap).Length -lt 100000) {
    Write-Host "Mengunduh Bootstrap CSS..."
    Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" -OutFile $vendorBootstrap -UseBasicParsing
}

if (-not (Test-Path $vendorLucide) -or (Get-Item $vendorLucide).Length -lt 100000) {
    Write-Host "Mengunduh Lucide JS..."
    Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js" -OutFile $vendorLucide -UseBasicParsing
}

$introBell = "$root\audio\default\in.wav"
if (-not (Test-Path $introBell) -or (Get-Item $introBell).Length -lt 10000) {
    Write-Host "Mengunduh in.wav (bell intro) ke audio/default..."
    Invoke-WebRequest -Uri "https://raw.githubusercontent.com/herudi/simple-antrian/master/public/audio/in.wav" -OutFile $introBell -UseBasicParsing
}

$requiredMp3 = @(
    "0.MP3", "1.MP3", "2.MP3", "3.MP3", "4.MP3", "5.MP3", "6.MP3", "7.MP3", "8.MP3", "9.MP3",
    "sepuluh.MP3", "sebelas.MP3", "belas.MP3", "puluh.MP3", "seratus.MP3", "ratus.MP3", "ribu.MP3",
    "nomor-urut.MP3", "loket.MP3"
)

$missing = @()
foreach ($name in $requiredMp3) {
    $path = Join-Path "$root\audio\default" $name
    if (-not (Test-Path $path) -or (Get-Item $path).Length -lt 500) {
        $missing += $name
    }
}

if ($missing.Count -gt 0) {
    Write-Warning "File audio/default belum lengkap: $($missing -join ', ')"
    Write-Warning "Salin isi audio/default/ dari backup, atau jalankan migrasi dari file lama di audio/."
    exit 1
}

foreach ($pack in @('ardi', 'gadis')) {
    $packDir = Join-Path "$root\audio" $pack
    $packMissing = @()
    foreach ($name in $requiredMp3) {
        $path = Join-Path $packDir $name
        if (-not (Test-Path $path)) {
            $packMissing += $name
        }
    }
    if ($packMissing.Count -gt 0) {
        Write-Host "Info: audio/$pack/ belum lengkap ($($packMissing.Count) file) - opsi admin nonaktif sampai rekaman selesai."
    }
}

Write-Host "OK: vendor dan audio wajib sudah terpasang di $root"
