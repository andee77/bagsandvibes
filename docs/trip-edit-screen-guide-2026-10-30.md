# Trip edit screen: every box explained (staff manual)

Written 30 Oct 2026 from the site's code and live settings (read-only; nothing was changed). Replaces the 28 Oct draft: it now covers the **new members' trip page** (live since 30 Oct 2026).

Screen: Trips > (any trip). The trip screen uses the block editor. The boxes sit below the text area, in the order your admin account has saved, and are listed here in that order. "Sidebar" boxes appear in the right-hand settings panel.

**Where things show (short names used below)**
- **Public page (old):** the old public landing page.
- **Public page (new):** the new public landing design.
- **Members' page:** the new members' trip page that roster members and staff see at the trip's address.
- **Gate 07:** the trip browse list.
- **Gate 09:** the Payment page.
- **My Passport:** the member dashboard.
- **Feed:** the members' feed.
- **Proposal PDF:** made from the Proposals screen.
- **Roster export:** Export Roster to Excel.

## Which design is live

- **Public pages:**
  - **Master switch:** the new public landing design's master switch is **ON**.
  - **Per trip:** each trip chooses with its own **Use new landing design** checkbox (New Landing Design box). Today Trip 181 uses the **new** design, Trip 320 the **old** one, and CBGV TEST TRIP is private with no public page.
- **Members' page:** the **same new page for every trip**, whichever public design the trip uses. It doesn't depend on the "Use new landing design" checkbox.

---

## 1. Trip Details: every trip

**For:** the trip's core settings: workflow status, dates, size, reference prices, a client contact, the Gate 12 quote, and the roster.

**Fields (★ = most important):**

**★ Status:**
- **Requested:** a member's Gate 12 request waiting for a quote.
- **Quoted:** the member sees the price, the notes and **Accept this quote** on Gate 12.
- **Accepted:** set automatically when the member accepts. The trip shows on Gate 09, Gate 11 and Gate 08, but **not** on Gate 07.
- **Active:** the live trip.
  - It's the **only** status listed on Gate 07 and in the Feed's upcoming-trips strip.
  - It also shows on Gates 09, 11 and 08.
  - **The first save as Active creates the trip's discussion board** (Gate 10), which powers **Discuss this trip**.
- **Completed:** Gate 08 Gallery only. The public page's status board hides.
- **Declined:** stays on the requester's Gate 12 card.

**Source:** Curated or Member-built. Member-built trips show "Your Request Details" on the members' page and are the only trips on Gate 12.

**★ Start date / End date:** used almost everywhere:
- **Members' page:** the hero dates, the boarding pass, the days-until stub, "N nights", the route day dates and the "We sail" key date.
- **Both public designs.**
- **Gate 07** card.
- **Proposal PDF, roster export** and search results.

**★ Capacity (spots):**
- Blocks joining once full (0 = no limit).
- Spots left show on the Gate 07 card, the members' page (Who's going: "N spots open") and the Feed.
- It also feeds the public page's status board.

**★ Minimum group size:**
- The members' page shows "This trip needs a few more people before it's confirmed" until it's reached (Gate 11 too).
- The public page's status board hides while this is 0 or blank.

**Travel price (reference only, never charged):**
- Shown on the Gate 07 card only when there are no Pricing Tiers or price range.
- Also used by the proposal PDF and the roster export.
- **Not** shown on the members' page or either public design.
- Overwritten automatically when a member accepts a Gate 12 quote.

**CBGV fee deposit per person** (labelled "Deposit per person" before 30 Oct 2026): ⚠ not a travel deposit. It's the first payment of the **CBGV Group Experience Fee** on Gate 09, used only when the payment mode is "Deposit + installments".

**Price range summary (low / high):** Gate 07 card only. Ignored once Pricing Tiers have prices.

**Photo gallery privacy:** Gate 08 only.

**Point Person (name, phone, email):** staff only (Internal Data Sheet PDF).

**Gate 12 quote (quoted travel price per person, quote / plan notes):** the requester's Gate 12 card.

