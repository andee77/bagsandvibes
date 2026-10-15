# Step A: My Passport + member menu (review, 2026-10-15)

**Status: approved by the owner on 2026-10-15** and deployed (see the commit).

## Code changes (`review.diff`)
- **Menu** (`checkedbags-nav.php`): Feed | **My Passport** (passport icon) | Navigation ▾ (Gates 07–12) | Account ▾ (**My Account**, **Traveler info**, **Log out**).
  - The "My Profile" dropdown (My Profile, Following, Find Members) is gone.
  - The top-level "Logout" is gone.
  - Change Password, Privacy and Delete Account leave the menu; they are still tabs inside My Account.
  - The pages behind My Profile, Following and Find Members still work until Step B switches them off.
- **Renamed to "My Passport"** wherever members see it:
  - the page label (with the icon);
  - the logo link and its screen-reader name, on My Passport and every Gate page;
  - the signed-in header link on the new trip landing page;
  - "Go to My Passport" on the re-accept page, and its redirect afterwards.
  - Admin wording updated too: the re-accept notice and the page-template name.
- **Address `/my-passport/`:**
  - every link in the code points there;
  - `/dashboard/` redirects to it, keeping any `?query` (redirect map in `checkedbags-redirects.php`);
  - both addresses stay out of search engines (`checkedbags-seo.php`).
- **"Travel Profile" renamed "Traveler info"** (owner: "Travel preferences", or "Traveler info" if the tab is mostly identity information. It is: personal details, emergency contact, passport, health and accessibility, with one preferences field). The new name applies to the menu item, the My Account tab, its Save button ("Save traveler info"), the My Passport reminder ("Add your traveler info...", button "Add traveler info") and the set-up hint. It is distinct from each trip's "Travel Details" form.
- **Trip guests' default after sign-in** (code default for new installs): My Passport instead of their profile.
- **CSS:** passport-icon styling (`styles.css`). A faint coin-stamp watermark was tried and dropped, because on the dark page it read as a smudge.

## Data changes at deploy time (after the code; each with a snapshot)
1. **WordPress page 82:** title "DASHBOARD" → **"My Passport"**, slug `dashboard` → **`my-passport`**.
2. **Ultimate Member sign-in landing:**
   - **Full members (subscriber):** the after-login address changes from `https://bagsandvibes.com/dashboard/` to `https://bagsandvibes.com/my-passport/`.
   - **Trip guests:** after login, "redirect to profile" → **"redirect to URL"** `https://bagsandvibes.com/my-passport/`.
   - **Admins:** unchanged (wp-admin).
3. **Purge** SiteGround and Cloudflare.

## Tests (`tests.txt`; all pass on the temp copy)
- **New `test_my_passport.sh`: 28 checks.** It covers:
  - the menu order and items as an admin, a full member (107) and a trip guest (105);
  - the page label, logos, trip landing header and re-accept text;
  - the redirect map and query handling;
  - the Travel details tab;
  - the trip guest sign-in default;
  - SEO and CSS.
  - Against the live files, 20 of these fail, so the test catches the change.
- **Older tests brought up to date with today's live facts** (not Step A changes):
  - trip 181 is live (master switch on, only 181 opted in);
  - trip 181 offers Essential and Base on request;
  - since the Helmets move, the "one trip only" member is on trip 181;
  - old-page checks use a small helper (`tests/landing/master_off.php`) that turns the master switch off in memory.
- **Every suite:** 0 failures.

## Screenshots (`*.png`)
Desktop (1440) and phone (375), with the menu closed and open:
- `107-*` is a full member;
- `105-*` is a trip guest (with the My Trips note and the "Request Full Membership" box).

## Naming clash: resolved
The account tab is now **"Traveler info"**, so it no longer clashes with each trip's **"Travel Details"** form.
