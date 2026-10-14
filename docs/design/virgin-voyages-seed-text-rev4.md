# Virgin Voyages: proposed Provider Library seed, REVISION 4 (NOT written anywhere)

Source: `docs/design/event-page-design-reference.html` (approved 2026-10-02) plus your reviews of 2026-10-03 (including your answers A-H) and the revision 4 marks below. Text is the reference's wording except where your review replaced it. Every date from the reference's 2027 sailing is a `{date:key}` token (see Key dates), so the entry works for any Virgin sailing. Tokens print as "October 10, 2027".

## Seeding rule (decided: your answer B)
**The seed writes ONLY verified lines and prints a report of everything it skipped.** `[UNVERIFIED]` is a review marker, never stored. A line (card line or document bullet) carrying a marker is skipped whole; a key-date row carrying a marker is skipped whole, except where only its description sentence is marked (then the date is kept and that sentence is left out). Skipped lines are listed in "Skip report" at the end, which is what the script would print. Skipped lines can be added later, one by one, once verified.

Each document gets a "last reviewed" date: Cancellation policy 2026-10-03 (corrected text from your review); the other documents 2026-10-02 (copied from the approved reference). That date means "copied from a source", not "verified with Virgin".

Provider title: **Virgin Voyages**   Status: published   Included footnote: (blank)

---------------------------------------------------------------------
## 1. Price column names
Base | Essential | Premium | (slot 4 blank)