**Roster:**
- **Remove** and **Add traveler** act immediately. Add traveler respects Capacity and can email the member.
- Per-traveler **Paid in Full / Insurance Waiver Received / CC Auth Received** save on Update and are staff-only. They also drive the members' page checklist:
  - **Insurance Waiver Received = Yes** → item 3 DONE.
  - **CC Auth Received = Yes** → item 4 DONE.
  - **Paid in Full = Yes** → item 5 DONE.
- Marking **both** "Received" fields Yes also hides that member's Trip Registration form.

**Fill in:** every trip: Status, Source, dates, Capacity, Minimum group size. Deposit only for deposit + installment trips. The rest only sometimes.

**Design:** both (the dates). The members' page uses dates, Capacity, Minimum group size and the roster flags.

## 2. Public Landing Page Content: every public trip

**For:** the words on the public trip page.

**Fields:**
- **★ Tagline:** the hero line on **both public designs**, and the search/social description when Yoast is blank. Not on the members' page.
- **Show Itinerary on Public Landing Page:**
  - **Old design:** the itinerary table.
  - **New design:** the Route section and the boarding pass FROM/TO.
  - Doesn't affect the members' page, which always shows the route when there's an itinerary.
- **Highlights:** **old design only**. The new public design hides them and uses What's included instead.
- **Disclaimer / fine print:** the old page's footer. On the new design it shows in a cream band at the bottom.

**Fill in:** the Tagline on every public trip. Highlights only on old-design trips.

**Design:** public pages only (both).

## 3. Landing Page Settings (new design + members' page): every trip that has a provider

**For:** what kind of event the trip is, which provider it uses, and which sections show.

**Fields:**
- **Event type:** Automatic (from Trip Type) by default. It sets the words used (cabin/room, "Vessel/Property/Venue", "Departs/Check-in"), the sections on by default, and **which standard Group Experience Fee applies**.
- **★ Provider:** an entry from the Provider Library. Despite "(new design)" in the box name, it feeds the **members' page** too:
  - the cruise-line tag and the boarding pass "Cruise line";
  - **key dates** (02);
  - the fare cards in What's included (04);
  - **Leave at home** and the full prohibited-items link (05);
  - the fine print (05).

  It also sets the adults-only default and fills the cruise company on the Trip Registration form.
- **Accommodation word:** optional override.
- **Sections table:** Default / On / Off. The **Key dates, What's included, Pack list and Fine print** switches also apply to the members' page.

**Fill in:** Provider on every trip that has one, members' page included. Event type only if the automatic one is wrong.

**Design:** new public design, plus the members' page, Gate 09 (standard fee), the proposal PDF and the Trip Registration.

## 4. Key dates for this trip (new design + members' page): sometimes

**For:** adjusts the provider's deadlines for this trip, removes ones that don't apply, or adds trip-only dates.

**Shows:**
- The new public design's Countdown.
- The **members' page, 02 · Key dates** (a dotted timeline, ending with "We sail from {port}").
- The final-payment date on the price board and in the fee refund sentence.

It's not linked to Gate 09's "Next Travel Payment due date".

**Fields (per row):** key, title, days before start **or** a fixed date, dot colour, date text, description, "Not applicable on this trip", "Token only".

**Fill in:** only when the trip differs from the provider, or has extra dates. A trip with **no provider** needs its own rows to get any key dates at all.

**Design:** new public design and the members' page.

## 5. New Landing Design (sidebar, admins only): once per trip

**For:** the per-trip switch that moves **public visitors** to the new design. It shows the master switch status, a plain sentence saying what the public sees, and a "Preview new design" link.

**Fill in:** tick it once the new-design boxes are ready. It only works with **Create Public Landing Page** ticked. It has **no effect on the members' page.**

## 6. Day-by-Day Itinerary: every trip

**For:** the schedule, one row per day.

**Fields (per row):** Day #, Date, Port/place, **Code** (e.g. MIA), Country, Type (Embarkation, Arrival, Departure, At Sea, Disembarkation), Time, Dock/Tender.

**Shows:**
- **Members' page:**
  - **03 · The route:** big day numbers, port, times, dock/tender, code.
  - The hero's "Miami round trip".
  - The boarding pass **FROM/TO codes** (first and last Code).
