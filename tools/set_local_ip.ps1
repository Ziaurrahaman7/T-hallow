# Writes data/local.json + shared/net_config.local.h from this PC's IPv4 (skip loopback/APIPA).
$ip = & (Join-Path $PSScriptRoot 'get_lan_ip.ps1') | Select-Object -First 1
if (-not $ip) {
  $ip = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    Sort-Object InterfaceMetric |
    Select-Object -First 1 -ExpandProperty IPAddress
}
if (-not $ip) {
  Write-Host "No IPv4 found. Set data/local.json manually."
  exit 1
}
$root = Split-Path $PSScriptRoot -Parent
$base = "http://$ip/T-hallow"
$live = "https://hallow.mmgbd.com/api/upload.php"
$localJson = Join-Path $root "data\local.json"
$netLocal = Join-Path $root "shared\net_config.local.h"

$ssid = "Redmi 13C"
$pass = "1234567a"
if (Test-Path $netLocal) {
  $old = Get-Content -Path $netLocal -Raw -ErrorAction SilentlyContinue
  if ($old -match '#define\s+NET_SSID\s+"([^"]*)"') { $ssid = $Matches[1] }
  if ($old -match '#define\s+NET_PASS\s+"([^"]*)"') { $pass = $Matches[1] }
}

@{
  lan_base = $base
  note     = "Auto $(Get-Date -Format o)"
} | ConvertTo-Json | Set-Content -Path $localJson -Encoding UTF8
@"
#pragma once
// Laragon: PC + board same Wi-Fi. IP বদলালে এই স্ক্রিপ্ট চালান, তারপর AP/STA flash।
#define NET_URL "$base/api/upload.php"
#define NET_URL_ALT "$live"
#define NET_SSID "$ssid"
#define NET_PASS "$pass"
"@ | Set-Content -Path $netLocal -Encoding UTF8
@{
  uploads = @("$base/api/upload.php", $live)
} | ConvertTo-Json | Set-Content -Path (Join-Path $root "data\servers.json") -Encoding UTF8
Write-Host "Local base: $base"
Write-Host "Wi-Fi default in flash: $ssid"
Write-Host "Re-flash AP/STA after net_config.local.h changes (or match hotspot name/pass + update Upload URL on dashboard)."
