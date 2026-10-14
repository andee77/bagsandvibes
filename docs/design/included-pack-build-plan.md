# What's included + Pack list: build plan (before trip 181 goes live)

Proposed 2026-10-14, for owner approval. **Nothing built.** Mockups (static, review only): `docs/design/mockups/included-pack-mockup.html`, `included-pack-desktop.png`, `included-phone.jpg`, `pack-phone.jpg`. Look copied from the original mockup (`event-page-design-reference.html`, sections WHAT'S INCLUDED and PACK LIST).

## Placement and switches
- New design order: … How to book, Countdown, Travel documents, **What's included**, **Pack list**, Who does what. For trip 181: Gate 22 · What's included, Gate 23 · Pack list. Gate numbers continue automatically.
- Both use the trip's existing On/Off switches ("What's included", "Pack list"; on by default for cruises). Each part hides when empty, and a whole section hides when all its parts are empty.
- New design only. The members' page doesn't get these sections (say if you want them there too).

## 1. What's included (horizon-blue band)
Heading **"Every fare comes with extras"** (from the mockup). Lede: "What your all-in price covers, what {provider} includes with each fare, and what our group adds."

**a) In your all-in price** (cream strip with green ticks)
- **Built from the trip's own prices; nothing typed.** A part is listed only when **every price on the price board includes it** (a value above $0):
  - "Cruise fare, at our group rate", or "Cruise fare" when no group discount is recorded
  - "Taxes & fees"
  - "Prepaid gratuities"
  - "Voyage Protection"
  - "CBGV Group Experience Fee ($225 per traveler)", when the trip has a fee to bill
- **Trip 181 today:** all 7 price-board points carry fare, discount, taxes, gratuities and protection, plus the $225 fee, so all 5 show.
- Non-cruise trips say "Travel price" instead of "Cruise fare".
- If a part is on some prices but not all, it's left out. Proposed; the alternative is "(most cabins)".

**b) Included by the cruise line** (fare cards, mockup style)
- **The Virgin Voyages provider's What's included cards, already stored, verified lines only:**
  - Base, Essential, Premium: 1 line each
  - RockStar Quarters: 2 lines
  - Mega RockStar: 5 lines, including "Transfers & complimentary parking" and "Daily spa access"
- Card styles match the mockup: light, light, gold, then dark and wide for the suites.
- **Left out, as unverified (seed skip report):**
  - Wi-Fi tiers on every fare
  - Dining-reservation dates
  - $50 Sea Terrace credit (all three fares) and the $50 suite credits
  - $75 Premium bar tab
  - Priority pre-voyage support
  - VIP early boarding
  - 24/7 RockStar agents and room delivery
  - Outdoor terrace shower
- The mockup's lede "On every one: all restaurants, soda and water, group fitness, entertainment" is also **left out until verified**. The plan adds an optional provider field **"On every fare"**, empty for now, shown as a short line above the cards once filled with checked lines.
- A small line under the cards: "Wi-Fi, bar tabs, onboard credit and dining dates are left off until checked with {provider}." (Proposed; or no line.)

**c) The CBGV group program** (dark panel, two columns)
- **New per-trip field** (one line each) in a new trip box, "What's included & pack list".
- **Trip 181 draft (your list, to confirm):**
  - Sail-away toast on the top deck
  - Scarlet Night meetup and group photo
  - Private beach cabana day in Puerto Plata
  - Recovery brunch at The Wake
  - Group swag
  - Group chat before and during the trip
  - An on-trip CBGV host
- The catamaran is not mentioned.

## 2. Pack list (luggage tags)
Heading **"Pack List: Vibes Only"** and an optional gold **forecast chip** (per trip; trip 181: "FORECAST · LOW 80s °F · WARM & TROPICAL").

**"Pack this" tags: per trip.** A repeater of up to 6 tags. Each has a label ("TAG 01 · THEME NIGHTS"; the number is automatic), a title and one line per item. Items use the usual text format (**bold**, links).

