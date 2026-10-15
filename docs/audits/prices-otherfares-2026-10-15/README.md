# Trip 181 prices (Oct 15, 2026 invoices), other fares on request, fee ticket, empty band (2026-10-15)

## 1. Pricing Tiers: saved through the real box on 2026-10-15
Saved with a snapshot before and after: `~/logs/old/trip181-{before,after}-tiers-20261015-173115.json`. Only `cb_pricing_tiers` changed, and the stored value matches the dry run exactly.

Every price is for 2 people, priced per cabin. "Board" is the all-in price on the board: the invoice total plus the CBGV Group Experience Fee, 2 × $225 = $450.

| Before (7 tiers) | Invoice total | | After (8 tiers) | Fare | Tax | Grat | Prot | Disc | Invoice total | Board |
|---|---|---|---|---|---|---|---|---|---|---|
| The Insider | 2,552 | | The Insider (group Insider) | 2,598 | 402 | 200 | 158 | 750 | **2,608** | $3,058 |
| Insider Locked In (not on board) | 1,696 | | *removed* | | | | | | | |
| Sea View | 2,850 | | Sea View (group Sea View) | 2,676 | 402 | 200 | 162 | 778 | **2,662** | $3,112 |
| Sea View Locked In (not on board) | 1,896 | | *removed* | | | | | | | |
| | | | Limited View Sea Terrace (new; Sea Terraces) | 2,908 | 402 | 200 | 174 | 860 | **2,824** | $3,274 |
| Sea Terrace | 3,258 | | Sea Terrace (Sea Terraces; ★ Group favorite, highlighted) | 3,216 | 402 | 200 | 192 | 968 | **3,042** | $3,492 |
| Sea Terrace Locked In (not on board) | 2,324 | | *removed* | | | | | | | |
| | | | Central Sea Terrace (new; Sea Terraces) | 3,446 | 402 | 200 | 204 | 1,048 | **3,204** | $3,654 |
| Sea Terrace XL | 3,794 | | Sea Terrace XL (Sea Terraces) | 4,292 | 402 | 200 | 250 | 1,344 | **3,800** | $4,250 |
| | | | RockStar Quarters (new; Suites, one price only) | 5,348 | 402 | 200 | 454 | 0 | **6,404** | $6,854 |
| | | | Mega RockStar (new; Suites, one price only) | 9,398 | 402 | 200 | 798 | 0 | **10,798** | $11,248 |

**Checks:**
- All 8 cabin totals match the invoice table exactly.
- The board shows all 8 cabins: 6 in the Premium column, plus the two suites at one price each.
- What's included now shows the RockStar Quarters and Mega RockStar cards, because the board lists suites.

**Question for the owner:** how many people does each new cabin sleep (Limited View Sea Terrace, Central Sea Terrace, RockStar Quarters, Mega RockStar)? They are set to 2–2 for now. This only appears in the proposal PDF ("sleeps 2–2").

## 2. Code changes (NOT deployed; waiting for the owner's OK)
- **Other fares available on request** (any trip):
  - **Setting:** a new tick list in the trip's **Price board** box, offering the provider's fare columns. For trip 181 choose Essential and Base. This setting is saved after the deploy.
  - **Under the board:** "Prices shown are Premium fares. Essential and Base fares are also available. Ask us for a quote and we'll compare them for you. Some fares book outside our group block; your travel advisor will explain what that means for you." A **Request a quote →** button links to the Contact page.
  - **Fare names:** they come from the board's columns and the ticked fares, richest first. Ticked fares already on the board are ignored.
  - **What's included:** the ticked fares' cards appear, tagged "Available on request". A fare that is neither on the board nor ticked stays hidden.
- **The fee is back** as the 5th ticket, "CBGV Group Experience Fee", with "$225 per traveler" on a small line under the name.
- **Empty band above the footer:** removed. The band only appears when something of the old page is left to show.
- **Layout:**
  - Fare cards show three across; suites run full width under them, with their perks in two columns, as in the mockup.
  - Members' page: the five tickets fit on one row.
- **Tests:**
  - The What's included suite has 40 checks, all passing; against the live files 8 fail, so they catch the change.
  - Updated old checks:
    - the page-order checks no longer need the old-page band;
    - trip 181's prices are now $2,608 + $450 = $3,058, with 8 tiers;
    - the price board's stored value includes the new "other" field.
  - All 21 suites pass on the temp copy (`tests.txt`).

## Files
- `review.diff`: the code diff.
- `tests.txt`: test results.
- `full-page/`: the whole preview, with Essential and Base set in memory only. Desktop is in 6 parts and phone in 7.
- `member-included-desk.png`: What's included on the members' page.