- **Old public page:** "Departs from", plus the table when Show Itinerary is ticked.
- **New public design:** the Route cards and the boarding pass route, when Show Itinerary is ticked.
- **Proposal PDF** and search data.

**Fill in:** every trip, including the Code column.

**Design:** both public designs and the members' page.

## 7. Cover Photo & Itinerary PDF (sidebar): every trip / sometimes

**Fields:**

**★ Cover photo:**
- **Members' page hero:** used when the Hero & boarding pass box has no poster.
- **My Passport** trip cards.
- **Old public page** hero, and **new public** hero when there's no poster.
- **Social sharing** image when Yoast has none.

⚠ **Members on the trip can change it themselves from My Passport.**

**Itinerary PDF:**
- **Members' page:** a **Download Full Itinerary PDF** button in 03 · The route, and on the rules screen before acceptance.
- **Public pages:** a button on the old page, and in the new design's bottom band.

**Design:** both.

## 8. Pricing Tiers: every public trip

**For:** cabin/room categories with real prices per occupancy. **The main price source.**

**Fields:**
- **Per tier:** name, sleeps, short description, and the new-design board tags (Group, Badge, Note, Highlight this row, One price only).
- **Per price point:** travelers, Fare, Taxes & Fees, Gratuities, Insurance, Discount, priced per cabin, and the **Price board column**.
- **Add-ons.**

**Shows:**
- **Members' page, 04 · What's included:** the "Included" ticket cards (e.g. Cruise fare, Taxes & fees, Prepaid gratuities, Voyage Protection), from what every price on the board includes.
- **Gate 07:** the price range.
- **Old public page:** the cards.
- **New public design:** the price board.
- **Proposal PDF** and search data.

Prices are display only; CBGV never bills travel prices.

**Fill in:** every public trip. For the new public design, also the price board column on each price point.

**Design:** both public designs and the members' page.

## 9. Packing Notes (members' page, only when there's no pack list): optional, legacy

(Called "Gate 07 — Packing Notes" before 30 Oct 2026.)

**For:** a free-text packing note.

**Shows:**
- **Members' page, 05 · What to pack, but only when the trip has no pack list** (What's included & pack list box). Otherwise it's hidden.
- Despite the name, it's **not** on Gate 07.

**Fill in:** only on trips that won't get a pack list. **The pack list wins.**

**Design:** members' page only.

## 10. Gate 09 — Payment Settings (sidebar): every trip members pay for

**Fields:**
- **Payment mode:** full payment only, or deposit + installments.
- **Number of installments.**
- **Approved extras ($ per member):** added to the fee.
- **Next Travel Payment due date:** display only.

**Shows:** Gate 09 (trips that are Active or Accepted). The fee total feeds the **members' page checklist item 5** ("$X due · $Y paid", Pay now).

⚠ The box's note "full price is set in Trip Details" is misleading: the travel price is never charged.

**Design:** neither (payments).

## 11. Gate 12 — Member Request Details: leave alone

**For:** a read-only view of a member's "Build Your Own Trip" answers.

**Shows:** **"Your Request Details"** at the bottom of the members' page (member-built trips, folded away), the roster export, the proposal PDF and the staff "New Vacation Request" email.

## 12. Export Roster (sidebar): use when needed

**For:** **Export Roster to Excel** (staff only) and a passport-renewal warning. Store the file carefully, as it contains personal data, including children's names and dates of birth.

## 13. Trip Code & Visibility (sidebar): every trip, check carefully

**Fields:**

**★ Trip code:** filled in automatically if blank (CBV-YEAR-XXX). Used for:
- the `/join/?trip=CODE` link and QR code;
- **members' page** hero tag and boarding pass;
- the new public boarding pass;
- the roster export file name.

**★ Visibility: Public / Private.** Private hides the trip from Gate 07, stops the join code, hides the QR code and **switches the public page off**.

**★ Create Public Landing Page:** without it, visitors who aren't on the trip see a sign-in or no-access message (see "What each visitor sees" below).

**Public join QR:** right-click to save (public trips only).

**Design:** both. It decides whether any public page exists.

## 14. Trip Agreement: every trip, before members join

**For:** the trip's binding rules text, with a version number.

