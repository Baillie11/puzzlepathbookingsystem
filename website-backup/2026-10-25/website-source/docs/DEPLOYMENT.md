# Orange Host deployment
## Verified discovery — 18 October 2026
Evidence: owner-provided output from cPanel Terminal. No remote files have been changed by this project.
- Management interface: cPanel; browser Terminal works.
- cPanel/SSH username: `ozbizfin`.
- Account home: `/home/ozbizfin`.
- Domain: `puzzlepath.com.au`, configured as an addon domain.
- Verified domain document root: `/home/ozbizfin/puzzlepath.com.au`.
- WordPress is installed in this root: `index.php` and `wp-admin/` exist.
- Proposed production directory: `/home/ozbizfin/puzzlepath.com.au/brochure/`.
- Neither a file, directory nor symbolic link named `brochure` existed at inspection time. Recheck immediately before any upload.
- Root `.htaccess` excludes existing files and directories (`!-f`, `!-d`) before forwarding requests to WordPress `index.php`. This supports an independent real directory; index-file selection and inherited server settings still require preview verification.
- Git is available at `/usr/local/cpanel/3rdparty/lib/path-bin/git`; `zip` and `unzip` are available at `/bin/zip` and `/bin/unzip`.
- Domain IP reported by cPanel: `209.172.2.80`.
- External SSH attempts to this IP on port 22 failed before authentication. Correct external hostname, port, network access and private-key authentication remain unverified.
- Presence of `.htaccess` does not establish whether the serving stack is Apache, LiteSpeed or another configuration. Confirm with the host.
## Safety boundaries
Do not change the root `.htaccess`, WordPress installation, booking plugin, quest app, database, PHP settings, DNS or Stripe settings.
The deployment key is held locally outside this repository. Never package, print or commit private keys, credentials or hosting-account configuration files.
There is no automatic deployment workflow. Any hosting upload, including a preview, requires explicit approval.
## Before preview deployment
1. Finish the local build, verify coupon eligibility with the booking-system owner and run the documented tests.
2. Obtain approval for a named, isolated preview location. A suggested name is `brochure-preview`, but it has not been inspected, reserved, created or approved.
3. Recheck the selected destination for files, directories and symlinks. Do not overwrite any existing destination. If occupied, agree a backup and replacement strategy first.
4. Confirm the web-server stack, `DirectoryIndex` behaviour, supported headers, HTTPS and static caching options. Do not infer these from the control-panel theme.
5. Build a public-files-only deployment package from `dist/`. Do not upload source tooling, dependencies, documentation, secrets or `.git`.
6. Use verified SSH/SFTP access, or have the owner upload through cPanel File Manager. Confirm the SSH host fingerprint through a trusted host-provided source; do not bypass host-key checking.
## Preview verification
- Confirm `index.html` is served directly and no WordPress bootstrap or checkout iframe is involved.
- Verify all relative asset paths, HTTPS, image dimensions, console output, keyboard interaction and six required viewport widths.
- Keep preview `noindex` directives. These do not provide access control; use host-supported access protection if the preview must be private.
- Verify event preselection, manual coupon validation and the displayed group total without creating bookings or running payment transactions.
- Measure Lighthouse against the authorised HTTPS preview. Local scores are not deployed results.
- Confirm a landing-directory-only policy for caching and security headers. Revalidate HTML promptly; use long immutable caching only for content-hashed assets. Do not enable domain-wide HSTS or modify the root configuration.
## Production publication
Obtain separate explicit approval for `/brochure/` after preview review.
Recheck `/home/ozbizfin/puzzlepath.com.au/brochure/` immediately before publication.
Publish only the reviewed static release and verify `https://puzzlepath.com.au/brochure/`.
The current campaign strategy is `noindex, follow`; keep or change this deliberately after the owner reviews the indexing strategy.
Do not remove preview protection or change WordPress routes as a side effect.
## Rollback
Before an update, archive the existing landing-page directory outside the public document root and record its release identifier. Scope the backup to this landing page, not WordPress.
If the new page fails, restore that known-good landing release only, then recheck assets and booking links.
For a first publication with no earlier release, agree a rollback destination and QR-code fallback before launch; removing the directory would otherwise return requests to WordPress and may produce a 404.
No production release, backup or rollback has been performed yet.
