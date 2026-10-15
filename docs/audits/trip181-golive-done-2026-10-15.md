# Trip 181 (Annual Family and Friends): LIVE on the new design since 2026-10-15

**Owner said GO on 2026-10-15** after reviewing the final screenshots in `docs/audits/final-golive-2026-10-15/`.

## What was switched on (~18:05 UTC)
1. **Trip 181's "Use new landing design" box ticked.** Saved through its real box; only `cbv_lp_use_new_design` changed (0 → 1). Snapshots: `~/logs/old/trip181-{before,after}-golive-20261015-180458.json`.
2. **Master switch on:** `wp option update cbv_lp_redesign_live 1`.
3. **Caches purged:** SiteGround (`wp sg purge`) and Cloudflare (`"success":true`).

**Before switching on:** only trips 181 and 320 had the per-trip setting, and both were 0, so the master switch affects trip 181 alone. Trip 320 (Helmets) still shows its current design.

**Site status text afterwards:**
- 181: "Public visitors see the NEW design (master switch on and this trip is checked)."
- 320: "Public visitors see the CURRENT design."

## Live checks (real switches, server-side renders)
- **Signed out, and signed in but not on the trip:** the new design, Gates 14–23 in the mockup's order:
  - the 8 cabins from the Oct 15 invoices, with the "Request a quote" note (Essential and Base on request);
  - the five price tickets, including the fee;
  - the on-request and suite cards;
  - the hero poster as the only share image;
  - no empty band above the footer;
  - no InteleTravel, no "CBGV books your cabin", no catamaran.
- **Member who has accepted the trip agreement:** the members' trip page with What's included, the Pack list, the Countdown and Travel documents.
- **Member who hasn't accepted:** the agreement screen first, as designed.
- **Helmets:** unchanged (current design).
- **Phone:** the final screenshots come from the same code and data, at 375px.
- **Not checked in a real browser:** the browser pane got SiteGround's robot check, and that check is never waited out or solved. The owner should open https://bagsandvibes.com/trip/annual-family-and-friends/ in a private window and on a phone, and should see the boarding-pass hero and "Gate 14 · The trip".

## UNDO (instant; nothing is deleted)
- **All trips back to the old design:**
  ```
  wp option update cbv_lp_redesign_live 0
  ```
- **Only trip 181 back:** untick "Use new landing design" in trip 181's **New Landing Design** box (WordPress admin > Trips > Annual Family and Friends, right-hand column), or run:
  ```
  wp post meta update 181 cbv_lp_use_new_design 0
  ```
- **Afterwards, purge both caches** so visitors see the change right away:
  - `wp sg purge`
  - Cloudflare: Caching > Purge Everything in the Cloudflare dashboard, or the API purge in `reference_cloudflare_deploy_infra`.

The commands run on the server from the site folder (`~/www/bagsandvibes.com/public_html`) over SSH.

## Done after go-live (same day)
The Helmets → Annual Family and Friends move and its emails: see `helmets-move-final-2026-10-15.md`.
