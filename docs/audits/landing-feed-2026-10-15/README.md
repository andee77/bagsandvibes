# After-sign-in = the Feed: deployed 2026-10-15 (commit 82da44d)

- **Change:** after accepting the Master Traveler Agreement, members go to the Feed; the trip-guest fallback is the Feed.
- **Deploy:** checksums matched (0027f94af384), php -l clean, SiteGround and Cloudflare purged, pushed.
- **Live checks:**
  - test_my_passport.sh: 29 checks, 0 failures.
  - The re-accept page redirects to https://bagsandvibes.com/member-feed/ after accepting.
  - Ultimate Member: full member and trip guest → redirect_url /member-feed/; admin → redirect_admin.
