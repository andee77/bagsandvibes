# Virgin Voyages provider (seed revision 4) + switch trip 181 + trash Test Provider: plan

Proposed 2026-10-14, for owner approval. Nothing built or written. Source: `virgin-voyages-seed-text-rev4.md` plus the owner's decisions of 2026-10-14 (recorded in `event-page-plan.md`).

## 1. What the seed writes (one new provider, "Virgin Voyages", published)

| Group | Written | Left out |
|---|---|---|
| **Adults only (18+)** | Ticked | — |
| **Price column names** | Base, Essential, Premium (slot 4 blank) | — |
| **What's included cards** (5) | Base: "Non-refundable, no changes". Essential: "Date & cabin changes; cancel for a Future Voyage Certificate". Premium: "Date & cabin changes; transfer your booking to someone else". RockStar Quarters: "Curated in-room bar, first round on Virgin" and "Members-only Richard's Rooftop". Mega RockStar: "Everything in RockStar, plus:", "Bottomless in-room bar + daily bar tab", "Your own dedicated Mega RockStar agent", "Transfers & complimentary parking", "Daily spa access". Labels, titles, sublines and card styles exactly as in revision 4 | 19 unverified lines (Wi-Fi tiers, dining dates, $50 credits, $75 bar tab, priority support, VIP boarding, 24/7 room delivery, terrace shower). One-line cards are fine (owner) |
| **How-to-book steps** | **None** (owner). Virgin trips use the site's 4 steps (Settings > How to Book & Disclosure) | Revision 4's 3 old steps (they said "CBGV books your cabin… InteleTravel") |
| **Key dates** (4) | `final_payment` 120 days before ("Final payment due", description "{days:final_payment} days out."); `date_change_deadline` 46 ("Last day to change dates or cabins"); `funds_final` 45 ("Payments become final"); `name_change_cutoff` 2 ("Name changes and transfers close"). Dot colours and timeline flags as in revision 4 | The "Saved cards are charged automatically" sentence; dining, shore_things, checkin, health_check and the 4 dining_* rows |
| **Travel documents** (4) | **Cancellation policy** (7 bullets, last reviewed 2026-10-03); **Payment plans & deadlines** (3 bullets: final payment due, pay-later plans "subject to approval", booking after the final-payment date); **ID required for boarding** (2 bullets); **Accessibility, upgrades & changes** (2 bullets: **"Accessible cabins are limited. Ask your travel advisor to check availability when you book."** (owner's wording) + the name/date-change bullet). Last reviewed 2026-10-02 except Cancellation | 7 unverified bullets (auto-charge, Apple Pay/PayPal, skip the auto-charge, protection before final payment, passport details must match, $250 hold, egg chair, Level Upgrade); the Voyage Protection document |
| **Included footnote** | Blank | — |

Checks the script runs before writing:
- No "CBGV books your cabin" and no "InteleTravel" anywhere in the text.
- No `[UNVERIFIED]` marker is stored.
- No children wording.
- Every `{date:…}`/`{days:…}` token points to a written key date.

**Today, the What's included cards, key dates and documents are stored but not shown on the page.** They show when those sections are built (go-live R4 key dates and R5 documents next; cards in Step 10).

## 2. Steps (each with your OK; dry run first)

1. **Dry run:** the script prints exactly what it would write, plus the skip report. Nothing is saved. You review.
2. **Write:** creates the provider. Readback check: its stored content matches the dry run line for line.
3. **Switch trip 181** to Virgin Voyages through the trip's own Provider box. Snapshot and diff afterwards: only the provider ID changes.
   - Trip 181's Adults only must read "Same as the provider (adults only)".
   - Price board column names stay Base / Essential / Premium (same as Test Provider), so the board is unchanged. Checked by rendering the preview before and after.
   - How to book on 181 changes from Test Provider's 2 placeholder steps to your 4 steps ("Four steps to the gangway").
4. **Trash Test Provider (402)** (trash, not deleted), after checking no trip uses it. Helmets has no provider.
5. **Live checks:** 181's preview, public page and members' page, and Gate 07, rendered. Adults-only and price-board tests re-run.

## 3. Tests

New `test_virgin_seed.php`. It checks:
- The stored provider matches revision 4 with your decisions, word for word.
- The skip report counts: 19 card lines, 7 document bullets, 8 key-date rows, 1 sentence.
- Adults only is ticked.
- Banned wording is absent.
- Trip 181 inherits the provider (columns, key dates, adults only).
- 181's price board is unchanged.

Existing suites re-run.

## Open point

"Transfers & complimentary parking" (Mega RockStar) is your earlier wording. The design reference only said "Free parking at PortMiami Terminal V". Keep it as is (unflagged in revision 4), or mark it unverified so it isn't written?
