# Member hub plan: social features off, Feed hub with the podcast, My Passport, member menu (2026-10-15)

**Status: PLAN ONLY.** Nothing is built until the owner approves. It comes from the work order in `queued-after-181-live.md`. Research was read-only, on the code and the live settings, on 2026-10-15.

**Proposed build order**, three small steps, each with its own diff, tests, desktop and phone screenshots, and the owner's OK before deploying:
- **Step A:** My Passport and the member menu.
- **Step B:** the social switch.
- **Step C:** the Feed hub.

A is first because it's small and visible. B comes before C because the Feed loses its wall posts in B.

---

## 1. Switch OFF member social features (Step B)

### The switch
- **One setting:** Settings > CBGV Member Features > "Member social features (profile walls, following, members directory)" On/Off. It is stored in option `cbv_member_social_enabled`.
- **Default:** it ships **Off**, as the owner asked. Turning it back On restores everything exactly as it is today.
- **Nothing is deleted:**
  - wall forums and their posts (usermeta `_cb_wall_forum_id`, hidden bbPress forums with `_cb_wall_owner_user_id`, topics);
  - follow lists (`_cb_following`) and the auto-follow setting (`cbv_auto_follow_user_id`);
  - bios and cover photos (also used by My Account).
- **How:** every social module checks one helper inside its hooks, not at the top of the file. Several pages rely on helpers in the wall file; for example, "Who's going" calls the profile-link helper, so switching off the whole file would break trip pages. Those helpers stay loaded.
- **Walls stay private either way.** When Off, wall topics can only be opened by moderators (today trip-mates could reach a wall topic by its direct URL).

### Switched off (with the setting Off)
| Feature | What members notice | Where |
|---|---|---|
| Profile walls | No wall composer or timeline; wall posting and deleting are refused; a wall forum page goes to My Passport | member-wall.php, member-profile-hooks.php |
| Following | No Follow buttons; the Following page shows "This feature is turned off"; new members are no longer auto-followed | follows.php, member-profile-hooks.php, find-members.php |
| Members directory | `/members/` shows "This feature is turned off"; `/members/{name}/` goes to My Account instead of a profile | find-members.php, checkedbags-landing.php |
| Profile pages | `/user/{name}/` (Ultimate Member profile) goes to My Account; "Member since", Follow and Timeline are hidden | member-profile-hooks.php, new redirect |
| Wall and follow posts in the Feed | The "Members Post" group and the "your friends" wording are removed | feed.php |
| "My Profile" menu | The dropdown is removed (My Profile, Following, Find Members) | nav.php |

### Kept (unchanged)
- My Account, including Bio and Cover Photo on its General tab.
- Trip pages, trip boards, the Lounge, payments and Gate 12.
- **"Who's going":** keeps the names, **adds each member's photo** (today it shows names only), and **drops the profile links**.

### Everything affected
- **Menus:** the "My Profile" dropdown (My Profile, Following, Find Members). No WordPress Appearance > Menus are used; the menu is all in code.
- **Links:**
  - "Who's going" names on trip pages;
  - Find Members cards;
  - wall-post author names;
  - Feed wall-post links;
  - "Edit Profile" in Ultimate Member's profile menu;
  - the `/members/{name}` and `/user/{name}` addresses.
- **Pages:**
  - `/members/` (page 52, Find Members);
  - `/following/`;
  - `/user/` (page 49, Ultimate Member profile);
  - the "Members Post" part of `/member-feed/`.
- **Emails: none.** No custom email links to walls, profiles or follows. Ultimate Member's email templates on the server (approved, checkmail, pending) don't mention profiles. bbPress could send topic-subscription emails from a wall topic page; with walls Off those pages are moderator-only.
- **Admin:** Settings > Auto-Follow Account stays, marked "inactive while member social features are off".
- **Tests to update:** `test_wall_privacy.php` (and its mutant script) and `test_step9a_login.php`. New tests cover On and Off.
- **Admin manual:** section on walls (line ~825) updated, plus a new section on the switch.

