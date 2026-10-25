$env:PP_ENV = 'local'
$env:PP_ANALYTICS = '0'
Set-Location -LiteralPath $PSScriptRoot
php -S 127.0.0.1:4180 -t public
