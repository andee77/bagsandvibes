# Step B: member social features switch (review, 2026-10-15)

**Status: built and tested on the temp copy. NOT deployed.** Waiting for the owner's OK. The menu alignment fix (`../menu-align-2026-10-15/`) is in the same `styles.css` change.

## The switch
- **Settings > CBGV Member Features**, "Member social features": On or Off. It is stored as option `cbv_member_social_enabled`.
- **Ships OFF.** There is no setting saved yet, which counts as off, so turning it off needs no data change at deploy.
- **Override:** a `CBV_MEMBER_SOCIAL` constant in wp-config would override the checkbox; it isn't used.
- **Nothing is deleted.** Wall posts, follow lists, bios and cover photos stay. Ticking the box restores everything as it was.

## While OFF (default)
| Feature | What members get | Where |
|---|---|---|
| Wall posting and deleting, follow and unfollow | Refused (403 "This feature is switched off.") | new `checkedbags-member-features.php` |
| Following page, Find Members page | "This part of the site is switched off for now. Your trips, trip boards and account are all still here: go to My Passport." Signed-out visitors still get the sign-in prompt. | same |
| `/user/{name}/` (profile), `/members/{name}/`, bbPress `/forums/users/{name}/` | Go to My Account | same |
| A wall's forum page | Closed to members (they're sent to My Passport, or get "not found") | same |
| Walls themselves | Readable by moderators only; owner and trip-mates can't open them | `checkedbags-member-wall.php` (2 guarded spots) |
| New members | No longer auto-followed | `checkedbags-follows.php` |
| Feed | No "Members Post" wall section; the intro now says "upcoming open travel, travel tips and the latest from your trip boards" | `checkedbags-feed.php` |
| "Who's going" (trip pages) | Photo and name for each traveler, no profile links. With the switch ON the names link to profiles again, and photos stay | `checkedbags-gate07.php`, `styles.css` |

**Unchanged:** My Account (including Bio and Cover Photo), trip pages, trip boards, the Lounge, payments and Gate 12. The menu already lost "My Profile" in Step A.

## Everything affected
- **Menus:** none left to change (Step A removed "My Profile").
- **Links:** "Who's going" names; Find Members cards (page now a notice); wall-post author names and Feed wall links (not shown); bbPress author links in boards now lead to My Account for members.
- **Pages:**
  - `/following/` and `/members/` (notice);
  - `/user/…`, `/members/…` and `/forums/users/…` (redirect);
  - wall forum and topic pages (closed);
  - the Feed's wall section.
- **Emails:** none. No custom or Ultimate Member email links to walls, profiles or follows.
- **Admin:** Settings > CBGV Member Features (new). Settings > Auto-Follow Account has no effect while off; the new page says so.

## Tests (`tests.txt`; all pass on the temp copy)
- **New `test_member_social.sh`: 23 checks, OFF and ON.**
  - REST checks run with every database write blocked.
  - It confirms nothing is deleted.
  - Against the live files, 16 of these fail, so the test catches the change.
- **Helper `tests/landing/social_on.php`** turns the switch ON in memory.
  - `test_wall_privacy.php` describes walls with social on, so it now runs through this helper: 33/33.
  - In `test_forum_design.sh`, the reply-form check opens a wall post ("Good morning travel family"), so it runs with social on: 51/51.
- **Every other suite:** 0 failures.

## Screenshots (test copy; member names masked)
- `following-desk.png`: the notice.
- `feed-desk.png`: no wall section, new intro.
- `trip-roster-desk.png` / `-phone.png`: "Who's going" with photos (Ultimate Member's default silhouette for members without a photo) and no links.
- `members-desk.png`: the notice.
