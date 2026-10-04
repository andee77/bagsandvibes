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
Cruise, Resort, Cabin/Villa Rental, Destination/Land Trip, Wedding, Party/Add-on, Corporate. A type only sets default-on sections, labels (cabin / room / villa, Sailors / Guests ...) and starter text. Each trip can switch any section On/Off. Defaults from the existing Trip Type term; the taxonomy itself is untouched. Corporate (and optionally any type): unlisted + access code (Step 15).

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
| 5 | Status board, intro | |
| 6 | Route (itinerary extras) | |
| 7 | Featured moment, gallery | |
| 8 | **Price board data** (see below) | |
| 9 | Price board render | |
| 9b | **Registration: opt-outs and decline acknowledgment** (new, see below; number to be assigned) | |
| 10 | Included cards, upgrades | |
| 11 | Pack list, perks, timeline (provider merge) | |
| 12 | How to book, member hint, travel docs (trip-side document editor, see below), footer | |
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

## Step 12: trip-side document editor (rules recorded 2026-10-03)
- **A trip document with the same title as a provider document REPLACES it; otherwise trip documents are appended.** (Today, in Step 3b, the provider's documents are shown first and the trip's own are only appended; the same-title replace rule arrives with this editor.)
- Follow-ups for this step, from the Step 3b review and admin check (all concern the trip key-date box):
  1. A trip row should be able to **un-hide a provider token-only date** (a provider key date that is not shown in the timeline but feeds `{date:key}` / `{days:key}`), i.e. put it in the timeline for that trip.
  2. A **new trip-only key date should require a title** (today a new key with a date but a blank title is saved as-is; a row with neither key nor title is dropped).
  3. **The trip box's Key field becomes a dropdown** of the provider's keys, plus a "New date..." choice that reveals a text field for a custom key. (Admin check 2026-10-03: on a first try the admin typed the date into the free-text Key field. A dropdown also prevents typos that silently create a new date instead of overriding one.) If the trip has no provider, only "New date..." is offered.

## Go-live checklist (Step 17; list started 2026-10-04)
- **Media: source is the First Mates marketing toolkit (FirstMates.com); use unaltered** (web compression only, no cropping, editing or logos on the asset). Every Virgin image and video, including the Terminal V hero video, comes from there. Each asset is listed in `docs/media-sources.md`. (This replaces the earlier "usage rights unconfirmed" wording.)
- **Photo frames never hide Virgin's logo or branding and never change what the photo shows.** Where the subject is not centred, set a per-image focus point (`object-position`) so the crop keeps it in view. Applied from Step 4 on.
- **Virgin brand rules** (Virgin "With Love From" brand guidelines, Nov 2024): the Virgin Voyages logo appears only in Virgin red from the official toolkit file and is never recoloured; no recreated Virgin campaign elements (the "with Love from" script lockup, promo stamps, Virgin campaign fonts); "With love from..." is never used as CBGV copy; our own headings and design stay in CBGV's brand. Checked at each step that touches media and again at final QA.
- **Offer stamps:** a toolkit image carrying an offer stamp is used only while that offer is valid; each such image is listed in `docs/media-sources.md` with its offer end date and removed when the offer ends. Check the list at go-live.
- Further items are added here as steps ship.

## Open decisions and unverified claims (running list)
- Registration and price-breakdown items listed under Steps 8 and 9b.
- Trip-side editors for the remaining provider groups (columns, included cards, footnote, steps, own documents) arrive in later steps; the trip key-date editor ships with Step 3 revision 2.
