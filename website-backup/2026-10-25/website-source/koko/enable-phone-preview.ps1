# Run once from an Administrator PowerShell if Windows blocks the phone connection.
# Allow only this PHP preview on Wi-Fi, TCP 4181, from the local subnet.
$ErrorActionPreference = 'Stop'
$phoneRuleName = 'PuzzlePath-Koko-Phone-4181'
$phonePhpPath = (Get-Command php -ErrorAction Stop).Source
if (-not (Get-NetFirewallRule -Name $phoneRuleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -Name $phoneRuleName -DisplayName 'PuzzlePath Koko phone preview (local Wi-Fi)' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 4181 -RemoteAddress LocalSubnet -InterfaceAlias 'Wi-Fi' -Program $phonePhpPath -Profile Any | Out-Null
}
Write-Host 'Phone preview allowed on local Wi-Fi, port 4181.'
Write-Host "To remove later: Remove-NetFirewallRule -Name '$phoneRuleName'"
