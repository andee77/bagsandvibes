# Event page (public trip landing) redesign: plan

Design reference: `event-page-design-reference.html` (approved 2026-10-02).
Scope: redesign the public landing page at `/trip/{slug}/` for trips with "Create Public Landing Page" ticked. One template for every event type. Everything on the page comes from each trip's own fields (and its provider), never from the reference's example copy.

This document is the written record of the approved plan and its decisions. Before 2026-10-03 the plan lived only in conversation; this file was created then. Status lines below are updated as steps ship.

## Fixed rules
- **Unchanged:** the URL, the "Create Public Landing Page" opt-in, logged-out vs logged-in behaviour, Yoast + Event schema, QR code, the Reserve CTA into the tagged registration and manual-approval flow, and the Show Itinerary toggle.
- **Go-live is per trip.** Public visitors see the new design only when the master option `cbv_lp_redesign_live` is on AND that trip's "Use new landing design" box is checked (default off). Admins can preview any trip with `?preview=new` (noindex, no cache). Final step: master on, Family & Friends only.
- **Generic naming** in code, fields and classes: price board, accommodation, stops, price columns (`cbv_lp_*`, `.cbv-lp-*`). Page wording comes from the event type.
- **Sections hide when empty**, so non-cruise trips work. GATE labels renumber from GATE 14 over the sections that actually render.
- **Admin manual:** after each step, a section for admins is added to `docs/admin-manual-notes.md` (where it is in wp-admin, what each field does, order of work, rules like replace vs append, common mistakes) and committed with that step's docs. Every screen it describes is given by its full wp-admin URL (list or add-new screens, never a link to one specific post ID). The file becomes the CBGV admin user manual at the end of the build.
- **Deploy cycle** for every step: build, lint, test, show the diff, wait for approval, commit, deploy, checksum, `php -l` live, purge SiteGround and Cloudflare, push, verify.

## Event types (one template)
Cruise, Resort, Cabin/Villa Rental, Destination/Land Trip, Wedding, Party/Add-on, Corporate. A type only sets default-on sections, labels (cabin / room / villa, Travelers / Guests ...) and starter text. Each trip can switch any section On/Off. Defaults from the existing Trip Type term; the taxonomy itself is untouched. Corporate (and optionally any type): unlisted + access code (Step 15).