**Shows:**
- **Members' page:**
  - Checklist item 1.
  - The rules prompt until it's accepted.
  - **Rules for this trip** (folded) afterwards.
- **Gate 11** "Rules for Your Trips". ⚠ The box's help text says "Gate 12".

⚠ **Any change makes everyone on the trip accept again** before they see sections 02–06. If the text is blank, members just click Continue.

**Design:** members' page and Gate 11.

## 15. Internal Notes (Admin Only): sometimes

**Fields:** Vendor Contacts, Margin / Profit Notes, Coordinator Checklist, What's Needed From the Party, Notes. Admins only.

**Shows:** the Internal Data Sheet PDF only. Never shown to clients.

## 16. Yoast SEO: sometimes

**For:** the search title, meta description and social title, description and image.

- Anything typed here wins. Blank → the tagline, dates and cover photo are used (only with the public page on).
- Trips without a public page are hidden from search engines.
- Not on the members' page.

## 17. Featured moment (new design): sometimes

**For:** one highlighted moment (e.g. a theme night).

**Fields:** **Title** (required), day, text, note card, 2 photos, background colour.

**Shows:** **new public design only.** Not on the members' page.

## 18. Gallery (new design): recommended for public trips

**For:** up to 10 captioned photos. **Photos are required**: without them the section doesn't show.

**Shows:** **new public design only.** Not on the members' page.

## 19. Group Experience Fee (charged on Gate 09, all trips; admins only): every trip, choose on purpose

**Fields:**
- **Use the standard fee** (default).
- **Use a different fee for this trip:** amount + basis.
- **No Group Experience Fee.**

⚠ **It controls billing for every trip, not just the new design:**
- A fee set for this trip is **charged on Gate 09 straight away**.
- The standard fee is charged only once "Group Experience Fee is live" is on.
- "No fee" is never charged.

**Shows:**
- **Gate 09** billing.
- **Members' page:**
  - **Checklist item 5:** DONE when paid in full, or "Nothing due" when there's no fee.
  - The **"CBGV Group Experience Fee" ticket** in 04.
- The new public price board and refund note.
- The proposal PDF, once the switch is on.

**Today:** Trip 320 is $150 per traveler and Trip 181 is $225 per traveler. Both are billable now.

## 20. Hero & boarding pass (new design + members' page): every trip with a ship or venue

**Fields:** hero video, smaller hero video, **poster picture**, picture focus, **vessel / venue name**.

**Shows:**
- **Poster picture:** the **members' page hero** (else the Cover Photo), and the new public hero.
- **Vessel / venue name:** the **members' page boarding pass** (head and "Vessel"/"Ship" cell), the new public boarding pass, and intro facts.
- **Videos:** new public design only.

**Fill in:** a poster (or rely on the Cover Photo) and the vessel/venue name.

## 21. Status board & intro (new design): every new-design public trip

**Fields:** **Travelers booked** (typed by hand), the intro heading, text, fact chips, photo and caption.

**Shows:** **new public design only.** Not on the members' page, whose crew count comes from the real roster.

- The board hides while Minimum group size is 0 or blank.
- Below the minimum it shows only the chip.
- ⚠ Its help text still describes the older behaviour.

## 22. Route days (new design): optional

**For:** extras (titles, text, stickers, photos) for the **new public** design's route cards.

**Shows:** new public design only.
- The members' page route uses the itinerary only.
- If the Route section is switched **Off**, the new public page shows no itinerary.

## 23. Price board (new design): optional extras

**Fields:** header note, 2 photos, "Other fares available on request".

**Shows:** new public design only. The prices themselves come from Pricing Tiers.

## 24. Price notes (current page and Gate 07): sometimes

**Fields:**
- **Pricing note:** shown under the **old** page's prices. ⚠ **Not on the new design.** Use the Price board header note instead.
- **Gate 07 card note:** one line on the trip's Gate 07 card.

**Design:** pricing note old only; card note on Gate 07 for every trip.

## 25. What's included & pack list (new design + members' page): every trip

**Fields:**
- **★ The CBGV group program** (one item per line), note under the program.
- Pack list forecast chip, **pack tags** (up to 6: category, title, items).
- Extra "Leave at home" lines.

