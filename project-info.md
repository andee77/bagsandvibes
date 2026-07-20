# Project Info — Checked Bags & Good Vibes

## Overview
- **Site name:** Checked Bags & Good Vibes
- **Domain:** bagsandvibes.com
- **Brand owner:** JourneyWell Global LLC
- **Purpose:** Group travel and vacation planning platform
- **Platform:** WordPress
- **Hosting:** SiteGround
- **Local project folder:** `C:\Users\Andre\projects\bagsandvibes`

## Brand Colors
| Name | Role | Hex |
|---|---|---|
| Ink | Primary dark | `#16232B` |
| Horizon blue | — | `#1B3A4B` |
| Coral | Accent | `#FF6B4A` |
| Gold | Accent | `#E8A94E` |
| Sand cream | Light / text-on-dark | `#FBF3E7` |
| Palm teal | Secondary accent | `#2E7D6E` |

## Fonts
- **Fraunces** — display/headings (italic, warm vintage-postcard feel)
- **Work Sans** — body copy, nav
- **Space Mono** — labels, tags, small utility text

## Homepage — Scrollytelling Landing
The homepage uses a custom page template called **"Scrollytelling Landing"**, registered via an mu-plugin rather than a theme template:

- Registration: `wp-content/mu-plugins/checkedbags-landing.php`
- Template: `wp-content/mu-plugins/checkedbags-landing/template-scrollytelling.php`
- This template **bypasses Kadence's header/footer** for that page.
- Assets: `styles.css` and `app.js` live in `wp-content/uploads/checkedbags/`
- It's a **GSAP ScrollTrigger**-driven scrollytelling sequence.

**Important:** Do not refactor this into standard Kadence blocks — the custom template and bypass of Kadence's header/footer are intentional.

## Live Server Deployment (SSH)

SSH connection details:
  Host: gcam1028.siteground.biz
  Port: 18765
  User: u2922-bn1ak4vn2swx

Remote path to wp-content:
  /home/u2922-bn1ak4vn2swx/www/bagsandvibes.com/public_html/wp-content/

This maps to our local folders like this:
  local: mu-plugins/...              -> remote: wp-content/mu-plugins/...
  local: uploads/checkedbags/...     -> remote: wp-content/uploads/checkedbags/...

From now on, whenever a file in mu-plugins/ or uploads/ is changed and
committed, also deploy it to the live server using scp, e.g.:

  scp -P 18765 uploads/checkedbags/css/styles.css u2922-bn1ak4vn2swx@gcam1028.siteground.biz:/home/u2922-bn1ak4vn2swx/www/bagsandvibes.com/public_html/wp-content/uploads/checkedbags/css/styles.css

Or for a whole folder, use rsync, e.g.:

  rsync -avz -e "ssh -p 18765" uploads/checkedbags/ u2922-bn1ak4vn2swx@gcam1028.siteground.biz:/home/u2922-bn1ak4vn2swx/www/bagsandvibes.com/public_html/wp-content/uploads/checkedbags/

After deploying, verify by SSHing in and checking the file, e.g.:
  ssh -p 18765 u2922-bn1ak4vn2swx@gcam1028.siteground.biz "cat /home/u2922-bn1ak4vn2swx/www/bagsandvibes.com/public_html/wp-content/uploads/checkedbags/css/styles.css | grep -c important"

This replaces the manual SiteGround File Manager upload step entirely for
these two folders — local, GitHub, and the live server should now all be
kept in sync in one workflow instead of three separate manual ones.

## Cache Purging (SiteGround)

  wp sg purge
  (run via SSH from the site's public_html directory — see SSH connection
  details above. Clears static/dynamic/memcached/opcache. File cache may
  show a "not enabled on this plan" warning — that's expected, not an error.)

## Cache Purging (Cloudflare)

After every deploy to the live server (mu-plugins/ or uploads/ changes),
also purge Cloudflare's cache so the change is actually visible — the
origin file being correct does NOT mean visitors see it, since Cloudflare
caches independently of both git and the SiteGround origin.

Credentials are stored in ~/.cloudflare_credentials (not in this repo, not
in chat). To purge, source that file and call the Cloudflare API:

  source ~/.cloudflare_credentials
  curl -X POST "https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/purge_cache" \
    -H "Authorization: Bearer $CF_API_TOKEN" \
    -H "Content-Type: application/json" \
    --data '{"purge_everything":true}'

On this Windows machine, curl over schannel may fail with
CRYPT_E_NO_REVOCATION_CHECK (exit code 35) because it can't reach a
revocation server — add --ssl-no-revoke to the curl call to work around it.
This is a local TLS quirk, not a Cloudflare or credentials problem.

A successful response includes "success":true in the JSON. Add this purge
step to the standard deploy workflow: after scp/rsync to the live server,
purge SiteGround, then purge Cloudflare, then tell the user to hard-refresh
and check.
