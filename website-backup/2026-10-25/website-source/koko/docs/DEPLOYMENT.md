# OrangeHost deployment and rollback — not executed

Prior project evidence identifies cPanel account home `/home/ozbizfin` and domain root `/home/ozbizfin/puzzlepath.com.au`. `/brochure/` was previously proposed and unoccupied on 18 October; recheck it, including symlinks, before deployment. Do not change root WordPress routing or DNS. This PHP package supersedes the older static landing deployment procedure.

## Package

Run `./package.ps1` from this folder. It builds a timestamped zip with separate `public/`, `private/`, `integration/` and `docs/` directories and SHA-256 checksum. The deployment package excludes test fixtures, the simulated checkout, logs, dependencies, original oversized images and development scripts. Never upload the whole workspace to a public directory.

## Staging checklist (after approval)

1. Approve a specific isolated staging route and private application directory. Recheck destinations and back up anything to be replaced. Verify SSH host identity if using SSH; use cPanel File Manager if external SSH remains unavailable. No private credentials belong in the archive.
2. Confirm PHP 8.2+, cURL with trusted CA certificates and outbound HTTPS to puzzlepath.com.au. PDO SQLite is required only when a real conversion adapter is implemented. Confirm Apache/LiteSpeed compatibility with the scoped `.htaccess`.
3. Upload `private/` contents outside the domain document root, e.g. `/home/ozbizfin/puzzlepath-landing-private`. Give PHP read access, with write access only to its private `var/` directory. Use owner-only configuration permissions and normal public file permissions supported by the host.
4. Upload only `public/` contents into the approved landing directory. Configure the environment from `config/environment.example` through the host-supported server/PHP-FPM mechanism. If environment configuration is unavailable, insert the absolute private root in the uploaded entrypoint through a reviewed deployment-specific change; do not relocate private source into public web space.
5. Keep `PP_ENV=production`, `PP_BOOKING_VERIFIED=0`, `PP_PROMOTION_VERIFIED=0`, `PP_ANALYTICS=0`. The archive intentionally contains no local checkout fixture. Do not advertise the staging offer.
6. With separate integration approval, install `integration/puzzlepath-landing-catalogue.php` as a small WordPress plugin, then set `PP_CATALOGUE_URL` to the implemented bridge endpoint. Verify its IDs and prices against the existing booking form. It only reads existing event rows.
7. Reconcile the deployed existing booking plugin with the local voucher helper and enqueue code. Do not overwrite the entire live booking plugin from this package. Validate the actual approved brochure coupon and automatic application in staging. Test failure, expiry, race conditions on quest change, quantity changes and old saved vouchers. Use the existing booking service and Stripe test credentials; never test with real customer payments.
8. Test mobile/desktop, subdirectory asset URLs, CSP, HTTPS, readable errors, API outage/cache behaviour, and that `/src`, `/config`, `/var`, fixtures and logs cannot be fetched publicly. `noindex` is not access control; protect staging using cPanel access controls.
9. After full booking verification, set `PP_BOOKING_VERIFIED=1`. Set `PP_PROMOTION_VERIFIED=1` only after the actual promotional checkout has passed. Keep both off if anything is uncertain.

## Publication and brochure URL

After local review, explicit deployment approval and the staging checks above, publish the reviewed release to the separately approved route. The proposed URL, conditional on `/brochure/` approval, is:

`https://puzzlepath.com.au/brochure/?campaign=dl-brochure`

The trusted campaign already sets source to QR; a visitor-supplied source is unnecessary. Keep this public campaign slug stable when changing backend configuration. Do not generate a print-ready QR until this route and the end-to-end booking flow are approved. The implementation retains `noindex, nofollow` pending an indexing decision.

## Rollback

Before each deployment, archive the previous landing public files, private application/config and any separately changed integration file outside public web space. Record checksum and environment settings. If a release fails, close the verification flags and restore those exact landing files/settings. Restore the companion plugin only if it was changed as part of this release. Do not roll back unrelated bookings, customer data, Stripe state or WordPress database tables.

For first publication, agree a temporary safe unavailable page at the same QR route. Do not simply delete a printed QR destination. Recheck the rollback page and all public asset paths after restoration. No deployment, remote backup, QR printing or rollback has been performed by this task.
