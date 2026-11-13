# Smallest routing metric; prefer 192.168.x (Wi‑Fi/LAN) over 10.x (often VPN).
$ip = Get-NetIPAddress -AddressFamily IPv4 |
  Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
  Sort-Object @{ Expression = {
      if ($_.IPAddress -like '192.168.*') { 0 }
      elseif ($_.IPAddress -like '10.*') { 2 }
      else { 1 }
    } }, InterfaceMetric |
  Select-Object -First 1 -ExpandProperty IPAddress
if ($ip) { Write-Output $ip }