---------------------------------------------------------------------
## 2. "What's included" cards (5)
Revision 4: Premium "Priority pre-voyage support" is now marked UNVERIFIED.
"Tips & Voyage Protection included" is removed from every card that had it. Changes in this revision: card 2 and 3 change-lines reworded (D); suite $50 credits, VIP early boarding, 24/7 room delivery, terrace shower and suite Wi-Fi counts now marked (E); "Daily spa access" added to Mega RockStar (E, confirmed in Virgin's CMI brochure, so it is verified and seeded).

### Card 1: light
label: FARE | title: Base | subline: INSIDER · SEA VIEW · SEA TERRACE
```
Basic Wi-Fi, 1 device                                  [UNVERIFIED: Wi-Fi tier]
Dining reservations open {date:dining_base}           [UNVERIFIED: dining date]
**Sea Terrace cabins add $50 onboard credit**          [UNVERIFIED: $50 Sea Terrace credit]
Non-refundable, no changes
```
### Card 2: light
label: FARE | title: Essential | subline: INSIDER · SEA VIEW · SEA TERRACE
```
Faster Wi-Fi with calling, 1 device                    [UNVERIFIED: Wi-Fi tier]
Dining reservations open {date:dining_essential}      [UNVERIFIED: dining date]
**Sea Terrace cabins add $50 onboard credit**          [UNVERIFIED: $50 Sea Terrace credit]
Date & cabin changes; cancel for a Future Voyage Certificate
```
### Card 3: gold (featured)
label: FARE · MOST PERKS | title: Premium | subline: INSIDER · SEA VIEW · SEA TERRACE
```
Premium Wi-Fi with streaming, 2 devices                [UNVERIFIED: Wi-Fi tier]
$75 premium bar tab per sailor                         [UNVERIFIED: $75 bar tab]
Dining reservations open {date:dining_premium}        [UNVERIFIED: dining date]
Priority pre-voyage support                            [UNVERIFIED: priority pre-voyage support]
**Sea Terrace cabins add $50 onboard credit**          [UNVERIFIED: $50 Sea Terrace credit]
Date & cabin changes; transfer your booking to someone else
```
### Card 4: dark, WIDE
label: SUITES | title: RockStar Quarters | subline: SERIOUSLY · BRILLIANT · CHEEKY CORNER · SWEET AFT
```
VIP early boarding                                     [UNVERIFIED: VIP early boarding]
First to book: dining & experiences open {date:dining_rockstar}   [UNVERIFIED: dining date]
Curated in-room bar, first round on Virgin
Members-only Richard's Rooftop
24/7 RockStar Agents & room delivery                   [UNVERIFIED: 24/7 room delivery]
$50 onboard credit                                     [UNVERIFIED: suite $50 credit]
Wi-Fi with calling, 1 device                           [UNVERIFIED: Wi-Fi tier / device count]
```
### Card 5: dark, WIDE
label: SUITES | title: Mega RockStar | subline: GORGEOUS · POSH · FAB · MASSIVE
```
Everything in RockStar, plus:
Bottomless in-room bar + daily bar tab
Your own dedicated Mega RockStar agent
Work-from-sea Wi-Fi, 2 devices, plus a Classic Wi-Fi pass          [UNVERIFIED: Wi-Fi tier / device count]
$50 onboard credit                                     [UNVERIFIED: suite $50 credit]
Transfers & complimentary parking
Outdoor terrace shower                                 [UNVERIFIED: outdoor terrace shower]
Daily spa access                                       (verified: Virgin's CMI brochure)
```
Note: the parking line is the wording you chose earlier ("Transfers & complimentary parking"). The reference only said "Free parking at PortMiami Terminal V"; "Transfers" is not in the reference.

---------------------------------------------------------------------
## 3. How-to-book steps (3), unchanged

**01: Register for the trip**
```
Tell us who's coming and which cabin you want, and tick the boxes if you also need a flight or a hotel.
```
**02: Pay your deposit**
```
CBGV books your cabin inside our group through InteleTravel, our official booking partner, then Virgin Voyages emails you a secure payment link for your deposit. Pay in full or spread it out with a plan.
```
**03: You're cleared for boarding**
```
Log in anytime to see your trip, payments and updates in your boarding-pass wallet.
```

---------------------------------------------------------------------
## 4. Key dates (days before the trip's start date; dates shown for an Oct 25, 2027 start)

| key | title | days | dot | in timeline | date text | description |
|---|---|---|---|---|---|---|
| final_payment | Final payment due | 120 | coral | yes | (formatted date) = Jun 27 | {days:final_payment} days out. Saved cards are charged automatically. **[UNVERIFIED: second sentence only]** |
| dining | Dining opens | 120 | gold | yes | `{date:dining_rockstar} - {date:dining_base}` | Depends on your fare: RockStar {date:dining_rockstar}, Premium {date:dining_premium}, Essential {date:dining_essential}, Base {date:dining_base}. **[UNVERIFIED: dining dates]** |
| shore_things | Shore Things open | 119 | gold | yes | (formatted date) = Jun 28 | Book your port excursions. Paid in full when added. **[UNVERIFIED: shore_things 119]** |
| date_change_deadline | Last day to change dates or cabins | 46 | gold | yes | (formatted date) = Thu Sep 9 | (none) |
| funds_final | Payments become final | **45** | gold | yes | (formatted date) = Fri Sep 10 | (none) |
| checkin | Check-in & travel docs | 18 | gold | yes | (formatted date) = Oct 7 | Upload passports, pick arrival times and download the Virgin Sailor App. **[UNVERIFIED: checkin 18]** |
| name_change_cutoff | Name changes and transfers close | 2 | gold | yes | (formatted date) = Sat Oct 23 | (none) |
| health_check | Health check | 1 | gold | yes | (formatted date) = Oct 24 | One quick form the day before. **[UNVERIFIED: health_check 1]** |
| dining_rockstar | (token only) | 120 | | no | | **[UNVERIFIED]** |
| dining_premium | (token only) | 60 | | no | | **[UNVERIFIED]** |
| dining_essential | (token only) | 45 | | no | | **[UNVERIFIED]** |
| dining_base | (token only) | 15 | | no | | **[UNVERIFIED]** |

Changes: `funds_final` 44 to 45 (A); the final-payment description now uses `{days:final_payment}` instead of a typed 120 (G). Offsets checked for an Oct 25, 2027 start: 46 days = Thu Sep 9; 45 = Fri Sep 10; 2 = Sat Oct 23 (matches "48 hours before sailing").
**Left out (unchanged):** the reference's "Cleared for boarding" row (Terminal V, 5:00 pm); it belongs on each trip. `flex_pay_deadline` removed earlier.

---------------------------------------------------------------------
## 5. Travel documents (4)
Removed: the **Voyage Protection document ("Cruise protection, built in")**: unverified, waiting on Aon's plan document. Also removed earlier: "Miami hotels & flights" (ship/port specific).

### Cancellation policy   (last reviewed 2026-10-03: corrected text, replaces the reference's body)
```
- **Base fares are non-refundable** and can't be changed. Taxes and fees are refunded.
- The schedule below applies to Essential, Premium and RockStar fares:
- **More than 120 days out:** cancel within 7 days of booking for a full refund, deposit included. After that, payments beyond the deposit are refunded; the deposit is non-refundable.
- **120 to 46 days out:** no cash refund. Take a Future Voyage Certificate for what you've paid, or move to a new sail date, either within 1 year of the original sail date.
- **45 days or less:** no refund or credit. Essential fares can change co-travelers' names; Premium and RockStar fares can transfer the booking. Both up to 48 hours before sailing.
- Certificates are issued per sailor and can't be transferred. Taxes and fees are refunded to the card on file.
- If you have Voyage Protection and cancel for a covered reason, it may reimburse you.
```
(Last bullet reworded per H.)

### Payment plans & deadlines   (last reviewed 2026-10-02)
```
- **Final payment is due {date:final_payment}**, {days:final_payment} days before sailing.
- If a card is saved with Virgin, the balance is charged automatically that morning.   [UNVERIFIED: saved cards charged automatically]   Apple Pay and PayPal are not auto-charged, so pay those by the due date.   [UNVERIFIED: Apple Pay / PayPal]
- Pay in full any time before then to skip the auto-charge.   [UNVERIFIED: depends on the auto-charge claim]
- Pay-later plans may be available through Virgin's financing partner, subject to approval.
- Cruise protection must be added *before* final payment.   [UNVERIFIED: protection before final payment]
- Booking after {date:final_payment}? Full payment is due at booking.
```
Removed earlier: the "Flex Pay by Upgrade" bullet (APR range and book-by date) and the `flex_pay_deadline` key date. "Pay-later plans ..." is your replacement. Changed now: "120 days" is `{days:final_payment}` (G); "skip the auto-charge" marked (F).

### ID required for boarding   (last reviewed 2026-10-02; revision 4 marks the passport-details bullet)
```
- Bring a valid, government-issued passport. Virgin only accepts passports as ID for check-in.
- Your passport name, birth date and nationality must match your booking.   [UNVERIFIED: passport details must match booking]
- Complete online check-in in the Virgin app as early as you can.
- Linking a card at check-in places a temporary $250 hold, released after the voyage.   [UNVERIFIED: $250 hold]
```

### Accessibility, upgrades & changes   (renamed per C; last reviewed 2026-10-02; revision 4 marks the egg-chair and Level Upgrade bullets)
```
- **Need an accessible cabin?** Virgin has accessible cabins in several categories. Tell us early so we can hold the right one.
- **Want an egg chair instead of a hammock?** A few forward Sea Terraces have one. Ask us and we'll check which cabins.   [UNVERIFIED: egg chair]
- **Dreaming bigger?** Virgin's Level Upgrade lets you bid on a better cabin in the weeks before sailing.   [UNVERIFIED: Level Upgrade]
- Name changes depend on your fare: none on Base, co-travelers on Essential, everyone on Premium and RockStar (up to 48 hours before sailing). Date changes on Essential and up are allowed until 46 days out.
```

---------------------------------------------------------------------
## NOT in the seed (trip-level, entered per trip later)
Section headings and blurbs; the Included footnote (field exists, left blank); all-in pricing and its explanation; Valiant Lady / Terminal V / Miami / Puerto Plata / Bimini specifics.

---------------------------------------------------------------------
## Skip report (what the seed script would print)

**Card lines skipped: 19**
- Base: Basic Wi-Fi; Dining reservations open {date:dining_base}; Sea Terrace $50 credit (3)
- Essential: Faster Wi-Fi; Dining reservations open {date:dining_essential}; Sea Terrace $50 credit (3)
- Premium: Premium Wi-Fi; $75 premium bar tab; Dining reservations open {date:dining_premium}; Priority pre-voyage support; Sea Terrace $50 credit (5)
- RockStar Quarters: VIP early boarding; First to book dining; 24/7 RockStar Agents & room delivery; $50 onboard credit; Wi-Fi with calling (5)
- Mega RockStar: Work-from-sea Wi-Fi; $50 onboard credit; Outdoor terrace shower (3)

**Document bullets skipped: 7**
- Payment plans: saved-card auto-charge + Apple Pay/PayPal bullet; "Pay in full any time before then to skip the auto-charge"; "Cruise protection must be added before final payment" (3)
- ID required for boarding: passport name, birth date and nationality must match the booking; $250 card hold (2)
- Accessibility, upgrades & changes: egg-chair bullet; Level Upgrade bullet (2)

**Key-date rows skipped: 8** dining, shore_things, checkin, health_check, and the four dining_* token rows. **Key-date sentence skipped: 1** the "Saved cards are charged automatically." sentence on final_payment (the date itself is kept).

**What would actually be written:** all 5 cards (Base 1 line, Essential 1, Premium 1, RockStar 2, Mega RockStar 5 incl. "Daily spa access"), 3 how-to-book steps, 4 key dates (final_payment, date_change_deadline, funds_final, name_change_cutoff), and 4 documents (Cancellation 7 bullets, Payment plans 3, ID 2, Accessibility 2).

**Consequences to be aware of:** (1) the Base, Essential and Premium cards become very short (one line each) until their Wi-Fi/dining/credit/support lines are verified; (2) Mega RockStar's first line "Everything in RockStar, plus:" would refer to a card that has only two lines for now; (3) no `dining_*` dates exist, so any trip text using `{date:dining_*}` would print blank until they are verified and added; (4) the timeline shows only the four verified dates.