**Shows:**
- **Members' page:**
  - **04 · What's included:** "The CBGV group program" list and note.
  - **05 · What to pack:**
    - the forecast chip;
    - pack cards with red, gold, teal and navy stripes;
    - **Leave at home**, which combines the provider's lines with this box's extra lines.
- **New public design:** What's included and Pack list, where it replaces the old Highlights.

**Fill in:** the group program on every trip. Pack tags and forecast when wanted.

## Sidebar: Adults only (18+): leave on "Same as the provider" unless this trip differs

**Field:** Same as the provider / Yes, adults only / No, children can travel.

**Shows:**
- **Adults-only trips:** no **Children** section on the Trip Registration (members' page). That also changes the party Gate 09 bills for.
- **Also hides** "Children pay half" lines and children wording in proposal PDFs.

## Sidebar: Trip updates (shown on members' Feed): sometimes

**Fields:** up to 5 updates, each up to 300 characters, each with a "Show until" date.

**Shows:** the top of the **Feed** for this trip's members, **and the members' page** under the checklist ("Trip update"), until the date passes.

---

## The members' trip page: which box feeds each section

| Section | Fed by |
|---|---|
| **Hero** (photo, tags, title, dates, "round trip · N nights", "Welcome aboard") | Photo: Hero & boarding pass **poster**, else **Cover Photo**. Tags: Trip Type, **Provider** (Landing Page Settings), **Trip code**. Dates: **Trip Details**. Route words: **Itinerary** |
| **Boarding pass** | FROM/TO: **Itinerary** Code column. Cruise line: **Provider**. Ship: **Vessel / venue name** (Hero box). Labels: **Event type**. Dates: **Trip Details**. Trip code. Passenger: the member's name |
| **Days-until stub** | **Trip Details** start / end dates |
| **Your next step** | The first unfinished checklist item |
| **01 · Checklist** | 1 **Trip Agreement** · 2 the member's **Trip Registration** · 3 roster **Insurance Waiver Received** (or the member chose insurance) · 4 roster **CC Auth Received** · 5 **Group Experience Fee** + **Gate 09 Payment Settings** + payments (+ roster **Paid in Full**) · 6 the member's **Schedule Appointment** request on Gate 09 |
| **Trip update** | **Trip updates** sidebar box |
| **02 · Key dates** | **Provider** key dates + **Key dates for this trip** + Trip Details start date. Sections switch "Countdown" |
| **03 · The route** | **Day-by-Day Itinerary** (+ **Itinerary PDF** button) |
| **04 · What's included** | Tickets: **Pricing Tiers** + **Group Experience Fee**. Group program: **What's included & pack list**. Fare cards: **Provider** |
| **05 · What to pack** | **What's included & pack list** (pack tags, forecast, extra leave-at-home) + **Provider** (leave at home, prohibited-items link, fine print) · **Gate 07 Packing Notes** only when there are no pack tags |
| **06 · Who's going** | The **roster** (member profile photos or initials, first name + last initial) · spots left from **Capacity** · minimum notice from **Minimum group size** · **Discuss this trip** once the trip is **Active** (board created) · Copy my invite link |
| **Travel details** | The member's own Trip Registration (cruise company and dates filled from the **Provider** and **Trip Details**) |
| **Rules for this trip** | **Trip Agreement** |
| **Your Request Details** | **Gate 12 — Member Request Details** (member-built trips only) |
| **Who does what** | Settings > How to Book & Disclosure (site-wide) |

**Not on the members' page:** Tagline, Highlights, Featured moment, Gallery, Status board & intro, Route days, Price board, Price notes, Travel price, Internal Notes, Yoast.

## Launch checklist (minimum, in order)

1. **Title**, and the **Trip Type** (sidebar).
2. **Trip Details:** start and end dates, Capacity, Minimum group size. Leave Status for step 11.
3. **Day-by-Day Itinerary**, with the Code column.
4. **Pricing Tiers** with prices (and a price board column for the new public design).
5. **Group Experience Fee:** choose on purpose. Then **Gate 09 — Payment Settings:** payment mode and installments.
6. **Landing Page Settings → Provider** (if there is one), and **Hero & boarding pass → vessel/venue name** + poster.
7. **Cover Photo.**
8. **What's included & pack list:** the group program (+ pack tags).
9. **Trip Agreement** text.
10. **Trip Code & Visibility:** Public, trip code, **Create Public Landing Page**. Then **Public Landing Page Content → Tagline**. For the new public design, also: Gallery, intro, and tick **Use new landing design**.
11. **Trip Details → Status = Active** and **Update.** This lists the trip on Gate 07 and the Feed and creates its discussion board.

## Overlaps and which one wins

| Overlap | Winner |
|---|---|
| Hero picture: Hero **poster** vs **Cover Photo** | Poster on the members' page and the new public page. The Cover Photo is the fallback, and is still used on My Passport and the old page |
| Price: Travel price vs price range vs Gate 12 quote vs **Pricing Tiers** | **Pricing Tiers** win everywhere they exist. An accepted quote overwrites the Travel price |
| Pricing note (old) vs **Price board header note** (new) | Each design uses its own. The pricing note doesn't show on the new design |
| Highlights (old) vs **What's included** (new) | New public design: What's included. Old page: Highlights. Members' page: What's included |
| Gate 07 Packing Notes vs **pack list** | **Pack list.** Packing Notes show only when there are no pack tags |
| Show Itinerary tick vs members' page route | The tick only affects public pages. The members' page always shows the itinerary |
| Route days extras vs Itinerary | The **Itinerary** is the source. Route days only decorate the new public route |
| Status board "Travelers booked" vs real roster | Public board: the typed number. Members' page: the real roster count |
| Key dates box vs provider key dates | **Key dates for this trip** overrides the provider row with the same key, and adds rows |
| Trip Details "Deposit" vs a travel deposit | It's the **CBGV fee** deposit (Gate 09), never a travel deposit |
| Key dates vs Gate 09 "Next Travel Payment due date" | Separate fields; they don't update each other |

## What each visitor sees at /trip/[trip-name]/

| Visitor | What they see |
|---|---|
| **Logged out** | **Create Public Landing Page ticked:** the public page, in the **new design** if the trip's "Use new landing design" is ticked, otherwise the **old design**. **Not ticked, or Private:** "Please sign in to see trip details", plus Who does what |
| **Logged in, not on the trip** | The same public page as above when it's on. Otherwise: "You don't have access to this trip's details yet — join it from All Planned Vacations first", plus Who does what |
| **On the trip, rules not yet accepted** | The **members' page**, shortened: hero, boarding pass, Your next step ("Accept the trip rules"), the checklist, the Itinerary PDF button, the **rules prompt**, their Trip Registration, and Who does what |
| **On the trip, rules accepted** | The **full members' page**: hero → pass → next step → 01 checklist and trip update → 02 key dates → 03 route → 04 what's included → 05 pack list and fine print → 06 who's going → Trip Registration, rules, who does what, request details (member-built), questions. Sections with no data are left out |
| **Staff (admin not on the roster)** | The members' page **without personal parts**: the passenger reads "Staff view". No next step, no checklist, no Trip Registration, no invite button. Sections 02–06 show without the rules step. To see the new public design, add `?preview=new` to the address |

## Help text fixed on 30 Oct 2026

These six misleading labels and notes on the trip edit screen were corrected (commit f115ab2):
1. **Trip Agreement:** now says the rules show on **Gate 11** (it said Gate 12).
2. **Status board & intro:** the "Travelers booked" help now describes the current behaviour (only the chip below the minimum; hidden while the minimum is 0 or blank).
3. **Gate 09 — Payment Settings:** now says members pay only the CBGV fee plus approved extras, and the travel price is never charged.
4. **Box names:** Landing Page Settings, Key dates for this trip and Hero & boarding pass now say **(new design + members' page)**. The fee box is **Group Experience Fee (charged on Gate 09, all trips)**, and its description says a trip's own fee is charged on Gate 09 straight away.
5. **Packing Notes (members' page, only when there's no pack list)**, with a note under the name.
6. **Trip Details:** **CBGV fee deposit per person ($)**, with a note that it's not a travel deposit.
