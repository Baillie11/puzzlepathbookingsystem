$ErrorActionPreference = 'Stop'
$phoneAddress = (Get-NetIPConfiguration -InterfaceAlias 'Wi-Fi').IPv4Address.IPAddress
if (-not $phoneAddress) { throw 'No Wi-Fi IPv4 address is available.' }
$env:PP_ENV = 'local'
$env:PP_ANALYTICS = '0'
Set-Location -LiteralPath $PSScriptRoot
Write-Host "Phone preview: http://${phoneAddress}:4181/?campaign=dl-brochure"
php -S "${phoneAddress}:4181" -t public
