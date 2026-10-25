# Approved destination: /landingpage/

On 22 October 2026 the owner approved deployment to `www.puzzlepath.com.au/landingpage`. This replaces the earlier proposed `/brochure/` route. The site currently redirects www to `https://puzzlepath.com.au`; retain that existing domain behaviour. Do not change DNS or global WordPress redirects.

Public destination: `/home/ozbizfin/puzzlepath.com.au/landingpage/`.
Private application: `/home/ozbizfin/puzzlepath-landing-private/`.
Public URL: `https://www.puzzlepath.com.au/landingpage/`.
Campaign URL: `https://www.puzzlepath.com.au/landingpage/?campaign=dl-brochure`.

`./package.ps1 -OrangeHost` produces a destination-specific archive. The entrypoint loads the private `config/hosting.php`, so no PHP-FPM environment changes are needed. Only the archive's `public/` contents go into the landingpage directory; `private/` contents go outside the domain root at the private application path. Keep docs and integration files outside public web space.

The release uses the existing public catalogue API and does not install or alter WordPress plugins. All currently public IDs matched that API at discovery; the companion full-catalogue adapter remains a future integration requirement. Both booking verification flags remain zero and the public page clearly states booking is temporarily unavailable. The simulated checkout and test catalogue are absent from the release. No real booking or payment is accepted by this page.

Before upload, confirm both destination paths with the hosting filesystem and check for existing directories or symlinks. A public 404 does not prove the filesystem is empty. Back up any existing release outside public web space before an update. Confirm PHP 8.2/cURL and that the private var directory is writable by PHP. Follow DEPLOYMENT.md for verification and rollback.

Resolved 24 October: deployment completed through the owner's signed-in cPanel session. See DEPLOYED-2026-10-24.md for paths, checksum, verification and remaining booking limitations. SSH was not needed.
