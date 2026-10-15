# Step C: the Feed hub (review, 2026-10-15)

**Status: built and tested on the temp copy. NOT deployed.** Waiting for the owner's OK.

## What the Feed (/member-feed/) shows, in order
1. **Trip updates ("Gate notice"):**
   - **Who sees them:** members of that trip only; admins see every trip's.
   - **What shows:** a coral-edged strip with the trip name and the message.
   - **Where they come from:** a new **"Trip updates (shown on members' Feed)"** box on each trip's edit screen, with up to 5 messages of 300 characters each, each with an optional "show until" date.
   - Expired updates disappear on their own. No updates means no strip.
2. **The Good Vibe Check (podcast).** The latest episode appears as a boarding-pass card:
   - artwork, title, date and length ("Sep 27, 2026 · 9 min"), and a short description;
   - a play button; the audio only loads when pressed;
   - **Listen on** Apple Podcasts, Spotify, Amazon Music and iHeartRadio, each opening in a new tab.
   - Up to 3 more episodes show as rows once the show has them.
   - **Source:** the RSS feed, checked at most every 6 hours. If the feed is down, the last good copy shows; with neither, the section hides.
   - **Links:** the feed address and the four "Listen on" links are on **Settings > CBGV Member Features**, editable without code. They default to the owner's links.
   - The Spotify for Creators (admin) addresses from the RSS are never shown.
3. **Trip Tips:** the existing Travel Tips, newest 6, as luggage tags ("Tip 01").
4. **Your boards:** a departures board.
   - **Which boards:** only the Lounge and the member's own trips' boards (admins: every trip board); never another trip's, never walls. Each board also has to pass the site's own "can read this board" check.
   - **Columns:** Board, Latest (topic link, or "Be the first to post"), Last activity, Posts, and an **Open** button.
   - **Order:** most posts in the last 30 days first, then the latest activity.
5. **Members Post** (wall posts): only while the member social features are switched ON.
6. **Upcoming open trips:** a small strip of chips with name and spots left, **soonest first**. The old list was meant to sort by date but didn't.

**Removed:** Destination Inspiration. The `cb_destination` posts are kept, just not shown.

## Files (`review.diff`)
- **New `wp-content/mu-plugins/checkedbags-feed-hub.php`:** the podcast settings and feed reader, the trip-updates box, the boards list, and the hub layout.
- **`checkedbags-feed.php`:** `[cb_feed]` now renders the hub; the upcoming-trips sorting is fixed.
- **`checkedbags-member-features.php`:** the settings page shows the podcast fields.
- **`styles.css`:** the hub styles (desktop and phone).

## Tests (`tests.txt`)
- **New `test_feed_hub.sh`: 26 checks, all pass.** Against the live files, 21 of them fail, so the test catches the change.
  - **Podcast:** settings defaults and cleaning; the live feed reads correctly; the fallback works; no address means the section is hidden.
  - **Layout:** the section order; the podcast card and its links; nothing from Spotify for Creators.
  - **Content:** tips; boards per member (181-only, both trips, no trip, admin); board order; destinations gone.
  - **Trip updates:** current ones only, for that trip's members only. Update cleaning; the admin box; its save writes only its own field (intercepted).
  - **Settings fields and CSS.**
  - The podcast checks keep the feed cache in memory, so nothing is written.
- **`test_member_social.sh`** accepts the hub's new intro line.
- **Every other suite:** 0 failures.

## Screenshots (test copy)
`feed-desk.png` and `feed-phone.png` show the Feed as a member of trip 181. The "Gate notice" is a **sample** held in memory only; nothing was saved.

## Not changed yet
After sign-in, members still land on **My Passport**. The plan was to switch to the Feed once it has Trip Tips and podcast content, which it now does. That switch is the owner's call.
