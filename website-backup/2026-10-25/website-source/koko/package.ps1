param([switch]$OrangeHost)
$ErrorActionPreference = 'Stop'
$releaseName = $(if ($OrangeHost) { 'koko-landingpage-' } else { 'koko-review-' }) + (Get-Date -Format 'yyyyMMdd-HHmmss')
$releaseRoot = Join-Path $PSScriptRoot ('release/' + $releaseName)
New-Item -ItemType Directory -Path "$releaseRoot/public/assets/images", "$releaseRoot/private/src/includes", "$releaseRoot/private/config", "$releaseRoot/private/var", "$releaseRoot/integration", "$releaseRoot/docs" -Force | Out-Null
Copy-Item -LiteralPath "$PSScriptRoot/public/index.php", "$PSScriptRoot/public/privacy.html", "$PSScriptRoot/public/.htaccess" -Destination "$releaseRoot/public"
Copy-Item -LiteralPath "$PSScriptRoot/public/assets/css", "$PSScriptRoot/public/assets/js", "$PSScriptRoot/public/assets/svg" -Destination "$releaseRoot/public/assets" -Recurse
Copy-Item -Path "$PSScriptRoot/public/assets/images/koko-*.jpg" -Destination "$releaseRoot/public/assets/images"
Copy-Item -LiteralPath "$PSScriptRoot/src/core.php", "$PSScriptRoot/src/page.php", "$PSScriptRoot/src/conversions.php" -Destination "$releaseRoot/private/src"
Copy-Item -LiteralPath "$PSScriptRoot/src/includes/island.php", "$PSScriptRoot/src/includes/trail.php" -Destination "$releaseRoot/private/src/includes"
Copy-Item -Path "$PSScriptRoot/config/*" -Destination "$releaseRoot/private/config"
Copy-Item -Path "$PSScriptRoot/integration/*" -Destination "$releaseRoot/integration"
Copy-Item -Path "$PSScriptRoot/docs/*" -Destination "$releaseRoot/docs"
if ($OrangeHost) {
    $hostingConfig = @'
<?php
// This release keeps unverified live booking disabled. Edit only after checkout verification.
putenv('PP_ENV=production');
putenv('PP_APP_ROOT=/home/ozbizfin/puzzlepath-landing-private');
putenv('PP_STORAGE=/home/ozbizfin/puzzlepath-landing-private/var');
putenv('PP_CATALOGUE_URL=https://puzzlepath.com.au/wp-json/puzzlepath/v1/hunts');
putenv('PP_BOOKING_VERIFIED=0');
putenv('PP_PROMOTION_VERIFIED=0');
putenv('PP_ANALYTICS=0');
'@
    [IO.File]::WriteAllText("$releaseRoot/private/config/hosting.php", $hostingConfig)
    $entrypoint = [IO.File]::ReadAllText("$releaseRoot/public/index.php")
    $entrypoint = $entrypoint.Replace('declare(strict_types=1);', "declare(strict_types=1);`nrequire '/home/ozbizfin/puzzlepath-landing-private/config/hosting.php';")
    [IO.File]::WriteAllText("$releaseRoot/public/index.php", $entrypoint)
}
Compress-Archive -Path "$releaseRoot/*" -DestinationPath "$releaseRoot.zip"
$releaseHash = Get-FileHash -LiteralPath "$releaseRoot.zip" -Algorithm SHA256
$releaseHash.Hash | Set-Content -LiteralPath "$releaseRoot.zip.sha256"
Write-Output $releaseHash
