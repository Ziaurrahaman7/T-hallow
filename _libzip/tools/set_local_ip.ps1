# Writes data/local.json + shared/net_config.local.h from this PC's IPv4 (skip loopback/APIPA).
$ip = Get-NetIPAddress -AddressFamily IPv4 |
  Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
  Sort-Object InterfaceMetric |
  Select-Object -First 1 -ExpandProperty IPAddress
if (-not $ip) {
  Write-Host "No IPv4 found. Set data/local.json manually."
  exit 1
}
$root = Split-Path $PSScriptRoot -Parent
$base = "http://$ip/T-hallow"
$localJson = Join-Path $root "data\local.json"
$netLocal = Join-Path $root "shared\net_config.local.h"
@{
  lan_base = $base
  note     = "Auto $(Get-Date -Format o)"
} | ConvertTo-Json | Set-Content -Path $localJson -Encoding UTF8
@"
#pragma once
#define NET_URL "$base/api/upload.php"
#define NET_URL_ALT ""
"@ | Set-Content -Path $netLocal -Encoding UTF8
@{
  uploads = @("$base/api/upload.php")
} | ConvertTo-Json | Set-Content -Path (Join-Path $root "data\servers.json") -Encoding UTF8
Write-Host "Local base: $base"
Write-Host "Re-flash AP/STA after net_config.local.h changes."
