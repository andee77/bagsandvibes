# Key dates (Countdown) + Travel documents: build plan (go-live R4 + R5)

Proposed 2026-10-14, for owner approval. **Nothing built.** Parts of plan Steps 11 (timeline) and 12 (travel documents), brought forward for trip 181's go-live.

## Already in place (no change needed)
- **Data:** the provider's key dates and documents. Virgin Voyages has 4 key dates and 4 documents.
- **Trip-side key-date box (Step 3b):** a trip can move, retitle or remove a provider date, or add its own.
- **Merge:** the trip's version wins, rows are sorted by date, and `{date:…}` / `{days:…}` tokens are filled in. Trip 181 resolves to Jun 27, Sep 9, Sep 10 and Oct 23, 2027.
- **Per-trip switches:** "Timeline / key dates" and "Travel documents" are on by default for cruises.

What's missing is only **drawing them on the page.**

## 1. Countdown (key dates) section: new design
- **Placement:** after How to book, before "Who does what" (the design reference's order). Gate label "Gate nn · Countdown".
- **Heading:** the event type's existing label. Cruise: "Countdown to boarding". Resort: "Countdown to check-in". Others have their own.
- **One row per date, oldest first:**
  - a coloured dot (coral, green or gold, as set on the date);
  - the date, e.g. "JUNE 27, 2027";
  - the title, e.g. "Final payment due";
  - the description, with tokens filled in, e.g. "120 days out."
- **Hidden rows and dates that can't be worked out** (no start date) are left out. With no rows, the section hides itself.
- **Dates already past:** shown dimmed, with a small "Passed" tag, so the list still tells the whole story. Proposed; or hide them.
- **Phone:** one column, dot and date on top.

**For trip 181 the countdown reads:**
- Jun 27, 2027: Final payment due (coral). "120 days out."
- Sep 9, 2027: Last day to change dates or cabins.
- Sep 10, 2027: Payments become final.
- Oct 23, 2027: Name changes and transfers close.

## 2. Travel documents section: new design
- **Placement:** after the Countdown. Gate label "Gate nn · Travel documents".
- **Heading:** "The fine print, folded neatly" (from the design reference, for every event type). Optional: your own wording.
- **Lede under the heading:** proposed "Key {provider} policies, in short. The cruise line's own terms always apply." On a trip with no provider: "Key policies, in short."
- **Panels:** each document is a fold-open panel (title, then a "+" that turns into "−"), all closed at first.
  - Uses the browser's own open/close control, so it works without JavaScript and with a keyboard or screen reader.
  - Bodies use the existing text format: "- " bullets, **bold**, links, and `{date:…}` tokens. For 181, "Final payment is due **June 27, 2027**, 120 days before sailing."
- **Order:** the provider's documents first, then any of the trip's own (the trip-side editor comes in a later step).
- **"Last reviewed" dates:** not shown publicly. They mean "copied from a source", not "verified with the cruise line". Admin only. Proposed.
- **No documents:** the section hides itself.

## 3. Members' trip page (roster view): proposed
**Travellers on a trip always see the members' page, not the new design.** Without this part, trip 181's travellers would never see the final-payment date or the cancellation policy on the site.

- **Proposal:** on the members' page, show the same Countdown and Travel documents, in the members' page styling (cream page, the same fold-open panels).
- **Placement:** after "Rules for this trip" and before "Who's going".
- **Members who still have to accept the trip agreement** see them only after accepting, like the rest of the trip details.
- **Old public page (trips not on the new design):** unchanged. Its visitors aren't booked yet.

## 4. Wording checks (built into the tests)
On every page these sections appear on:
- no "CBGV books your cabin" and no InteleTravel;
- CBGV never described as taking travel money;
- the pay-later line keeps "subject to approval";
- no children wording on Virgin trips.

## 5. What it changes for the public, and when
- **New design:** it's still preview-only, so nothing public changes until go-live (R8). Admins see it with `?preview=new`.
- **Members' page (part 3), if approved:** live for 181's roster as soon as it's deployed.

## 6. Tests and review
- **New test `test_keydates_docs.sh`.** It checks:
  - 181's 4 rows in date order, with the right dates, titles, the coral dot and the "120 days out." text;
  - a trip override (simulated) moving a date, and the order following it;
  - "not applicable" removing a row;
  - a hidden row left out;
  - past dates dimmed with "Passed" (simulated "today");
  - the switches hiding each section;
  - the 4 document panels with their bullet counts and the date token filled in;
  - the lede naming the provider;
  - no "Last reviewed" shown publicly;
  - the members' page showing both sections (and not before the agreement is accepted);
  - the word checks.
- **Mutation run:** the same checks against the live files must fail.
- **Screenshots:** desktop and phone, for the 181 preview and the members' page. Member names masked.
- **Admin manual:** section 21. Then the standard deploy cycle with your OK.

## Decisions needed
1. Past dates: dimmed with "Passed" (proposed), or hidden?
2. Documents heading: "The fine print, folded neatly", or your wording?
3. Documents lede: "Key {provider} policies, in short. The cruise line's own terms always apply.", or your wording?
4. "Last reviewed" dates: hidden publicly (proposed)?
5. Members' page (part 3): add Countdown + Travel documents there too (proposed)?
