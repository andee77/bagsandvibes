# Admin manual notes: the new trip landing page

Working notes for the CBGV admin manual. A new section is added after each build step, written for an admin who is not technical. At the end of the build this file becomes the admin user manual.

**Last updated:** 2026-10-03 (covers Steps 1, 2, 3 and 3b).

## How to read this file
- **Screens** (always listed by their full address; these are the list and add-new screens, so they work for any trip or provider):
  - Trips, list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip
  - Trips, add new: https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_trip
  - Trip Types (the existing categories): https://bagsandvibes.com/wp-admin/edit-tags.php?taxonomy=cb_trip_type&post_type=cb_trip
  - Provider Library, list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider
  - Provider Library, add new: https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_provider
- To edit a trip or a provider, open its list screen above and click the name. "Edit a trip" below always means that.
- Fields shown in **bold** are the exact labels you will see on screen.
- Nothing here changes what the public sees until a trip is switched live (see section 1). You can fill everything in safely ahead of time.

## What you can see today, and what comes later
The new design is being built one step at a time. **Right now the new page is only a frame**: new header and footer, plus today's trip content inside it. The new sections (status board, price board, included cards, timeline, travel documents and so on) are built in later steps. Everything you enter in the boxes below is saved and waiting; **most of it will not show on the page until the step that draws that section is finished.** This file tells you when each section starts showing.

---

## 1. Turning the new design on for a trip (Step 1)

### Where it is
Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name. In the right-hand column there is a box called **New Landing Design**. Only administrators see it.

### What it does
| Item | What it does |
|---|---|
| **Use new landing design** (checkbox) | Off by default. When ticked, the public sees the new design for this trip, but only if the master switch is also on (below). |
| **Master switch** line | Shows ON or OFF. This is a site-wide switch set by the developer; it is OFF now. While it is off, **no public visitor sees the new design on any trip**, whatever the box says. |
| The sentence under it | Says in plain words what the public sees for this trip right now. Believe it: it checks all the conditions for you. |
| **Preview new design (admins only)** link | Opens the trip with the new design, for you only. |

### The three things that must all be true for the public to see the new design
1. **Create Public Landing Page** is ticked on the trip (this is the existing opt-in).
2. **Use new landing design** is ticked on the trip.
3. The master switch is ON.

If any one is missing, visitors see the current design (or no landing page, if the first is unticked).

### Previewing
- Add `?preview=new` to the trip's web address, or use the **Preview new design** link. You must be logged in as an admin.
- A banner across the top says "New landing design · admin preview" and tells you: what the public currently sees, the trip's event type, its provider, and which sections are switched on.
- Previews are never shown to search engines and are never cached.
- Previewing works even if **Create Public Landing Page** is unticked.

### Common mistakes
- Ticking **Use new landing design** and expecting the public to see it. The master switch must be on as well.
- Forgetting that the preview shows a half-built page for now. Judge the content boxes, not the layout, until the later steps are done.

---

## 2. Landing Page Settings and event types (Step 2)

### Where it is
Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name. In the main column there is a box called **Landing Page Settings (new design)**.

### Order to do things in
1. Choose the **Event type**.
2. Choose the **Provider** (see section 3).
3. Optionally set the **Accommodation word**.
4. Look at the **Sections** table and switch any section On or Off.
5. Click **Update**. The defaults in the table follow the event type *as last saved*, so after changing the type, save and reopen to see the table update.

### Event type
Sets three things for the page: which sections are on by default, what the page calls things, and a little starter wording. It is the same page template for every type.

