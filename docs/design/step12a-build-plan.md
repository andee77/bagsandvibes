# Step 12a: How to book + "Who does what" disclosure: build plan

Proposed 2026-10-14, for owner approval. Nothing built yet. Text: `event-page-plan.md`, "Text received 2026-10-14".

## 1. Where the text lives (editable in wp-admin, no code)

The disclosure wording may change after the InteleTravel and attorney replies, so both texts are stored as **settings**, not in code.

- New screen **Settings > How to Book & Disclosure** (`https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-how-to-book`):
  - **Default How-to-book steps:** a repeater of title + text, seeded with your 4 steps. Every trip uses them unless its provider has its own steps (a provider's steps REPLACE the default, as in the plan's inheritance rules).
  - **"Who does what" disclosure:** one text box, seeded with your paragraph, plus a "Last updated" date that is set automatically when the text changes. No versions and no member acceptance; it's a disclosure, not an agreement.
- The seed for the real Virgin provider writes **no steps** (your decision), so Virgin trips use the default 4.
- **Test Provider (402)** has 2 placeholder steps, which would REPLACE the default on trip 181's preview until 181 moves to the Virgin provider (go-live R3). I'll leave 402 alone; the preview shows the placeholders until R3.

## 2. How to book section (new design only)

- **Placement:** after the price board, as in the design reference ("GATE nn · CHECK-IN"). It uses the existing **"How to book"** On/Off switch on each trip (on by default for every type except Wedding), and hides itself when there are no steps.
- **Heading:** counts the steps. Cruise: "Four steps to the gangway"; other types: "Four steps to your trip".
- **Steps** are numbered 01–04 in the reference's layout: dark band, coral numbers, Fraunces titles.
- **Step 3 (CBGV Group Experience Fee) only shows when the trip actually has a fee to bill.** On a trip with no fee, the steps renumber 01–03. Proposed; say if you'd rather always show it.
- **Buttons:**
  - **Register now:** the same registration link as the price board's Reserve.
  - **Booking 5+ cabins? Talk to us:** links to /contact/. Optional; say if you want it.
- **Word check (also run in tests):** never "CBGV books your cabin", never "InteleTravel". Step 2 says the travel advisor books the cabin.

## 3. "Who does what" on every trip page

| Page | Where |
|---|---|
| New design (public and `?preview=new`) | Its own block right under the How-to-book steps, titled "Who does what". It shows even if How to book is switched off. |
| Old public trip page (trips not yet on the new design) | At the end of the page. |
| Members' trip page (roster view) | At the end, after "Discuss this trip". |
| Terms of Service page | A new **"Who does what"** section added to the page text (a WordPress write, with your OK), using a short code so it always shows the current wording. |

Small, readable, not hidden behind a toggle. The "Last updated" date shows under it.

## 4. Not in this step

The footer and logo coin (Step 12), key dates and travel documents (go-live R4/R5, next step), the What's included cards (Step 10), the proposal PDF, and emails. Should the disclosure also go in the client proposal PDF? It's an easy addition later.

## 5. Tests and review

- **New test `test_step12a.php`** (render-based, temp copy). It checks:
  - The settings' default text matches your text word for word.
  - The section renders on trip 181's preview with 4 steps and the right heading.
  - Provider steps replace the default.
  - A trip with no fee drops step 3 and renumbers.
  - The Off switch hides How to book but not the disclosure.
  - The disclosure appears on all 4 page kinds.
  - The word check passes on all of them.
  - Admin save escapes HTML.
  - No children wording.
- **Mutation tests:** the same checks run against the live files and must fail.
- **Screenshots:** desktop and phone for each page kind. Member names masked.
- **Admin manual:** section 19.
- **Deploy:** standard cycle, with your OK. The Terms page write and the settings seed (two options) are separate, listed writes.

## Decisions needed

1. Step 3 hidden on trips with no fee, or always shown?
2. Keep the "Booking 5+ cabins? Talk to us" button?
3. Headings "Four steps to the gangway" (cruise) and "Four steps to your trip" (other types): OK, or your wording?
4. Add the disclosure to the Terms of Service page (write)? Where: a new last section, or after section 2 "Bookings and Payments"?
5. Disclosure in the proposal PDF as well (later)?