---

## 2. Reshape the Feed into the CBGV hub (Step C)

**Today**, `/member-feed/` (`[cb_feed]`) shows:
- every active trip, including trips the member isn't on;
- Travel Tips;
- wall posts;
- board topics (already filtered to boards the member can access);
- destinations.

### Proposed order and layout (CBGV design: gate ribbon, boarding-pass and luggage-tag cards)
1. **Trip updates (optional; recommended).** A small "Announcements" box, written by admins on each trip's edit screen, with a message and an optional end date.
   - Shown only to members on that trip, at the top, as a coral "Gate notice" strip.
   - Hidden when there's nothing current.
2. **The Good Vibe Check (podcast).**
   - **Latest episode** as a boarding-pass card: artwork, title, date, a short description of about 2 lines, a **play button** (the browser's own audio player, loading only when pressed), "Listen on" links, and an episode count.
   - **Older episodes:** up to 3 more as compact rows ("More episodes"), once the show has them.
3. **Trip Tips:** the existing Travel Tips (`cb_tip`) as luggage tags (title, short text, link), newest first, up to 6.
4. **Your boards: most active.** A departures-board list of only the boards this member can open: the Lounge plus their own trips' boards, never other trips' and never walls.
   - **Each row:** board name, latest post title, last activity ("2h ago") and post count, plus an **Open** button.
   - **Order:** by activity in the last 30 days.
   - **Quiet boards** (almost all today: only 3 posts exist, all on a wall) show the member's boards anyway, with "Be the first to post".
5. **Removed from the Feed:**
   - Upcoming open trips. They showed trips the member isn't on; their own trips are on My Passport.
   - Destinations.
   - Wall posts (Step B).

   The data is kept. Only the Feed stops showing them.

### Podcast feed (checked 2026-10-15)
- **Feed:** `https://anchor.fm/s/117a25e28/podcast/rss` works (HTTP 200, valid RSS). The show is "The Good Vibe Check", with 1 episode so far: "Cleared for Boarding", Sep 27, 2026, 9:25.
- **Each episode has:** a title, date, description, its own artwork, an MP3 file (playable on the page) and a Spotify episode link.
- **Server:** the site's server fetches it fine with WordPress's built-in feed reader.
- **Caching:** checked at most every **6 hours**. If the feed is ever down, the last good copy is shown; with no copy at all, the podcast section hides itself.
- **"Listen on" links (owner, 2026-10-15)** live in a settings box (Settings > CBGV Member Features > Podcast), so they can be updated without code. All four were checked on 2026-10-15 and load (HTTP 200).

  | Platform | Show address |
  |---|---|
  | Apple Podcasts | https://podcasts.apple.com/us/podcast/the-good-vibe-check/id6819837564 |
  | Spotify | https://open.spotify.com/show/4QKnVZeCikRxZa5kIvV9J7 |
  | Amazon Music | https://music.amazon.com/podcasts/2aaf2a45-7d23-49ff-8bf5-7d27758eb4d0/the-good-vibe-check |
  | iHeartRadio | https://www.iheart.com/podcast/269-the-good-vibe-check-347148735 |

  - The feed address is stored in the same box.
  - A button shows only when its address is filled in.
  - Each episode's own RSS link points to podcasters.spotify.com (the Spotify for Creators site), so it is **not used publicly** (owner: creators.spotify.com is the admin page). Episodes play on the page, and the buttons go to the show on each platform.

---

## 3. Rename "Dashboard" to "My Passport" (Step A)

**Member-visible places** (the internal code names stay):
- **Menu item:** "Dashboard" becomes **My Passport**, with a small passport icon (inline SVG in the menu's own colours).
- **Page label** above "Welcome back" (template-dashboard.php) becomes **My Passport**, with the CBGV coin stamp as a faint stamp mark, the same style as the boarding pass.
- **Logo link** label (read by screen readers) on My Passport and all Gate pages becomes "My Passport".
- **New trip landing page header** (signed in): "Dashboard" becomes "My Passport".
- **Re-accept page:** "Go to your dashboard" becomes "Go to My Passport".
- **Page title** (browser tab): the WordPress page "DASHBOARD" (id 82) is renamed **My Passport**.
- **Admin-only wording** ("continuing to use their dashboard", the template name) is updated too.

**Address (decision needed):** keep `/dashboard/`, or move to **`/my-passport/`** with a permanent redirect from `/dashboard/` so old links and bookmarks keep working. Recommendation: move it.
- Every link in the code changes together, and the SEO noindex list follows.
- The after-sign-in setting is in Ultimate Member, in the database, and gets changed to the new address.

**Emails:** no custom email or Ultimate Member template says "Dashboard". The welcome email's button says "Log In Now", so nothing changes there.

---

## 4. Member menu (Step A)

**Today:** Dashboard | My Profile ▾ (My Profile, Following, Find Members) | Feed | Navigation ▾ (Gates 07–12) | Account ▾ (Account, Change Password, Travel Profile, Privacy, Delete Account) | Logout. Desktop and phone use the same menu; on phones it opens from the "Menu" button.

**Proposed:**

| Feed | My Passport | Navigation ▾ | Account ▾ |
|---|---|---|---|
| `/member-feed/` | `/my-passport/` (passport icon) | Gate 07 Planned Vacations · Gate 08 Photo Gallery · Gate 09 Payments · Gate 10 Discussion Boards · Gate 11 Vacation Requests · Gate 12 Travel Rules | **My Account** (`/account/general/`) · **Travel details** (`/account/travel-profile/`) · **Log out** |

- **"My Profile" removed** (with Step B).
- **Log out moves into Account.** The extra "Log Out" button at the bottom of the My Account page stays.
- **Change Password, Privacy and Delete Account** leave the menu but stay as tabs inside My Account. (Decision: OK?)
- **"Travel Profile" is renamed "Travel details"** in the menu and on its My Account tab.
- **"Discussion Boards"** is Gate 10 inside Navigation, as today.
- **After sign-in:**

  | Who | Today | Proposed |
  |---|---|---|
  | Full members | `/dashboard/` | **My Passport** |
  | Trip guests | their **profile page** (Ultimate Member "redirect to profile") | **My Passport** |
  | Admins | wp-admin | unchanged |

  Later, one setting switches members and guests to the **Feed** once it has Trip Tips and podcast content.
- **Screenshots before deploying:** desktop and phone, with the menu closed and open, as a full member and as a trip guest.

---

## 5. Idea for later (recorded only; NOT built)
**Passport stamps on My Passport:**
- one stamp per trip the member has taken (trip name, dates and port codes, in the CBGV coin-stamp style);
- a **"Cleared for boarding"** stamp once their trip agreement, payments and travel details are complete for an upcoming trip.

It would read existing data (rosters, trip agreement acceptances, Gate 09 payments, Travel Details), so no new forms would be needed.

---

## Decisions for the owner
1. **My Passport address:** move to `/my-passport/` with a redirect from `/dashboard/` (recommended), or keep `/dashboard/`?
2. **Account menu:** OK to drop Change Password, Privacy and Delete Account from the menu (they stay as tabs in My Account), and to rename "Travel Profile" to "Travel details"?
3. **"Who's going":** add member photos next to names (recommended), or names only?
4. **Feed:**
   - OK to remove "Upcoming open trips" and destinations?
   - Include the optional admin **trip updates** box?
5. ~~Podcast links~~ **Answered 2026-10-15:** Apple, Spotify, Amazon and iHeart show links, stored in a settings field (see section 2).
6. **Build order** A → B → C, each step with its own diff, tests, screenshots and OK. Approve?