| Event type | Calls the rooms | Calls the people | Typical use |
|---|---|---|---|
| Cruise | cabins | Sailors | Cruises |
| Resort | rooms | Guests | Resort stays |
| Cabin / Villa Rental | villas | Guests | Rentals |
| Destination / Land Trip | rooms | Travelers | Land trips and packages |
| Wedding | rooms | Guests | Weddings (adds couple's story, schedule, RSVP) |
| Party / Add-on | spots | Guests | Parties and add-ons |
| Corporate | rooms | Attendees | Corporate events |

- **Automatic** (the first choice in the list) picks the type from the trip's existing **Trip Type** (list: https://bagsandvibes.com/wp-admin/edit-tags.php?taxonomy=cb_trip_type&post_type=cb_trip; Cruise, Resort, Hotel and Retreat map to Cruise or Resort; everything else maps to Destination). The setting says what it is currently choosing.
- If you pick a type yourself, it always wins over the automatic one.
- This is separate from Trip Type. Changing the event type does not change the trip's Trip Type, filters or listings.

### Accommodation word
Optional. Replaces the type's word for the place people sleep, for example "suite" or "cottage". The heading follows it ("Pick your suite"). Leave blank to use the type's word. 30 characters at most.

### Sections table
Each row is a part of the page. Columns:
- **Default for (type)**: On or Off, from the event type.
- **This trip**: **Default**, **On** or **Off**.

Rules worth knowing:
- **Default** means "follow the event type". If you later change the event type, sections left on Default move with it. Choosing On or Off pins that section for this trip.
- **A section that is On still hides itself if it has nothing to show.** For example, "Travel documents" is On for a cruise but disappears if no documents exist. You never need to switch a section Off just because it is empty.
- The page is always built with a header and a footer; those are not in the table.
- Section names you will see: Status board (minimum travelers), Intro, Route / itinerary, Featured moment, Photo gallery, Price board, What's included, Upgrades, Pack list, How to book, Group perks, Member hint, Timeline / key dates, Travel documents, Couple's story, Event schedule, RSVP.

### Common mistakes
- Switching a section **On** that the event type does not use, expecting content to appear. It still needs content from the trip or provider.
- Changing the event type and not clicking **Update** before looking at the Sections table.

### When this starts showing on the page
Event-type wording and the sections' on/off choices start taking effect as each section is drawn in the later steps. Today they only show in the preview banner.

---

## 3. Provider Library (Steps 3 and 3b)

A **provider** is a cruise line, resort brand and so on. You enter its reusable text **once** here, and every trip that picks that provider shows it, instead of retyping the same cancellation policy, how-to-book steps and key dates on every trip.

### Where it is
Provider Library list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider (also **Provider Library** in the left menu of wp-admin). Providers are internal only: they never appear as pages on the website and are not searchable.

### Creating a provider
1. Open https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_provider (Provider Library, add new).
2. Type the provider's name as the title (for example "Virgin Voyages").
3. Fill in the boxes below as far as you want. Everything is optional; you can add more later.
4. Click **Publish**. **A provider must be Published before trips can choose it.** A draft will not appear in the trip's Provider list.

### The boxes on a provider

| Box | What it holds | What a trip does with it |
|---|---|---|
| **Price column names** | Up to four names for the price board's columns, for example Base, Essential, Premium. Leave unused slots blank. | Trip inherits them unless it enters its own. |
| **What's included cards** | Cards for the "What's included" section. Each has a **Label**, **Title**, **Subline**, **Style** (Light, Gold (featured), Dark), **Bullets** (one per line) and a **Wide card** tick. Below the cards: **Footnote under the cards** (optional; one short note). | Trip inherits them unless it enters its own. |
| **How to book steps** | The numbered steps in order (01, 02, 03 ...). Each has a title and text. | Trip inherits them unless it enters its own. |
| **Travel documents** | Policy pages shown at the bottom of the page (cancellation, payments, ID). Each has a **Document title**, **Text**, and **Last reviewed** (see below). | The trip's own documents are added after these. |
| **Key dates** | Dates counted from each trip's start date. See section 3a. | Trip can change them one by one. |

### Replace or add? (the most important rule)
When a trip picks a provider it inherits that provider's content. What happens when the trip also enters its own depends on the group:

| Group | If the trip enters its own |
|---|---|
| Price column names | **Replaces** the provider's. |
| What's included cards | **Replaces** the provider's, all of it. |
| Footnote under the cards | **Replaces** the provider's. |
| How to book steps | **Replaces** the provider's, all of it. |
| Travel documents | **Adds** to the provider's. The trip's documents appear after the provider's; they do not replace them. *(A later step adds the rule "same title replaces", see the plan.)* |
| Key dates | **Replaces only the dates you name**, by key (see 3a). Others are inherited. |

"Replaces" means the trip's version wins **completely** for that group. If a trip enters one card of its own, the provider's other cards are no longer used on that trip. If you only want to change one thing, do not enter your own version of the whole group.

A group the trip leaves empty always falls back to the provider's.

### Last reviewed (documents)
An internal date you set when you copied the provider's terms in or last checked them. **It is never shown to the public.** It means "when did we last check this against the provider's own current terms", not "approved by the provider". Update it when you re-check.

### Writing text: formatting and tokens
Wherever text is entered (bullets, steps, documents, footnote, key-date descriptions):

| Type this | You get |
|---|---|
| `**word**` | **bold** |
| `*word*` | *italic* |
| `[text](https://example.com)` | a link (web, email and phone links only) |
| a line starting with `- ` | a bullet |
| a blank line | a new paragraph |
| a line starting with `> ` | small print |

**Tokens** are placeholders that fill in details for each trip. Type them in curly brackets:

| Token | Fills in |
|---|---|
| `{trip_title}` | the trip's title |
| `{trip_code}` | the trip code |
| `{start}` / `{end}` | the start and end dates, like "October 25, 2027" |
| `{deposit}` | the trip's deposit amount, like "$250" |
| `{vessel}` | the trip's vessel or venue name |
| `{accommodation}` / `{accommodation_plural}` | cabin / cabins (or the trip's word) |
| `{party}` | Sailors / Guests / and so on |
| `{date:key}` | a key date, like "June 27, 2027" (see 3a) |
| `{days:key}` | the number of days from that key date to the trip's start, like "120" |

- Tokens are filled in separately for each trip, so one provider text works for every trip.
- **A token that doesn't exist prints nothing (blank).** Check spelling: `{date:final_payment}` not `{date: final payment}`. A token for a date that a trip has no start date for also prints blank.
- Prefer `{days:final_payment}` to typing "120 days": if one trip has a different deadline, the number stays correct.

### Choosing a provider on a trip
On the trip (https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then the trip's name), in **Landing Page Settings (new design)**, use the **Provider** dropdown. The link beside it, **Manage Provider Library**, opens https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider. Choose **None** for a trip with no provider. Click **Update**.

### Changing or deleting a provider
- Edits to a provider apply to every trip that uses it. The public page may take a few minutes to show them while the site's page cache refreshes.
- **Moving a provider to Draft or Trash** makes trips that chose it behave as if they had no provider. Their own entries still show.
- Changing a trip's provider does not delete anything entered on the trip.

### Common mistakes
- Looking for the provider in the trip's Provider list before it is **Published**.
- Entering one card or one step on a trip and being surprised the provider's others disappeared (replace rule).
- Re-typing the provider's documents on the trip, which duplicates them (documents add, they do not replace).
- Typing a date like "June 27" into text instead of using `{date:final_payment}`: it will be wrong for the next sailing.
- Copying wording from a provider's website without checking it is current. Fill in **Last reviewed** and re-check periodically.

---

## 3a. Key dates (provider and trip)

### What they are
A key date is a named date counted from the trip's **start date**. Example: **Final payment due**, key `final_payment`, 120 days before start. On a trip starting October 25, 2027 that is June 27, 2027. Key dates drive three things from one list: the `{date:key}` and `{days:key}` tokens in any text, and (in a later step) the countdown timeline.

### On a provider (open the provider from https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider, then the **Key dates** box)
Each row:

| Field | What it does |
|---|---|
| **Key (the {date:key} token)** | The short code used in tokens, like `final_payment`. Lowercase letters, numbers and underscores. If you leave it blank it is made from the title ("Final payment due" becomes `final_payment_due`), so set it yourself to something short and keep it stable: changing a key breaks text that uses the old one. |
| **Title** | What the timeline row says. |
| **Days before start** | A whole number. 120 means 120 days before the trip starts. **Use a minus number for after the start** (-7 is a week after). |
| **Dot colour** | Gold (default), Coral or Green. |
| **Date text override** | Optional. Replaces the date shown on the timeline row (for example a range). Tokens allowed. |
| **Token only (not a timeline row)** | Ticked means the date is available for `{date:key}` text but is not shown as its own timeline row. Use this for dates you only quote in sentences. |
| **Description** | Optional line under the date. |

- A trip with **no start date** shows none of these dates.
- If two rows on a provider use the same key, the second is renamed automatically (`final_payment_2`). Check that each key is unique so your tokens point at the date you mean.
- The provider's dates are always counted from days before start. Only a trip can use a fixed date.

### On a trip (open the trip from https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then the **Key dates for this trip (new landing design)** box)
Use this when one trip differs from its provider: for example a group contract with a different final-payment deadline. 

If the trip has a provider with key dates, the box first shows a small table: the provider's date, what it works out to for this trip, and what it is on this trip.

Then add rows with **+ Add / override a date**. Each row:

| Field | What it does |
|---|---|
| **Key** | The same key as the provider's date to **change** it, or a new key to **add** a date this trip only has. |
| **Title (blank = keep the provider's)** | Leave blank to keep the provider's wording. |
| **Days before start** | A different offset. |
| **OR a fixed date (wins)** | A real calendar date. **A fixed date beats "days before start" if both are filled in.** Use it for contract deadlines. |
| **Dot colour** | "Keep provider's", or choose one. |
| **Date text override** | Optional. |
| **Not applicable on this trip** | Tick to remove that provider date from this trip altogether. |
| **Token only** | Same as on the provider. |
| **Description (blank = keep the provider's)** | Leave blank to keep the provider's. |

### Rules worth knowing
- **Same key replaces, and only what you fill in changes.** Fill in a new date and leave the rest blank: the title, description and colour come from the provider.
- **A fixed date beats days before start.**
- **A new key adds a date** that only this trip has. Give it a **Title** (a later step will make a title required).
- **Not applicable** removes the provider's date from the page and from the tokens. Text that uses its `{date:key}` then prints blank, so check any text that mentions it.
- A row with **no date and no offset** changes nothing (it is ignored).
- If the same key appears twice on one trip, the **lower** row wins.
- The `{days:key}` number follows the trip's date, so wording like "120 days before sailing" stays true after an override.

### Doing it in order (the usual case)
1. Make sure the provider has the key dates and is chosen on the trip.
2. Make sure the trip has its **start date**.
3. Only if this trip differs, add a row on the trip for each date that differs.
4. Click **Update**. The table at the top of the box now shows the result.
5. Check with the **Preview new design** link.

### Common mistakes
- **Typing the date into the Key field.** The Key is a short name such as `final_payment`, not a date. The date goes in **OR a fixed date (wins)** (or **Days before start**). A date typed into Key creates a meaningless new date instead of changing the provider's. *(A later step turns Key into a dropdown of the provider's keys.)*
- Using a different key from the provider (for example `final_pay` vs `final_payment`) and getting a **new** date instead of a change. Copy the key exactly from the table at the top of the box.
- Filling in both "days before start" and a fixed date and expecting the offset to count. The fixed date wins.
- Overriding a date and forgetting a sentence elsewhere that still has the old number typed in. Use tokens.
- Leaving the trip's start date empty. Provider dates then cannot be worked out.

### When this starts showing on the page
The tokens work in provider text as soon as the section that holds that text is drawn. The countdown timeline itself is drawn in a later step.

---

## Coming in later steps (this file will be extended after each)
- Hero and boarding pass; status board and intro
- Route (itinerary extras); featured moment and gallery
- Price board: pricing data, all-in price breakdown, how a price column is tagged
- Registration opt-outs (gratuities and Voyage Protection) and the decline acknowledgment
- Included cards and upgrades; pack list, group perks and timeline
- How to book, member hint, travel documents editor on the trip
- Wedding sections; "Copy this trip"; unlisted trips with an access code; go-live checklist