## Provider Library
A `cb_provider` post type (not public) stores reusable content once per provider: price column names, "what's included" cards and footnote, how-to-book steps, travel documents, key dates (days before the trip's start date). A trip picks its provider and inherits:
- Price columns, included cards, included footnote, how-to-book steps: the trip's own REPLACE the provider's.
- Travel documents: the trip's own are APPENDED after the provider's.
- Key dates: a trip date with the SAME KEY replaces that one provider date (a fixed date or an offset; blank text inherits; "not applicable" removes it; new keys are added). Tokens `{date:key}`, `{days:key}` and the timeline all read one merged list.
Virgin Voyages is seeded from the reference (separate approval; text reviewed in `virgin-voyages-seed-text.md`, unverified claims flagged).

## Build order
| # | Step | Status |
|---|---|---|
| 1 | Switches, shared predicate, template shell, `?preview=new` | Live (d00c845) |
| 2 | Event types, labels, tokens, light markup, trip settings box | Live (cb722e7) |
| 3 | Provider Library, trip Provider picker, inheritance, key dates | Live (4499f2d); revision 2 (Step 3b: key-date override, footnote, trip key-date box, `{days}`) live (9a7938b), admin check passed 2026-10-04 |
| 3b | Virgin Voyages seed | Text revised 2026-10-03; nothing written. The seed writes ONLY verified lines and prints a report of everything it skipped (see below) |
| 4 | CSS foundation, hero, boarding pass (+ itinerary stop codes) | Live 2026-10-04 (da5962f; 123 checks + 13 mutation tests). Public pages unchanged: the new design is still preview-only. New `checkedbags-lp-hero.php`, `trip-landing.js`; edits to `checkedbags-lp-core.php`, `checkedbags-trips.php` (Code column), `trip-landing.css`. Visual check: `docs/audits/step4-hero-preview-2026-10-04.html` |
| 5 | Status board, intro | Built 2026-10-05, revised after review (stages, "Travelers"), approved 2026-10-05 (138 checks incl. link safety + 23 mutation tests; Step 3 suite 186/186, Step 4 suite 123/123 with its pass-title expectation updated to "Traveler"; both suites now kept in `tests/landing/`). New `checkedbags-lp-intro.php` (status board, intro, "Status board & intro" box, GATE counter); edits to `checkedbags-lp-core.php` (render order, GATE reset), `checkedbags-lp-context.php` (cruise people-word), `checkedbags-lp-fields.php` (help text) and `trip-landing.css`. Visual check: `docs/audits/step5-status-intro-preview-2026-10-05.html` |
| 6 | Route (itinerary extras) | Built 2026-10-05, awaiting approval (102 checks + 19 mutation tests; Step 4 123/123 and Step 5 138/138 re-run). New `checkedbags-lp-route.php` (route, time line, code chain, "Route days" box, legacy itinerary table removal); edit to `checkedbags-lp-core.php` (render order) and `trip-landing.css`. Visual check: `docs/audits/step6-route-preview-2026-10-05.html` |
| 7 | Featured moment, gallery | Built 2026-10-05, awaiting approval (64 checks + 17 mutation tests; Steps 4-6 suites re-run). New `checkedbags-lp-featured.php` (featured moment, gallery, both trip boxes); edit to `checkedbags-lp-core.php` (render order) and `trip-landing.css`. Visual check: `docs/audits/step7-featured-gallery-preview-2026-10-05.html` |
| 8 | **Price board data** (see below) | |
| 9 | Price board render | |
| 9b | **Registration: opt-outs and decline acknowledgment** (new, see below; number to be assigned) | |
| 10 | Included cards, upgrades | |
| 11 | Pack list, perks, timeline (provider merge) | |
| 12 | How to book, member hint, travel docs (trip-side document editor, see below), footer; **brand features** (provider box, trip tick-list, "Only on {provider}" section) and **hide an inherited provider document on a trip** (see "Brand features ..." below) | |
| 12b | **Provider glossary** ("Use the cruise line's own words" switch, `{term:...}` tokens; see "Brand features ..." below). After Step 12 so it can be checked against every section's wording | |
| 13 | Wedding sections (story, schedule, RSVP) | |
| 14 | "Copy this trip" | |
| 15 | Unlisted + access code | |
| 16 | Final QA | |
| 17 | Go-live (per trip) | |

## Step 8: Price board data (decision recorded 2026-10-03)
Existing model: a pricing tier is a cabin category, its occupancy points carry the price for a headcount, and each point already stores `voyage_fare`, `taxes_fees`, `gratuities`, `insurance` and `discount`, with a per-person or per-cabin basis. Step 8 adds the price-column tag (Base / Essential / Premium) on occupancy points, tier group/badge, and the Fare label column in the proposal PDF.

**Decision: pricing is ALL-IN by default** (fare + taxes + prepaid gratuities + Voyage Protection), shown itemized under each cabin price. Sailors must always actively opt out; nothing is opt-in.

Draft wording, price breakdown (under each cabin price):
> All-in for 2 sailors: cruise fare $X · taxes & fees $X · prepaid gratuities $X · Voyage Protection $X

Mapping to the existing fields: cruise fare = `voyage_fare` (less `discount`), taxes & fees = `taxes_fees`, prepaid gratuities = `gratuities`, Voyage Protection = `insurance`. No new price fields are needed; the board must show the four components for the headline price and keep the per-person vs per-cabin rule already agreed.

Open: confirm each trip's tier data actually holds gratuities and insurance as separate components (Family & Friends and Helmets need checking before Step 8 is built).

## Step 9b: Registration opt-outs and decline acknowledgment (decision recorded 2026-10-03)
The tagged registration (`/join/?trip=CODE`, the traveler intake) gets two opt-out boxes, both unticked by default (sailors always actively opt out):

> [ ] Remove prepaid gratuities. Virgin will add gratuities to my onboard account each night instead, and I can adjust them at Sailor Services before I disembark.
> [ ] Remove Voyage Protection.

If protection is removed, a required acknowledgment appears:

> [ ] I understand I'm declining Voyage Protection. Virgin's cancellation rules apply to my booking, and I may lose some or all of what I've paid if I cancel. I can ask Checked Bags & Good Vibes to add protection back before final payment.
> **UNVERIFIED:** confirm the "before final payment" deadline with Virgin Groups before this goes live.

Rules:
- **Declining protection requires the ticked acknowledgment**, stored as a **versioned acceptance** using the same pattern as the Payment Disclaimer (`checkedbags-payment-disclaimer.php`): wording kept as a version, and who, when and which version accepted is recorded. (That pattern is one option holding version/content/updated plus per-user acceptance meta; per-trip storage will follow the per-trip user-meta convention already used for traveler statuses.)
- **InteleTravel requires its own decline form whenever a client declines insurance.** When a sailor declines protection, the site tells them to complete the InteleTravel form and marks their registration **"protection declined, form pending"**. An admin can mark the form as received. Form details to come (the blank form will be shared).
- Reuse note: the roster admin box already has per-traveler-per-trip flags (Paid in Full, **Insurance Waiver Received**, CC Auth Received; user meta `_insurance_waiver_received_{trip_id}`). Decide when building whether "form received" reuses that flag or is a new field.
- The itemized all-in price (Step 8/9) must update to reflect an opted-out gratuity or protection so what the sailor sees matches what they chose.

Open items before building 9b:
1. The blank InteleTravel decline form and its exact process.
2. Virgin Groups' deadline for adding protection back (the "before final payment" claim).
3. Verified wording for the gratuity opt-out (Sailor Services behaviour).
4. Where the per-registration status ("protection declined, form pending") is stored and shown to the sailor and to admin.
5. How the opt-outs flow into the existing manual-approval step and the proposal PDF.

## Virgin seed rules (decided 2026-10-03)
- **Verified lines only.** Claims flagged `[UNVERIFIED]` in the seed text are NOT written. The seed script prints a list of everything it skipped, so nothing is dropped silently. Unflagged lines are treated as verified.
- Skipping is per line (a card or document bullet) and per key-date row; where only a key date's description sentence is unverified, the date is kept and the sentence is left out.
- The `[UNVERIFIED]` markers are review annotations and are never stored.
- `funds_final` = 45 days before the start (the Ticket Contract wins: "45 days or less" is final); `date_change_deadline` = 46; `name_change_cutoff` = 2.
- The Voyage Protection document is left out of the seed until Aon's plan document is available.
- Wording that depends on the final-payment date uses `{days:final_payment}` rather than a typed number, so a trip with a different deadline stays correct.

## Step 4: decisions recorded 2026-10-04
- **Fields:** hero video, optional smaller (720p) video, poster picture, a **focus point** (nine presets, stored as `object-position`) and the vessel / venue name (`cbv_lp_venue_name`, also the `{vessel}` token). Port codes are a new "Code" column on the existing itinerary rows (`stop_code`, letters/digits, upper case, 5 max).
- **Hero video loads by script, not by markup:** no `src` in the HTML; `trip-landing.js` loads it only on screens 768px and wider, not for reduced-motion or data-saver visitors, choosing the 720p file below 1400px wide when one exists; a Pause button appears once it plays. Everyone else sees the poster picture (poster, else cover photo, else featured image, else a plain dark hero).
- **Boarding pass:** chip = trip code (hidden if empty); FROM = first stop, TO = last stop that differs from FROM, VIA = different stops in between (max 3); At Sea rows ignored; a code typed on any row for a port applies to every row with the same port name (so the return to the start port is never mistaken for a different place); stops without codes show their port name; Show Itinerary off hides the route (dates and vessel stay). "Claim your seat" links to the trip's registration (`/join/?trip=CODE`); it may point at the price board once Step 9 exists.
- **Layout:** two columns (copy + pass) only when there is room for a 520px pass; stacked below that; on phones the stub becomes a bar under the pass.
- **Old hero:** the current renderer's hero and "Departs from / Dates" strip are removed from the new design's markup (so there is a single `<h1>`); the rest of today's content stays under the new hero until later steps replace it.
- **Not built in Step 4 (decided):** the "Annual event" part of the reference's gold line (no field for it; the line shows the event type's word + dates), and the section nav links in the header (they arrive with their sections).
- **Trip 181 poster (2026-10-04):** the poster for trip 181 is `terminal-v-hero-poster-ship.jpg` (a frame from 24s of the toolkit video: ship centred, terminal and skyline), not the first-frame image. Attached via the Hero & boarding pass box after Step 4 is deployed (a trip-record write: needs your go-ahead at that point).
- **Trip 181 code (2026-10-04):** changed from CBV-2028-ANN to CBV-2027-ANN (it sails in 2027 and the code had not been shared anywhere; read-only check found the old code stored nowhere else).
- **Media rule applied:** the focus point exists so a frame never hides Virgin's logo/branding or changes what the photo shows.

