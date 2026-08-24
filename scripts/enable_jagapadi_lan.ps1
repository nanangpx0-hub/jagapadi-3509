[CmdletBinding()]
param(
    [int] $Port = 80,
    [string] $RuleName = 'JAGAPADI LAN HTTP'
)

$ErrorActionPreference = 'Stop'

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
$isAdministrator = $principal.IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdministrator) {
    throw 'Jalankan PowerShell sebagai Administrator, lalu jalankan skrip ini kembali.'
}

$addresses = @(
    Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object {
            $_.AddressState -eq 'Preferred' -and
            $_.IPAddress -notlike '127.*' -and
            $_.PrefixOrigin -ne 'WellKnown'
        }
)

if ($addresses.Count -eq 0) {
    throw 'Tidak ditemukan alamat IPv4 LAN yang aktif.'
}

$existingRule = Get-NetFirewallRule -DisplayName $RuleName -ErrorAction SilentlyContinue
if ($null -eq $existingRule) {
    New-NetFirewallRule `
        -DisplayName $RuleName `
        -Description 'Akses HTTP JAGAPADI dari Wi-Fi/LAN lokal' `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $Port `
        -RemoteAddress LocalSubnet `
        -Profile Any | Out-Null
} else {
    Set-NetFirewallRule `
        -DisplayName $RuleName `
        -Enabled True `
        -Action Allow `
        -Direction Inbound `
        -Profile Any | Out-Null
    Set-NetFirewallAddressFilter `
        -AssociatedNetFirewallRule $existingRule `
        -RemoteAddress LocalSubnet | Out-Null
    Set-NetFirewallPortFilter `
        -AssociatedNetFirewallRule $existingRule `
        -Protocol TCP `
        -LocalPort $Port | Out-Null
}

$listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($null -eq $listener) {
    throw "Firewall sudah dikonfigurasi, tetapi tidak ada server yang mendengarkan port $Port. Jalankan Apache di Laragon."
}

Write-Host 'Akses LAN JAGAPADI telah diaktifkan.' -ForegroundColor Green
foreach ($address in $addresses) {
    $url = "http://$($address.IPAddress)/jagapadi-3509/"
    Write-Host "URL LAN: $url" -ForegroundColor Cyan
}
Write-Host 'Pastikan perangkat klien berada pada subnet yang sama dan AP/Client Isolation dinonaktifkan.'
