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
- **Public text never names InteleTravel** (decided 2026-10-08). Say "independent travel advisor" (Andrea M. Peaten). The host agency is disclosed on request, or where InteleTravel / Seller of Travel law requires (e.g. a registration line). Internal docs and admin-only notes may name it.
- **One CBGV fee, one name** (decided 2026-10-08): the "CBGV Commitment Fee" on the member Payment page is the same fee as the **CBGV Group Experience Fee**; every mention uses the new name. In the Payment Disclaimer this was a name swap only (same fee, same obligation): the text was updated without a version bump, so no re-acceptance.
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
| 8 | **Price board data** (see below) | Built 2026-10-05, awaiting approval (59 checks + 20 mutation tests; Steps 4-7 suites re-run). New `checkedbags-lp-prices.php` (price columns, all-in breakdown, board data); `checkedbags-trips.php` (Pricing Tiers: tier Group / Badge / Note / Highlight / One price only, point Price column; tier sanitizing moved into `cb_sanitize_pricing_tiers()`, junk rows now skipped); `checkedbags-proposal-pdf.php` ("Fare" column). Nothing drawn on any page yet |
| 8b | **CBGV Group Experience Fee** (new 2026-10-08; see below) | Built 2026-10-08 (64 checks, 29 mutants caught); awaiting diff review and deploy approval. Step 9 waits for this |
| 9 | Price board render | Plan presented 2026-10-08; waits for Step 8b |
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

