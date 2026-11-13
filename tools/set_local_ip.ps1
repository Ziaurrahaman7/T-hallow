# PC লোকাল IP + 192.168.1.50 peer + cloud — net_config.local.h + servers.json
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
$baseThis = "http://$ip/T-hallow"
$basePeer = "http://192.168.1.50/T-hallow"
$live = "https://hallow.mmgbd.com/api/upload.php"
$localJson = Join-Path $root "data\local.json"
$netLocal = Join-Path $root "shared\net_config.local.h"

$ssid = "4 SIG BN"
$pass = "ict4sigbn"
if (Test-Path $netLocal) {
  $old = Get-Content -Path $netLocal -Raw -ErrorAction SilentlyContinue
  if ($old -match '#define\s+NET_SSID\s+"([^"]*)"') { $ssid = $Matches[1] }
  if ($old -match '#define\s+NET_PASS\s+"([^"]*)"') { $pass = $Matches[1] }
}

$urlThis = "$baseThis/api/upload.php"
$urlPeer = "$basePeer/api/upload.php"

@{
  lan_base = $baseThis
  browser  = $baseThis
  peer_pc  = $basePeer
  cloud    = $live
  note     = "Auto $(Get-Date -Format o); 3 upload targets"
} | ConvertTo-Json | Set-Content -Path $localJson -Encoding UTF8

@"
#pragma once
// 3 uploads: this PC ($ip) + peer 192.168.1.50 + cloud. IP বদলালে স্ক্রিপ্ট চালিয়ে AP/STA flash।
#define NET_URL "$urlThis"
#define NET_URL_ALT "$urlPeer"
#define NET_URL_ALT2 "$live"
#define NET_SSID "$ssid"
#define NET_PASS "$pass"
"@ | Set-Content -Path $netLocal -Encoding UTF8

@{
  uploads = @($urlThis, $urlPeer, $live)
} | ConvertTo-Json | Set-Content -Path (Join-Path $root "data\servers.json") -Encoding UTF8

Write-Host "This PC upload:  $urlThis"
Write-Host "Peer PC upload:   $urlPeer"
Write-Host "Cloud upload:     $live"
Write-Host "Wi-Fi in flash:   $ssid"
Write-Host "Re-flash AP/STA after this script."