**Trip 181 starting draft (the mockup's list, for you to edit):**
- **TAG 01 · Theme nights: Must-pack outfits**
  - **Scarlet Night (Day 4):** an all-red look, with a swimsuit underneath that can get wet.
  - **PJ Party (Night 1):** cute, comfy sleepwear you're happy to wear in public. ⚠ *Confirm this event is on your sailing.*
  - **80s Workout (optional):** neon or retro activewear for the throwback fitness classes. ⚠ *Confirm.*
- **TAG 02 · Days: Daywear & excursions**
  - 3–4 lightweight outfits: linen, sundresses, breathable tees, polos.
  - 2–3 swimsuits plus cover-ups for restaurants. Reworded: the mockup said "required"; Virgin's FAQ says it "encourage[s]" more than a swimsuit in restaurants.
  - Breathable gear and water shoes for Puerto Plata.
- **TAG 03 · Nights: Evening, smart casual**
  - 3–4 resort-casual looks: jumpsuits, sundresses, dark denim, chinos, linen button-downs.
  - A light layer for chilly A/C and windy top decks.
- **TAG 04 · Cabin kit: Cabin essentials**
  - Magnetic hooks for the cabin walls. ⚠ *Unverified: "cabin walls are metal" isn't on Virgin's site; keep as a tip, or drop.*
  - Waterproof phone pouch and dry bag.
  - Reusable water bottle (bring it empty: sealed drinks only in cans or cartons). Tied to Virgin's no-bottled-drinks rule.
  - Reef-safe sunscreen, polarized shades, wide-brim hat.

**"Leave at home": on the provider (Virgin Voyages), shown on every Virgin trip** (full-width dashed coral tag)
- A trip can add its own lines after the provider's.
- Each line stores its **source link and a "checked on" date (admin only)**, so the list can be re-checked. Virgin last updated its list on April 10, 2026.

| # | Line (public) | Source |
|---|---|---|
| 1 | Clothing irons (hair dryers, straighteners and curling irons are fine). | [Pre-Voyage Policies FAQ](https://www.virginvoyages.com/faq/before-you-sail/pre-voyage-policies) ("Clothing irons are a no go on board"); [Prohibited Items](https://www.virginvoyages.com/safety-security/prohibited-items) (heating elements; hair tools permitted) |
| 2 | Candles and incense. | Prohibited Items |
| 3 | Liquor and beer. Wine: up to two 750 mL bottles per cabin, in carry-on. | Prohibited Items; Pre-Voyage Policies FAQ |
| 4 | Drinks in bottles. Water and soft drinks only in sealed cans or cartons (up to 12, 12 oz or less, per person, carry-on). | Prohibited Items; Pre-Voyage Policies FAQ |
| 5 | CBD and marijuana products, even with a prescription; kratom. | Prohibited Items |
| 6 | Drones. | Prohibited Items; Pre-Voyage Policies FAQ |
| 7 | Fresh-cut or potted flowers. | [Onboard Policies FAQ](https://www.virginvoyages.com/faq/the-days-at-sea/onboard-policies) |
| 8 | Seashells, coral or any marine souvenirs (fake shells are fine). | Prohibited Items; Onboard Policies FAQ |
| 9 | Homemade or perishable food; coolers (except for medicines). | Prohibited Items |
| 10 | Extension cords and multi-plug power strips (only if the ship's electrician approves). | Prohibited Items |
| 11 | Satellite phones, Starlink or routers. | Prohibited Items |
| 12 | Knives with blades over 4 inches. | Prohibited Items |

**Flagged and left out (can't verify):**
- **Garment steamers:** not named on Virgin's pages. Possibly covered by "any appliance with a heating body or element".
- **"Formal wear: not required anywhere on board":** Virgin says "Dress Up. Dress Down. Just Dress." but doesn't say this outright.
- **"Towels: provided on board and at Bimini":** not on these pages.

Twelve lines is long. Fewer, e.g. the 6 most relevant to a vacation sailor (1, 3, 4, 5, 6, 8), with "Full list: Virgin's prohibited items" linked under them? Your choice.

## 3. Where the content lives (admin)
| Content | Where | New? |
|---|---|---|
| All-in parts | Computed from Pricing Tiers + the fee | No field |
| Cruise-line fare cards | Provider > What's included cards | Exists (seeded) |
| "On every fare" | Provider, new box (one line each), empty | New |
| CBGV group program | Trip, new box "What's included & pack list" | New |
| Pack tags + forecast chip | Same trip box (repeater, up to 6 tags) | New |
| Leave at home | Provider, new box (line + source link + checked date); trip may add lines | New |

## 4. Writes needed after the build (each with your OK)
- **Virgin Voyages provider:** the Leave-at-home lines you approve, with sources.
- **Trip 181:** the group-program list and pack tags you approve, plus the forecast chip.
- Each write gets a snapshot and diff, through the real boxes.

## 5. Tests and review
- **New test.** It checks:
  - parts appear only when every price has them;
  - the fee line follows the billable fee;
  - only verified card lines show;
  - each part and section hides when empty, and the switches work;
  - tag numbering;
  - Leave-at-home sources stay admin-only;
  - gate numbers;
  - wording (no InteleTravel, no "CBGV books your cabin", no catamaran, no children wording on Virgin trips).
- **Mutation run:** the same checks against the live files must fail.
- **Screenshots:** desktop and phone. Then admin manual and the standard deploy with your OK.

## Decisions needed
1. Order: Included, then Pack list, after Travel documents (as mocked), or Pack list elsewhere?
2. All-in parts: show only when every price includes the part (proposed)?
3. The "left off until checked" line under the cards: keep or drop?
4. The trip 181 group-program list: final wording?
5. The trip 181 pack tags: your edits (and PJ Party / 80s Workout / magnetic hooks)?
6. Leave at home: all 12 lines, or a shorter 6 plus a link to Virgin's full list?
7. Members' page: add these sections there too, or new design only?