Checked 2026-10-05 (read-only): both Family & Friends (181) and Helmets (320) store gratuities and insurance as separate components. (Helmets' per-person / per-cabin basis is being checked; see Step 8 build decisions.)

## Step 8: build decisions (recorded 2026-10-05)
- **Where:** the new fields go **inside the existing Pricing Tiers box** (`checkedbags-trips.php`), the one place prices are entered. No separate price-board box.
- **Per tier (cabin category):** **Group** (e.g. "Insider · Interior"; tiers with the same Group form one section, in listed order), **Badge** (optional, e.g. "Partial view", "★ Group favorite"), **Highlight this row**, **One price only (suites)**, and an optional **Note** (one line under the row, e.g. "Booked alongside the group; group perks apply to Sea Terrace"), ready for when Ruel confirms the Groups Offer rules.
- **Per occupancy point:** **Price column** dropdown = the provider's (or trip's) column names, stored by position 1-4 so renaming a column never breaks the link, plus **"Not on the price board"**. Existing data with no tag = column 1, each tier its own group, so nothing changes until tags are set.
- **Lock-It-In:** trip 181's three "Locked In" tiers stay in the admin data and the proposal PDF and are tagged **"Not on the price board"** (they cannot be held in the group).
- **Base:** also **"Not on the price board"** until Ruel confirms (Base cannot be held in a group either; the design reference's BASE column is not used meanwhile).
- **Lead price:** the **all-in price per cabin for 2 travelers**, with the per-person price as a small line underneath.
- **Discount** stays folded into the cruise fare (cruise fare = `voyage_fare` − `discount`); no separate "group savings" line.
- **Proposal PDF:** a "Fare" column showing each point's price-column name.
- **Virgin Groups Offer (note, to confirm with Ruel):** per Virgin's Groups Offer, **only Sea Terrace and Central Sea Terrace cabins (Essential or Premium) can be held in the group block**; other cabins join as **"FIT to include"** (booked alongside the group) **without the group bar tab or group discount**. Once confirmed, the board will likely need the per-tier Note on those rows (e.g. "Booked alongside the group; group perks apply to Sea Terrace") and the perks/included wording (Steps 10-11) must not promise the bar tab or discount to non-block cabins.
- **Helmets (trip 320) data check, owner checking the quote (2026-10-05):** its occupancy points are marked per person but the numbers look like whole-cabin totals (taxes $404 for 2, $606 for 3), which would make today's per-person prices 2-3x too high on Gate 07 and the current landing page. No change until confirmed; any fix is a trip-record write through the real box with the owner's go-ahead, separate from Step 8.

## Step 8b: CBGV Group Experience Fee (requirement recorded 2026-10-08; built, awaiting deploy approval)
**Renamed 2026-10-08 (compliance update, wording only):** formerly the "CBGV group planning fee". Every label travelers or admins see now says **"CBGV Group Experience Fee"** (settings page, trip box, breakdown line, PDF column, board note, admin manual, refund policy, now `docs/policies/cbgv-group-experience-fee-refunds.md`); the settings page address is `options-general.php?page=cbv-experience-fees`. Internal code names (`cbv_lp_planning_fee`, `cbv_lp_planning_fees`, `cbv_lp_planning_fee_live`) are unchanged; no one sees them. The fee stays behind the off switch.

**Who charges it:** the fee is **Checked Bags & Good Vibes' own Group Experience Fee** (CBGV, run by LaDon Headen, does the group planning). It is **not a travel agent fee**: Andee is the travel agent who books through InteleTravel, and cruise payments go directly to the cruise line.

**Requirement (owner, 2026-10-08):**
- A **standard fee per event type** (cruise, resort, destination ...), set once, with a **per-trip override**.
- **Basis** selectable: **per traveler**, **per cabin**, or **flat per booking**.
- Shown as its **own fifth line** in the all-in breakdown, labelled **"CBGV Group Experience Fee"**, **included in the all-in total**, **never folded into the cruise fare**, and **not opt-out** (the Step 9b opt-outs stay gratuities and Voyage Protection only).
- A **note under the price board**: the fee is paid to Checked Bags & Good Vibes for the group program; travel payments go directly to the cruise line or supplier (exact wording under the build decisions).
- **Proposal PDF:** the fee as its own column.
- **Refund rule depends on timing:** a key date (e.g. `planning_fee_refund_cutoff`) so the page can say "refundable until {date}". The exact rule goes in CBGV's Terms page later.

**Refund policy (decided; approved by LaDon Headen, recorded 2026-10-08).** Full text: `docs/policies/cbgv-group-experience-fee-refunds.md` (source for the footer Terms page later).
- **Full refund within 7 days of paying**, if the trip is **120+ days away** (on the day of payment).
- **50% refund** after that, **until the trip's final payment date**.
- **Non-refundable from the final payment date.**
- **Transfers** to a replacement traveler at **no charge**.
- **Full refund if CBGV or the supplier cancels**; if **rescheduled**, the fee **carries over** (full refund if the traveler can't make the new date).
- Refunds to the **original payment method within 14 days**.
- **Same structure for every trip type.** The cutoff is the **trip's own final payment date**: use **`{date:final_payment}`** (provider key date with any trip override), so no separate `planning_fee_refund_cutoff` key date or per-type refund setting is needed (this replaces that part of the requirement above).
- **Price board refund wording:** now part of the single board note under the build decisions (compliance update 2026-10-08). The earlier separate refund line is superseded.

**Build decisions (recorded 2026-10-08):**
- **Where it is set:** a "Group Experience Fees" admin page (Settings menu, administrators only) with one row per event type: Amount (blank / $0 = no fee) and Basis (per traveler, per cabin = per room / villa for other types, flat per booking). **Built with blanks; LaDon provides the standard amounts, entered after deploy with the owner's go-ahead.**
- **Per trip:** a "Group Experience Fee" box with **Use the standard fee** (default), **Use a different fee for this trip** (amount + basis) and **No Group Experience Fee on this trip** (waiver).
- **Flat per booking:** counted **once in each cabin's all-in price**, with the note **"one fee per booking, however many cabins you book together"**.
- **Breakdown:** fifth line "CBGV Group Experience Fee $X"; included in the all-in total and per-person price; never folded into the cruise fare; not opt-out. No fee = every price exactly as before.
- **Price board note** (drawn in Step 9, only when the trip has a fee; **compliance update 2026-10-08**, one note, the same for every trip type): "The CBGV Group Experience Fee is paid to Checked Bags & Good Vibes (a d/b/a of JourneyWell Global LLC) for the group program; travel payments go directly to the cruise line or supplier. It's fully refundable within 7 days of paying, 50% refundable until {date:final_payment}, and non-refundable after that. Full refund if the trip is cancelled." Flat-per-booking trips add "One fee per booking, however many cabins you book together." **When the trip has no final payment date:** "...50% refundable until the trip's final payment date, and non-refundable after that..."
- **Proposal PDF:** a "CBGV Group Experience Fee / Cabin" column **and totals that include the fee**, only for trips with a fee; trips without a fee print exactly as before.
- **Gate 07 and today's public landing page stay unchanged until go-live** (they will show the price without the fee until the new design replaces them).
- **Before a fee is turned on for trip 181 or Helmets (320):** the owner confirms their current prices do not already contain a CBGV fee (no double counting).
- **The 7-day / 120-day refund rule depends on the payment date:** the page states the rule; applying it is manual for now. Payment-date tracking goes to Step 9b.

- **Standard fee from LaDon (2026-10-08): $250.00 per traveler, the same for every event type** (Cruise, Resort, Cabin/Villa, Destination, Wedding, Party, Corporate). Entered on the settings page **after Step 8b is deployed and approved, with the owner's go-ahead**.
- **Not public until the go-live business checks are done:** the settings page has a **"Group Experience Fee is live"** switch, **off by default**. While it is off the fee shows only in admin previews (`?preview=new`) and in the admin boxes, never in proposal PDFs or any public view; it is turned on only after the checks in the go-live checklist, with the owner's go-ahead.

**Status:** built 2026-10-08 (new file `checkedbags-lp-fees.php`; edits to `checkedbags-lp-prices.php` and `checkedbags-proposal-pdf.php`; tests `tests/landing/test_step8b.php`, `mutate_step8b.sh`; admin manual section 10). Awaiting diff review and deploy approval; the $250 standard is entered after deploy with the owner's go-ahead, and **Group Experience Fee is live** stays off until the go-live business checks are done. Step 9 (price board render) waits for Step 8b so the board is drawn with the fee line from the start.

## How to book and "Who does what" disclosure (recorded 2026-10-08; text to come; for the Step 12 How-to-book seed and the Terms page)
- **Four new How-to-book steps** and a **"Who does what" disclosure**: the owner is sending the exact text as a file; it is recorded here word for word when it arrives. Used as the **How-to-book seed** (Step 12) and on the **Terms page** (footer, Step 12).
- **Rule: "CBGV books your cabin" must never appear**, in any wording, anywhere on the site, in proposals or in emails. **The travel advisor books the cabin** (through InteleTravel); CBGV runs the group program and charges the CBGV Group Experience Fee. Checked in every step that writes booking wording, and again at go-live.
- The disclosure must be **live on every trip page** before go-live (see the go-live checklist).

## Member Payment page and the Step 8b fee (recorded 2026-10-08)
- **Before this change (Gate 09):** the Payment page billed through Stripe (live keys) a per-member amount = **Price per person** (`cb_price`) + **Extras Cost** (`cb_extras_cost`). On 2026-10-08 trip 181 = $0 + $225 and Helmets (320) = $0 + $150 (the fee was being held in Extras Cost); no payments recorded on either. Public pages never showed this amount.
- **Payment safeguard (owner's rules, 2026-10-08; tests in `tests/payments/`): CBGV never collects travel funds.**
  - The server computes every charge from the **CBGV Group Experience Fee** (the trip's own fee from its Group Experience Fee box; the standard fee only once "Group Experience Fee is live" is on) **for the member's adults and children + approved extras** (the Extras Cost field, now labelled "Approved extras ($ per member)"). Never `cb_price` or `cb_quoted_price`, whatever their values.
  - **Travelers per member** = the member + additional adults (full fee each) + additional children (**half** the fee each, per-traveler fees only) from the member's traveler intake (the member alone when the intake is empty). The hard ceiling uses the same calculation.
  - **Hard ceiling:** the checkout recomputes fee x travelers + approved extras independently and refuses (and logs) any charge that would take the member above it; the Stripe webhook logs any payment Stripe reports above it. The log is on Settings > Group Experience Fees.
  - Stripe charge name and description: **"CBGV Group Experience Fee: {trip name}"**.
  - Admin: "Price per person" is relabelled **"Travel price (reference only, never charged here)"** (and the Gate 12 quoted price likewise). Gate 12 quote acceptance stores the quoted travel price as that reference only; the member-facing quote and confirmation say the travel price is paid directly to the cruise line or supplier.
- **Children pay half (LaDon's rule, 2026-10-08):** when the fee is charged **per traveler**, every child pays **half** the CBGV Group Experience Fee (e.g. $250 per adult and $125 per child; on trip 181, $225 per adult and $112.50 per child). Per-cabin and flat-per-booking fees are not affected. The adults/children split comes from the traveler intake (the member counts as an adult). **Child age cutoff (confirmed by LaDon 2026-10-08): children are 17 and under; 18 and over is an adult**, entered as "Children are under 18 years old" on Settings > Group Experience Fees; the intake form then shows it next to "Additional adults" and "Additional children", ("Additional adults traveling with you (age 18 and over)", "Additional children traveling with you (under 18)"), so members choose the right box. The price board and PDF prices are "for 2 travelers" as adults; when the trip's fee is per traveler, the board note and the PDF add "Children pay half the CBGV Group Experience Fee." Refunds work the same way, on the amount actually paid.
- **Fee amounts (decided 2026-10-08):** trip 181 **$225 per traveler**, Helmets (320) **$150 per traveler**, each set as the trip's own fee (override); **$250 per traveler stays the standard** for new trips. **Order of the WordPress writes after this change deploys (each with the owner's go-ahead):** (1) set each trip's override **and** set its Approved extras to $0 in the same save, so the fee is not billed twice; (2) only then enter the $250 standard. Until (1), both trips keep billing $225 / $150 from the extras field, exactly as before.
- **Per member vs per traveler (for Step 9b):** billing now counts travelers from the traveler intake, but the intake is optional and most members have not filled it in, so a member who books for two may be billed for one until it is. Step 9b (registration and payments) should make the traveler count part of registration and record which travelers each member pays for.
- **Monthly reconciliation:** see the admin manual, "Payments and Stripe".

## Step 9b: Registration opt-outs and decline acknowledgment (decision recorded 2026-10-03)
The tagged registration (`/join/?trip=CODE`, the traveler intake) gets two opt-out boxes, both unticked by default (sailors always actively opt out):

> [ ] Remove prepaid gratuities. Virgin will add gratuities to my onboard account each night instead, and I can adjust them at Sailor Services before I disembark.
> [ ] Remove Voyage Protection.

If protection is removed, a required acknowledgment appears:

> [ ] I understand I'm declining Voyage Protection. Virgin's cancellation rules apply to my booking, and I may lose some or all of what I've paid if I cancel. I can ask Checked Bags & Good Vibes to add protection back before final payment.
> **UNVERIFIED:** confirm the "before final payment" deadline with Virgin Groups before this goes live.

Rules:
- **Declining protection requires the ticked acknowledgment**, stored as a **versioned acceptance** using the same pattern as the Payment Disclaimer (`checkedbags-payment-disclaimer.php`): wording kept as a version, and who, when and which version accepted is recorded. (That pattern is one option holding version/content/updated plus per-user acceptance meta; per-trip storage will follow the per-trip user-meta convention already used for traveler statuses.)
- **InteleTravel requires its own decline form whenever a client declines insurance.** When a sailor declines protection, the site tells them to complete the travel advisor's insurance decline form (InteleTravel's form; the public wording does not name InteleTravel) and marks their registration **"protection declined, form pending"**. An admin can mark the form as received. Form details to come (the blank form will be shared).
- Reuse note: the roster admin box already has per-traveler-per-trip flags (Paid in Full, **Insurance Waiver Received**, CC Auth Received; user meta `_insurance_waiver_received_{trip_id}`). Decide when building whether "form received" reuses that flag or is a new field.
- The itemized all-in price (Step 8/9) must update to reflect an opted-out gratuity or protection so what the sailor sees matches what they chose.

- **Optional group extras (added 2026-10-08, from the Group extras item under Steps 10-11):** optional extras need **sign-up at registration** (which travelers want which extra) and **payment tracking**; build those here.
- **CBGV Group Experience Fee payment dates (added 2026-10-08, from Step 8b):** record when each traveler pays the CBGV Group Experience Fee, so the refund rule (full refund within 7 days of paying if the trip is 120+ days away; 50% until the final payment date; none after) can be applied from the record instead of manually.

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

## Group extras (recorded 2026-10-08; not built; with Steps 10-11)
A **trip-level list of add-ons or events the group requests, priced per request**. Each extra has: **name**, **price**, **basis** (per traveler / per cabin / flat), and **Required** or **Optional**:
- **Required:** included in every traveler's all-in price as **its own breakdown line** (like the CBGV Group Experience Fee), so the all-in total stays honest.
- **Optional:** listed **under the price board as an add-on**, not in the all-in price; travelers choose it at registration (Step 9b: sign-up and payment tracking).

**The existing Pricing Tiers "Add-ons" field** (per tier: name + quantity, no price; shown in the proposal PDF as "Add-ons: name × qty"): **proposal: replace it** with the trip-level Group extras. It cannot hold a price, a basis or required/optional, and it is per cabin type rather than per trip. Neither trip has any add-ons today (181: 0, 320: 0), so nothing needs migrating. When Group extras is built: hide the old Add-ons editor in the Pricing Tiers box, keep reading any old data so nothing is lost, and have the proposal PDF list Group extras instead. To be confirmed when the step is planned.

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

## CBGV logo coin (recorded 2026-10-05; not built)
Source file: `media-incoming/CB_GV Logo Coin.png` (1041×1042 PNG, transparent background; gold, coral and navy coin with the plane/arrow mark). CBGV's own brand asset, so the Virgin media rules do not apply; it stays out of any Virgin logo's space.
1. **Site icon / favicon: already set**, as `cropped-CB_GV-Favicon-White-BG.png` (Media ID 20, 512×512 on a white background). Optional swap to the transparent coin (a cropped 512×512 copy) at **Step 16 (final QA)**, after checking it reads at 16-32px in browser tabs and on light and dark backgrounds. A site setting (Appearance > Customize > Site Identity), not code.
2. **Small mark beside the wordmark in the new-design header**: **Step 12** (with the footer work), as part of the new template's header/footer. Small (about 28-32px), decorative (empty alt, the wordmark carries the name), linked with the wordmark to the home page.
3. **Faint stamp on the boarding pass**: a **Step 4 follow-up, done in Step 9** (Step 9 already revisits the pass, whose "Claim your seat" may then point at the price board). Low-opacity, behind the pass text, never reducing text contrast below WCAG AA; hidden from screen readers.
4. **In the footer**: **Step 12** (footer), beside the brand name; decorative.
Upload the coin to the Media Library (alt text "Checked Bags & Good Vibes logo" where it is not decorative) when the first of these is built, and keep the original.

## Go-live checklist (Step 17; list started 2026-10-04)
- **Media: source is the First Mates marketing toolkit (FirstMates.com); use unaltered** (web compression only, no cropping, editing or logos on the asset). Every Virgin image and video, including the Terminal V hero video, comes from there. Each asset is listed in `docs/media-sources.md`. (This replaces the earlier "usage rights unconfirmed" wording.)
- **Photo frames never hide Virgin's logo or branding and never change what the photo shows.** Where the subject is not centred, set a per-image focus point (`object-position`) so the crop keeps it in view. Applied from Step 4 on.
- **Virgin brand rules** (Virgin "With Love From" brand guidelines, Nov 2024): the Virgin Voyages logo appears only in Virgin red from the official toolkit file and is never recoloured; no recreated Virgin campaign elements (the "with Love from" script lockup, promo stamps, Virgin campaign fonts); "With love from..." is never used as CBGV copy; our own headings and design stay in CBGV's brand. Checked at each step that touches media and again at final QA.
- **Offer stamps:** a toolkit image carrying an offer stamp is used only while that offer is valid; each such image is listed in `docs/media-sources.md` with its offer end date and removed when the offer ends. Check the list at go-live.
- **Caching:** Cloudflare must not cache trip page HTML (no APO / cache-everything rule) unless a token-free purge path is designed first; the status board's "paid deposits" number relies on SiteGround's purge-on-save (Step 5).
- **CBGV Group Experience Fee, business checks before the fee shows publicly (added 2026-10-08, not legal advice):** (1) InteleTravel's agent terms allow a separate third-party (CBGV) Group Experience Fee to be shown alongside bookings made through InteleTravel; (2) whether CBGV needs Seller-of-Travel registration in the states it sells to (e.g. Florida, California); (3) how and where CBGV collects the fee (the site takes no payments today).
- **Compliance items before go-live (added 2026-10-08):** (1) **InteleTravel compliance confirmation** (in writing) for how the CBGV Group Experience Fee and CBGV's role are presented; (2) **Seller of Travel numbers and wording from InteleTravel**, shown where InteleTravel says they must appear; (3) the **signed CBGV-advisor agreement** (CBGV and the travel advisor); (4) the **"Who does what" disclosure live on every trip page**. Also: no page, proposal or email says "CBGV books your cabin". Also: CBGV never collects travel funds (the Payment page bills the CBGV fee only; see "Member Payment page and the Step 8b fee").
- Further items are added here as steps ship.

## Open decisions and unverified claims (running list)
- **Resolved 2026-10-08: child age cutoff for the Group Experience Fee.** LaDon confirmed children are 17 and under (18 and over is an adult); set as "Children are under 18 years old" on Settings > Group Experience Fees, and the traveler intake states it.
- **Tidy-up (later, not scheduled):** gallery box, "Add photos" opens the Media Library in a mode where a plain click replaces the selection and **choosing several needs Ctrl+click** (Cmd+click on a Mac). Either switch the chooser to add-on-click mode or say so in the help text ("hold Ctrl to choose several"). Found 2026-10-05 while setting trip 181's gallery.
- **Tidy-up (later, not scheduled):** gallery box, when the gallery already holds 10 photos, "Add photos" should say "The gallery is full (10 photos)" instead of "Only the first 0 were added" (today the button is disabled at 10, but the wording should still be clear if it is reached). Recorded 2026-10-05.
- **Before quoting a Royal Caribbean or Carnival trip:** change the admin and proposal wording from "Sailors" to "travelers" (the "# Sailors" column labels in the Pricing Tiers / Occupancy Points editor in `checkedbags-trips.php` and the proposal PDF table in `checkedbags-proposal-pdf.php`), matching the Step 5 decision that cruise wording says "Travelers". Left as is for now (decided 2026-10-05).
- **Text formatter, links containing ")":** a link address stops at its first ")", so `[x](https://en.wikipedia.org/wiki/Bimini_(island))` loses the last ")" and leaves it in the text. Safe (only http, https, mailto and tel links are ever made; `javascript:`, `data:` and others are dropped, tested in `tests/landing/test_step5.php`), but worth fixing in the formatter (`cbv_lp_inline`, Step 2) at a later step. Found 2026-10-05.
- Registration and price-breakdown items listed under Steps 8 and 9b.
- Trip-side editors for the remaining provider groups (columns, included cards, footnote, steps, own documents) arrive in later steps; the trip key-date editor ships with Step 3 revision 2.