## Step 5: decisions recorded 2026-10-05
- **Status board by stage (revised 2026-10-05 after review):**
  - below the minimum (or no number typed): tiles "NN OF {min}", line "{min} travelers needed to depart", chip Boarding;
  - minimum reached: tiles "NN OF {capacity}", line "Departure confirmed · {capacity minus booked} spots left" ("1 spot left"), chip On time; with no capacity set, tiles stay "NN OF {min}" and the line is just "Departure confirmed";
  - full (booked >= capacity, capacity set): tiles "NN OF {capacity}", line "Departure confirmed", chip "FULLY BOOKED · ASK ABOUT THE WAITLIST".
  Non-cruise types use their own words ("25 guests needed to confirm", Open / Confirmed, "Group confirmed"). Hidden when the trip has no minimum or its status is Completed or Declined.
- **Cruise people-word is "Travelers" / "Traveler", not "Sailors"** (2026-10-05): the cruise wording must suit Royal Caribbean and Carnival too. Changed in the shared labels (`cbv_lp_base_labels`), so it also changes the boarding pass title ("Boarding Pass · Traveler") and the `{party}` token. The draft price-breakdown and registration wording under Steps 8 and 9b that says "sailors" should follow this when those steps are built.
- **Tiles:** the count is a number typed by the admin in the new box, labelled as **paid deposits** (`cbv_lp_travelers_booked`). Not the roster count (it counts accounts, includes the admin account, misses companions and includes unpaid registrations). **Blank = no tiles**: only the line and the chip. Digits are padded to the width of the minimum; screen readers get one plain sentence instead of the tiles. **No tile animation** in this step.
- **Chip colours (approved):** gold outline below the minimum, solid gold at or above it, coral when full. Status kicker "Departure status" for cruise wording, "Group status" otherwise (approved).
- **Intro:** new "Intro (new design)" box: heading, text (paragraphs, bold, italic, links; tokens work), up to 4 typed fact chips, photo (Media Library), photo focus point (the nine hero presets), caption. No heading and no text = section hidden; no photo = text runs full width. Photos follow the media rules (First Mates toolkit, unaltered, logged in `docs/media-sources.md`).
- **Automatic chips ON:** nights (from the trip's dates) and the vessel/venue name come first, the typed chips after them.
- **GATE numbering starts in this step at 14**, counted over the sections that actually render (the status board has no GATE label; the intro is GATE 14 when shown).
- **Fresh public page after Save (checked 2026-10-05; option A approved 2026-10-05):** Cloudflare does not cache the trip page's HTML (`cf-cache-status: DYNAMIC`); only SiteGround's dynamic cache does, and SiteGround Optimizer already purges a post's URL on save (`save_post`, cb_trip is public and not excluded). So no Cloudflare token is needed on the server. Verified after deploy by a real save test on trip 181 (prime the public page to a HIT, change the booked number, Save, expect the next public request to be a MISS; the number itself is checked in ?preview=new while the master switch is off). If it fails, option B (a server-side purge of the trip URL through SiteGround's own function) is proposed with its diff and added only with approval. Go-live checklist item: if Cloudflare HTML caching (APO or a cache-everything rule) is ever turned on, this must be revisited.

## Step 6: decisions recorded 2026-10-05
- **One card per day, worked out from the Day-by-Day Itinerary** (rows grouped by date; rows without a date grouped by their Day number). The day number shown ("DAY 01") counts from the trip's start date, whatever the row's own Day box says (trip 181 numbers from 0). Place: the day's port, or "At sea".
- **Per-day extras live in a separate "Route days (new design)" box** (not new itinerary columns; the itinerary editor and the proposal PDF are untouched): title, text (light markup + tokens), sticker text, sticker style (Standard cream / Highlight coral), photo + focus point (shown in a 3:2 frame, revised from a fixed 200px height after review so toolkit photos are not cut into a strip), "Hide the automatic time line". Stored by **day number from the start date**, so moving the whole trip to new dates keeps each day's content. Empty title = the place name; empty text = only the time line. New itinerary days appear in the box after the trip is saved.
- **Automatic time line ON**, each day can hide it. Wording (approved 2026-10-05, times as "5:00 pm"; a part with no time is left out):
  - Embarkation: cruise **"Sail-away 5:00 pm"**; other event types "Departs 5:00 pm" (the row's time is the departure time, not boarding).
  - Arrival and Departure on the same day: **"Docked 10:00 am – 6:00 pm"**, **"Tender port · 10:00 am – 6:00 pm"** when the rows say Tender (approved wording), "In port 10:00 am – 6:00 pm" when Dock/Tender is blank; other event types just "10:00 am – 6:00 pm".
  - Arrival only: "Arrives 10:00 am" (an overnight stay: "Arrives 10:00 am · overnight"); Departure only: "Departs 6:00 pm".
  - Disembarkation: **"Back in {port} at 6:30 am"** ("Back in {port}" with no time).
  - At Sea: no time line.
- **Code chain** ("MIA → SEA → POP → SEA → BIM → MIA") only when every port day has a Code (Step 4 code-sharing applies); "SEA" for days at sea. Otherwise not shown.
- **Heading is a field** in the box (blank = the default), defaulting per event type (approved): Cruise and Destination "The Route", Resort "The Plan", Corporate "The Agenda" (the GATE label above it keeps the event type's section name: Flight path / The route / The plan / Agenda).
- Hidden when Show Itinerary is off, the section is Off, or the itinerary is empty. Today's itinerary table is removed from the new design's markup (as the old hero was in Step 4); the public page is unchanged.
- **Brand features attach to days in Step 12**; Step 6 stickers are typed text.
- **Trip 181 times to confirm (owner checking against Virgin's itinerary, 2026-10-05):** Embarkation is stored as 12:01 (likely 17:00 sail-away) and Disembarkation as 18:30 (likely 06:30). Not to be changed until confirmed; the automatic time line shows whatever is stored.

## Step 7: decisions recorded 2026-10-05
- **One featured moment per trip**, in a new "Featured moment (new design)" box: title (no title = no section), optional **Day** (a dropdown of the trip's route days, **stored by day number from the start date** like the route days, so it survives a date move; adds "DAY 02" to the label "GATE 16 · DAY 02 · AFTER DARK"), text (light markup + tokens), optional note card (label + one line, e.g. "Dress code · All red"), up to **2 photos** in a 2:3 portrait frame with focus points (no photos = text full width), and a **background colour**.
- **Background colours:** Horizon blue `#1B3A4B` (default), Ink `#16232B`, Palm teal and a **Deep red** accent `#5E0B12` for red-themed events. No free colour field.
  - **Contrast checked (WCAG AA needs 4.5:1 for body text):** cream title / body text / gold label: Horizon 10.9 / 9.5 / 5.8; Ink 14.6 / 12.5 / 7.8; Deep red 12.5 / 10.6 / 6.7 — all pass. **Brand Palm teal `#2E7D6E` fails** (4.5 / 4.1 / 2.4), so the band uses a **deeper shade of the brand teal, `#174A41`** (9.1 / 8.0 / 4.9, all pass). **Approved 2026-10-05: `#174A41` for the featured band only; the brand Palm teal `#2E7D6E` stays unchanged everywhere else.** The note card (ink on cream, 14.6) passes on every option. **Ink band edge (2026-10-05, after review):** Ink is also the page background, so an Ink band gets a 1px muted-gold line top and bottom, the same as the footer's top edge (`rgba(232,169,78,0.35)`); the other colours have no line.
- **Gallery** in a new "Gallery (new design)" box: heading, intro line, up to **10 photos** (caption + focus point each, 4:5 frame, tilted prints), ordered with **Up / Down / Remove** buttons; "Add photos" opens the Media Library for several at once. No photos = no section. Only admin-chosen Media Library photos are used, never the members' Gate 08 uploads.
- **Rows:** 5 per row on a computer, 3 on a tablet, 2 on a phone. **An incomplete last row is centred** (on a tablet the 10th photo sits centred under the row above), so it looks intentional.
- **No lightbox** in Step 7.
- **Alt text:** every featured/gallery photo needs Alt Text in its Media Library entry (admin manual section 4); the page reads it from there.
- GATE numbers continue automatically (featured 16, gallery 17 after the route); hidden sections do not use a number.
- Today's legacy "Highlights" boxes stay until Steps 10-11 (their content belongs to "What's included" / "Group perks").
- Brand features feeding the featured moment stays open for Step 12.

## Step 12: trip-side document editor (rules recorded 2026-10-03)
- **A trip document with the same title as a provider document REPLACES it; otherwise trip documents are appended.** (Today, in Step 3b, the provider's documents are shown first and the trip's own are only appended; the same-title replace rule arrives with this editor.)
- Follow-ups for this step, from the Step 3b review and admin check (all concern the trip key-date box):
  1. A trip row should be able to **un-hide a provider token-only date** (a provider key date that is not shown in the timeline but feeds `{date:key}` / `{days:key}`), i.e. put it in the timeline for that trip.
  2. A **new trip-only key date should require a title** (today a new key with a date but a blank title is saved as-is; a row with neither key nor title is dropped).
  3. **The trip box's Key field becomes a dropdown** of the provider's keys, plus a "New date..." choice that reveals a text field for a custom key. (Admin check 2026-10-03: on a first try the admin typed the date into the free-text Key field. A dropdown also prevents typos that silently create a new date instead of overriding one.) If the trip has no provider, only "New date..." is offered.

## Brand features, document hiding, provider glossary (recorded 2026-10-05; not built)
Placement: items 1-3 in **Step 12** (they are trip-side editors over provider content, and item 3 sits in the same document editor as the same-title replace rule above). Item 4 is a separate **Step 12b**, because a glossary changes wording in every section and is only testable once all sections exist.

1. **Provider "Brand features" box** (on each provider, https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider): a repeater of **name**, **short description**, **type** (Event / Program / Onboard / Destination) and an **optional photo** (with the per-image focus point). Virgin examples: Scarlet Night, Shore Things, Happenings Cast, The Beach Club at Bimini. Each row gets a stable hidden id when first saved, so reordering, renaming or deleting rows never moves a trip's ticks onto a different feature.
2. **Trip box** lists the trip's provider's brand features, one checkbox each; only ticked ones render, as a section such as "Only on {provider}" (its own GATE number, its own On/Off row in the Sections table, hidden when nothing is ticked).
   - **Proposed default: unticked.** A feature only appears after an admin ticks it for that trip, because many features depend on the sailing (The Beach Club at Bimini only on itineraries that call there; a program may not run on every ship or date). A feature added to the provider later therefore never appears on existing trips by itself. A "Tick all" button in the trip box keeps the common case quick.
3. **Trip box: hide an inherited provider document** on this trip, one checkbox per provider document ("Hide on this trip"), unticked by default. Hidden documents are not shown and not counted; the trip's own documents and the same-title REPLACE rule are unaffected. Stored by the document's stable id, like brand features.
4. **Optional provider glossary, off by default (Step 12b):** each provider can list its own words for generic terms (travelers -> Sailors, excursions -> Shore Things, cabin -> ... ), and each trip gets a **"Use the cruise line's own words"** switch (default off). Text uses tokens like `{term:excursions}`: switch off = the generic word, switch on = the provider's word, falling back to the generic word when the provider has none (never blank). With the switch on, the glossary's travelers entry also replaces the shared people-word (so a Virgin trip could say "Sailors" again; with it off every cruise says "Travelers", per the Step 5 decision).

**Rules**
- **Brand features belong to their provider only.** A trip shows only features of its own current provider; ticks are stored with the provider they belong to, ignored if the trip's provider changes, and dropped on the next save. Never shown on another provider's trips.
- **Media and brand rules apply** (go-live checklist): photos from the provider's official toolkit (Virgin: First Mates), unaltered, logged in `docs/media-sources.md`, focus point so a frame never hides a logo. Feature names are written as plain text in CBGV's design; never Virgin's campaign fonts, lockups or stamps.
- Open when built: whether the Step 7 "featured moment" may pick a brand feature as its source instead of its own fields.

## Go-live checklist (Step 17; list started 2026-10-04)
- **Media: source is the First Mates marketing toolkit (FirstMates.com); use unaltered** (web compression only, no cropping, editing or logos on the asset). Every Virgin image and video, including the Terminal V hero video, comes from there. Each asset is listed in `docs/media-sources.md`. (This replaces the earlier "usage rights unconfirmed" wording.)
- **Photo frames never hide Virgin's logo or branding and never change what the photo shows.** Where the subject is not centred, set a per-image focus point (`object-position`) so the crop keeps it in view. Applied from Step 4 on.
- **Virgin brand rules** (Virgin "With Love From" brand guidelines, Nov 2024): the Virgin Voyages logo appears only in Virgin red from the official toolkit file and is never recoloured; no recreated Virgin campaign elements (the "with Love from" script lockup, promo stamps, Virgin campaign fonts); "With love from..." is never used as CBGV copy; our own headings and design stay in CBGV's brand. Checked at each step that touches media and again at final QA.
- **Offer stamps:** a toolkit image carrying an offer stamp is used only while that offer is valid; each such image is listed in `docs/media-sources.md` with its offer end date and removed when the offer ends. Check the list at go-live.
- **Caching:** Cloudflare must not cache trip page HTML (no APO / cache-everything rule) unless a token-free purge path is designed first; the status board's "paid deposits" number relies on SiteGround's purge-on-save (Step 5).
- Further items are added here as steps ship.

## Open decisions and unverified claims (running list)
- **Tidy-up (later, not scheduled):** gallery box, when the gallery already holds 10 photos, "Add photos" should say "The gallery is full (10 photos)" instead of "Only the first 0 were added" (today the button is disabled at 10, but the wording should still be clear if it is reached). Recorded 2026-10-05.
- **Before quoting a Royal Caribbean or Carnival trip:** change the admin and proposal wording from "Sailors" to "travelers" (the "# Sailors" column labels in the Pricing Tiers / Occupancy Points editor in `checkedbags-trips.php` and the proposal PDF table in `checkedbags-proposal-pdf.php`), matching the Step 5 decision that cruise wording says "Travelers". Left as is for now (decided 2026-10-05).
- **Text formatter, links containing ")":** a link address stops at its first ")", so `[x](https://en.wikipedia.org/wiki/Bimini_(island))` loses the last ")" and leaves it in the text. Safe (only http, https, mailto and tel links are ever made; `javascript:`, `data:` and others are dropped, tested in `tests/landing/test_step5.php`), but worth fixing in the formatter (`cbv_lp_inline`, Step 2) at a later step. Found 2026-10-05.
- Registration and price-breakdown items listed under Steps 8 and 9b.
- Trip-side editors for the remaining provider groups (columns, included cards, footnote, steps, own documents) arrive in later steps; the trip key-date editor ships with Step 3 revision 2.
