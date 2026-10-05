# Admin manual notes: the new trip landing page

Working notes for the CBGV admin manual. A new section is added after each build step, written for an admin who is not technical. At the end of the build this file becomes the admin user manual.

**Last updated:** 2026-10-04 (covers Steps 1, 2, 3, 3b and 4, plus the media rule).

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
The new design is being built one step at a time. **Right now the new page has the new hero and boarding pass, the status board, the intro and the route at the top** (Steps 4 to 6): new header and footer, those sections, then today's trip content underneath. The remaining new sections (route, price board, included cards, timeline, travel documents and so on) are built in later steps. Everything you enter in the boxes below is saved and waiting; **most of it will not show on the page until the step that draws that section is finished.** This file tells you when each section starts showing.

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
| Cruise | cabins | Travelers | Cruises |
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
| `{vessel}` | the trip's vessel or venue name (the **Vessel / venue name** field in section 5) |
| `{accommodation}` / `{accommodation_plural}` | cabin / cabins (or the trip's word) |
| `{party}` | Travelers / Guests / and so on (cruises say "Travelers", not "Sailors", so the wording suits every cruise line) |
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

## 4. Media: Virgin images and video

### The rule
**Only use Virgin Voyages images and video from the official First Mates marketing toolkit on FirstMates.com. Never copy them from Virgin's public website.** The toolkit is where Virgin gives travel partners the right to use its media; its public website is not.

### How to use an asset
- Use it **unaltered**. Web compression (making the file smaller so the page loads fast) is fine. **Do not crop it, edit it, or add logos or text onto the image or video file itself.**
- Upload it in the Media Library: https://bagsandvibes.com/wp-admin/upload.php (add new files: https://bagsandvibes.com/wp-admin/media-new.php).
- Give it a clear file name and descriptive alt text (what the picture shows).
- Add a line to the media sources list, `docs/media-sources.md`: file name, what it shows, "First Mates marketing toolkit". Keep the original download from the toolkit so any file can be traced back.

### Virgin's brand rules ("With Love From" brand guidelines, Nov 2024)
- **The Virgin Voyages logo may only appear in Virgin red, from the official toolkit file. Never recolour it.** That includes putting it on a dark or coloured background in a different colour, or tinting it.
- **Don't recreate Virgin campaign elements.** That means the "with Love from" script lockup, promo stamps, and Virgin's campaign fonts. Use Virgin's finished assets exactly as delivered. Our own headings, fonts and design stay in the Checked Bags & Good Vibes brand.
- **Don't use "With love from..." as Checked Bags & Good Vibes wording** (titles, taglines, buttons, emails). It is Virgin's campaign line.
- **A toolkit image that carries an offer stamp may only be used while that offer is valid.** Note the offer's end date next to the file in the media sources list, and take the image off the page when the offer ends.
- Do not draw, trace or rebuild a Virgin logo or lockup in text, in CSS, or as a new image. If a logo is needed, use the toolkit file.
- The never-recolour rule applies to Virgin's logo FILE. Photos or video that show Virgin signage (e.g. the terminal sign) can sit under the standard hero overlay.

### Common mistakes
- Saving a picture from Virgin's website because it was handy.
- Recolouring, outlining or adding a shadow to a Virgin logo, or recreating it from text or a font.
- Typing "With love from..." in a tagline, or building a script-style lockup in Virgin's style.
- Leaving an image with an offer stamp on the page after the offer has ended. Use the toolkit version instead.
- Cropping or putting a logo on the file before uploading. (The page may display a picture in a frame that shows only part of it; that is fine because the file itself is untouched.)
- Uploading a file and forgetting to list it in the media sources list.
- Letting a frame cut off Virgin's logo or branding, or crop so tightly that the photo shows something different. **A frame must never hide Virgin's logo/branding or change what the photo shows.** If the subject isn't in the middle of the picture, set that image's focus point so the visible part keeps the subject. (Each image gets its own focus-point setting from Step 4.)

---

## 5. Hero and boarding pass (Step 4)

The top of the new page: a full-width hero (looping video or a picture, the trip title and tagline) with a **boarding pass** card showing where the trip goes, when, and a "Claim your seat" button.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- **Hero & boarding pass (new design)** box (main column): video, poster, focus point, vessel name.
- **Day-by-Day Itinerary** box (same screen): a new **Code** column for each stop.
- The words and numbers the pass uses also come from boxes you already fill in (listed below).

### Where each part of the hero and pass comes from
| On the page | Comes from |
|---|---|
| Small gold line above the title ("Now boarding · Oct 25-30, 2027") | The event type's word (Now boarding, Now booking ...) plus the trip's start and end dates. |
| Big title | The trip's title. |
| Line under the title | **Tagline** in the **Public Landing Page Content** box. |
| Trip code chip (top right of the pass) | **Trip Code** in the **Trip Code & Visibility** box. No trip code, no chip. |
| FROM / VIA / TO | The **Day-by-Day Itinerary** rows and their **Code** column. Only shown when **Show Itinerary on Public Landing Page** is ticked (Public Landing Page Content box). |
| Departs / Returns (Check-in / Check-out ...) | The trip's start and end dates. The labels follow the event type. |
| Vessel (Property, Venue ...) | **Vessel / venue name** in the Hero & boarding pass box. |
| "Claim your seat" button | Goes to the registration page for this trip (uses the trip code). |
| Background | The hero video (larger screens) and the poster picture (see below). |

### The fields in the Hero & boarding pass box
| Field | What it does |
|---|---|
| **Hero video** | The looping background video (MP4, no sound needed). Choose it from the Media Library. |
| **Smaller hero video (optional)** | A 720p copy for medium screens and slower connections. If you only choose this one, it is used for every screen. |
| **Poster picture** | The still picture shown before the video loads, on phones, and when the video is off. If empty, the trip's cover photo (then its featured image) is used. If there is none of those, the hero is a plain dark background. |
| **Picture focus point** | Centre (default), Top, Bottom, Left, Right or a corner. The hero is a wide frame and crops the edges of the picture. Choose where the main subject is so it stays in view. |
| **Vessel / venue name** | Shown on the pass; also available as `{vessel}` in text. |
| **Code** (in each itinerary row) | A short port code such as MIA or BIM. Letters and numbers only, up to 5; it is capitalised for you. |

### Order to do things in
1. Upload the Virgin video and picture from the First Mates toolkit (see section 4), unaltered.
2. Open the trip. In **Hero & boarding pass**, choose the video, the smaller video if you have one, and the poster picture.
3. Pick the **Picture focus point** if the subject is not in the middle.
4. Type the **Vessel / venue name**.
5. In **Day-by-Day Itinerary**, type a **Code** on each stop (at least the first stop, the farthest stop and the stops you want shown on the way).
6. Check that **Show Itinerary on Public Landing Page** is ticked, that the trip has a **Trip Code** and a **Tagline**, and click **Update**.
7. Open the **Preview new design** link (section 1) and look at the top of the page on a computer and on a phone.

### How FROM, VIA and TO are chosen
- **FROM** is the first stop in the itinerary.
- **TO** is the farthest stop that is not the same place as FROM. On a round trip (Miami, Puerto Plata, Bimini, Miami) the pass reads Miami to Bimini, not Miami to Miami.
- **VIA** lists the different stops in between (up to three), for example VIA POP.
- "At Sea" rows and rows with no port are ignored.
- **A Code typed on any row for a port is used for every row with the same port name.** Itineraries list a port twice (Arrival and Departure) and return to the start port at the end, so you only need to type each port's Code once; the final Miami is never mistaken for a different place. Rows with different port names are different places, even if they sound alike, unless you give them the same Code.
- If a stop has no Code, its port name is shown in the big letters instead. Add a Code for the neat look.
- Example, a round trip Miami, Puerto Plata, Bimini, Miami: FROM Miami (MIA), VIA Puerto Plata (POP), TO Bimini (BIM). The return to Miami is not shown.
- Fewer than two different stops means no route is shown on the pass.

### Rules worth knowing
- **The video only plays on larger screens.** Phones, visitors who have asked their device to reduce motion, and data-saver users never download it; they see the poster picture. A small **Pause background video** button appears once the video is playing.
- Parts that have nothing to show disappear instead of leaving a blank: no trip code means no chip; no vessel means no Vessel box; no dates means no date boxes.
- **The new hero replaces the old hero** (cover photo, title, "Reserve Your Spot" badge, and the Departs from / Dates strip) in the new design. The rest of today's page stays underneath until its own step.
- **Show Itinerary off** hides FROM / VIA / TO as well as the itinerary section. Dates and vessel stay.
- **A frame must never hide Virgin's logo/branding or change what the photo shows.** The focus point is how you keep the right part of the picture in view. After setting it, preview on a phone and a computer and make sure the logo, if the picture has one, is not cut off.
- Changes to the hero fields do not affect today's public page.
- **The chip on the pass is the trip code, exactly as saved** in **Trip Code & Visibility**. Check it reads right (right year, right letters) before you share anything. **Changing a trip code changes its registration link** (`/join/?trip=CODE`) and the QR code: links and QR images already shared or printed with the old code stop working ("not valid"). The trip's own web address does not change. Change the code BEFORE sharing links, flyers or QR codes. Once people have registered through the old code, changing it would also lose their "interested in this trip" highlight on their dashboard. Personal invite links are not affected.

### Common mistakes
- Choosing a video file but no poster picture. Phones then show the cover photo (or a plain dark background), which may not match.
- Putting a very tall or very wide picture in without checking the focus point.
- Leaving the **Code** column empty and wondering why the pass shows port names instead of MIA and BIM.
- Ticking **Show Itinerary** off to tidy the page and being surprised the route disappears from the pass.
- Using a video from Virgin's public website, or one that has been edited, cropped or had a logo added (section 4).
- Choosing the wrong kind of file: the video fields only accept video files and the poster only accepts an image; anything else is ignored when you click Update.
- Grey placeholder text in a field (e.g. Vessel) is not a saved value; type it in.

---

## 6. Status board and intro (Step 5)

Two sections directly under the hero:
- The **status board**: a dark strip that shows where the group stands, in three stages (below), with flip-board number tiles and a status chip.
- The **intro** (labelled GATE 14 · THE TRIP): a big heading, a few paragraphs, a row of fact chips (5 NIGHTS · VALIANT LADY · ADULTS ONLY · 18+) and a tilted photo with a caption.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- **Status board & intro (new design)** box (main column): the booked number and all the intro fields.
- **Trip Details** box (same screen): **Minimum group size** and **Capacity (spots)**, which the status board also uses.
- **Landing Page Settings (new design)** box: the **Sections** table, where "Status board (minimum travelers)" and "Intro" can be switched On or Off for this trip.

### Where each part comes from
| On the page | Comes from |
|---|---|
| The status board (line, tiles, chip) | **Travelers booked (paid deposits)** (Status board & intro box) compared with **Minimum group size** and **Capacity (spots)** (Trip Details). See "The three stages" below. |
| GATE 14 · THE TRIP | Automatic. Sections number themselves from GATE 14 in the order they appear, so a hidden section never leaves a gap. |
| Heading, text | **Heading** and **Text** in the Status board & intro box. |
| Fact chips | First the number of nights (worked out from the trip's start and end dates), then the **Vessel / venue name** (Hero & boarding pass box), then the **Fact chips** you type. |
| Photo and caption | **Intro photo**, **Photo focus point** and **Photo caption** in the Status board & intro box. |

### The three stages of the status board
The board changes by itself as you update **Travelers booked (paid deposits)**. Example: minimum 25, capacity 50.

| Stage | Tiles | Line | Chip |
|---|---|---|---|
| **1. Below the minimum** (or the number is blank) | "03 OF 25" (booked of the minimum) | "25 travelers needed to depart" | BOARDING |
| **2. Minimum reached** | "30 OF 50" (booked of the capacity) | "Departure confirmed · 20 spots left" | ON TIME |
| **3. Full** (booked reaches the capacity) | "50 OF 50" | "Departure confirmed" | FULLY BOOKED · ASK ABOUT THE WAITLIST |

- Blank number: no tiles at all; the line and chip are stage 1's.
- No capacity set: stage 2 keeps "30 OF 25" and the line is just "Departure confirmed"; stage 3 never happens.
- Other event types use their own words: a resort reads "25 guests needed to confirm", OPEN, then "Group confirmed · 20 spots left", CONFIRMED; a corporate event says "attendees".
- "1 spot left" is singular; the board never shows a negative number of spots.

### The fields in the Status board & intro box
| Field | What it does |
|---|---|
| **Travelers booked (paid deposits)** | How many travelers have paid their deposit. Whole numbers only. Leave it blank if you do not want a number shown; the board then shows only the "needed" line and the chip. 0 is a real value and shows "00 OF 25". |
| **Heading** | The big italic line, up to 120 characters. Tokens such as `{vessel}` work. |
| **Text** | A few paragraphs. A blank line starts a new paragraph; `**bold**`, `*italic*` and `[link text](https://...)` work, and so do tokens (section 3, "Writing text"). |
| **Fact chips** | One per line, up to 4, each up to 40 characters. Type only the extras (for example "Adults only · 18+" or "Low 80s °F"); the nights and the vessel are added for you. |
| **Intro photo** | Chosen from the Media Library. Shown beside the text on a computer, under it on a phone. |
| **Photo focus point** | Same nine choices as the hero. The photo is shown in a 4:3 frame; choose where the subject is so it stays in view. |
| **Photo caption** | The small line under the photo, for example "Our ride: {vessel}". Only shown when there is a photo. |

### Order to do things in
1. In **Trip Details**, check **Minimum group size** and **Capacity (spots)** are right (Capacity drives "spots left" and the Full stage).
2. In **Status board & intro**, type **Travelers booked (paid deposits)** if you want the tiles (or leave it blank).
3. Type the **Heading** and **Text**, then any extra **Fact chips**.
4. If you use a photo: upload it (Virgin pictures from the First Mates toolkit only, unaltered, logged in `docs/media-sources.md`; see section 4), give it **Alt Text** in the Media Library, choose it here, set the focus point and caption.
5. Click **Update** (in the new editor the button may be labelled **Save**).
6. Open **Preview new design** (section 1) and look at both sections on a computer and on a phone.

### Keeping the booked number up to date
- **Update the number each time a deposit is paid** (or a booking cancels), then click Update / Save. The public page shows the new number on the next visit: SiteGround clears its saved copy of the trip page when the trip is saved, and Cloudflare does not keep a copy of trip pages.
- The number is **typed by you on purpose**. It is not the trip roster count, because the roster counts website accounts (including the admin account), misses companions and includes people who registered but have not paid.

### Rules worth knowing
- **The status board hides itself** when the trip has no minimum group size, when the trip's **Status** is Completed or Declined, or when the section is switched Off.
- **The intro hides itself** when both the Heading and the Text are empty, even if chips or a photo are filled in. With text but no heading, the section name ("The trip") is used as the heading.
- No photo = the text runs full width.
- Defaults by event type: both sections are On for Cruise, Resort, Cabin/Villa Rental, Destination and Corporate; a Party / Add-on has the intro but no status board; a Wedding has neither. Any trip can switch either one On or Off in the Sections table.
- Changes here do not affect today's public page.
- **A frame must never hide Virgin's logo/branding or change what the photo shows.** Use the focus point, then preview on a phone and a computer.

### Common mistakes
- Typing the number of registrations or accounts instead of travelers with paid deposits.
- Leaving **Capacity (spots)** at 0 or wrong: stage 2 then cannot say how many spots are left, and the Full stage never shows.
- Forgetting to update the number after new deposits, so the board looks quieter than the trip really is.
- Typing "5 nights" or the ship's name as a fact chip: they are already added automatically and would show twice.
- Filling in only chips and a photo and wondering why the intro is missing (it needs a Heading or Text).
- Leaving the photo's **Alt Text** empty in the Media Library (screen readers then get no description).
- Grey placeholder text in a field is not a saved value; type it in.

---

## 7. Route (Step 6)

One card per day of the trip, under the heading "The Route" (labelled GATE 15 · FLIGHT PATH on a cruise), with a chain of port codes beside it ("MIA → SEA → POP → SEA → BIM → MIA"). Each card shows the day ("DAY 03 · WED OCT 27 · PUERTO PLATA"), a title, the times in port, optional text, an optional sticker and an optional photo.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- **Day-by-Day Itinerary** box: the days, ports, codes, types (Embarkation / Arrival / Departure / At Sea / Disembarkation), times and Dock/Tender. The route is built from these rows.
- **Route days (new design)** box: the route heading and, for each day, a title, text, sticker, photo and focus point.
- **Public Landing Page Content** box: **Show Itinerary on Public Landing Page** must be ticked.

### How the days are worked out
- Itinerary rows with the same **date** make one day (a port's Arrival and Departure rows become one card).
- **DAY 01 is the trip's start date.** The number typed in each row's Day box does not matter (trip 181 numbers its rows from 0 and still shows DAY 01 to DAY 06).
- The card's place is the day's port, or "At sea".
- Rows without a date are grouped by their Day number instead. Give every row a date for the neatest result.
- **The Route days box shows the days as last saved.** After adding or changing itinerary rows, click **Update** / **Save**; the new days then appear in the Route days box.

### The automatic time line
Written for you from each day's rows (a part with no time is left out):

| Day | Cruise | Other event types |
|---|---|---|
| Embarkation | Sail-away 5:00 pm | Departs 5:00 pm |
| Arrival + Departure, Dock | Docked 10:00 am – 6:00 pm | 10:00 am – 6:00 pm |
| Arrival + Departure, Tender | Tender port · 10:00 am – 6:00 pm | 10:00 am – 6:00 pm |
| Arrival + Departure, Dock/Tender blank | In port 10:00 am – 6:00 pm | 10:00 am – 6:00 pm |
| Arrival only | Arrives 10:00 am (· overnight when the next day departs from the same port) | same |
| Departure only | Departs 6:00 pm | same |
| Disembarkation | Back in Miami at 6:30 am | same |
| At sea | (no time line) | (no time line) |

- **On the Embarkation row, the Time is the sail-away (departure) time**, not the boarding time.
- Each day can hide its time line with **Hide the automatic time line**.
- The box shows each day's automatic time line so you can check it before saving.

### The fields in the Route days box
| Field | What it does |
|---|---|
| **Route heading** | The big heading. Leave blank for the event type's default: The Route (cruise, destination), The Plan (resort), The Agenda (corporate). Grey placeholder text is not a saved value. |
| **Title** (per day) | The card's title, e.g. "Sail-away toast". Blank = the place name. Tokens work. |
| **Text** (per day) | A few lines under the time line. Blank line = new paragraph; `**bold**`, `*italic*`, `[link](https://...)` and tokens work. |
| **Sticker** + style (per day) | A small tilted label, e.g. "Tonight: Scarlet Night". **Standard (cream)** or **Highlight (coral)**. Typed text for now; linking stickers to the cruise line's brand features comes later (Step 12). |
| **Photo** + **Photo focus point** (per day) | Shown across the top of the card instead of the dashed gold strip, in a 3:2 frame (the usual shape of a photo). A picture of a different shape is trimmed a little at the edges; if its subject or a logo sits near an edge, set the focus point and check on a computer and a phone. |
| **Hide the automatic time line** (per day) | Hides that day's time line. |

### The code chain
- Built from each port's **Code** (Day-by-Day Itinerary) plus "SEA" for each day at sea. A Code typed on one row of a port covers all its rows.
- **Shown only when every port day has a Code.** If one port has none, the chain is left out (the cards still show).

### Order to do things in
1. In **Day-by-Day Itinerary**, check every row has a **date**, the right **type**, the **time** (Embarkation = sail-away time) and **Dock/Tender**, and a **Code** on each port. Tick **Show Itinerary on Public Landing Page**. Click **Update** / **Save**.
2. In **Route days**, read each day's automatic time line; fix the itinerary rows if one is wrong.
3. Fill in titles, text, stickers and photos where wanted (Virgin pictures from the First Mates toolkit only, unaltered, with Alt Text in the Media Library; see section 4). Click **Update** / **Save**.
4. Open **Preview new design** (section 1) and check the route on a computer and a phone.

### Rules worth knowing
- **Each day's content is stored by its day number from the start date**, so moving the whole trip to new dates keeps every card's content with the right day.
- **Taking a day out of the itinerary keeps its content** (hidden); putting the day back shows it again. Clearing all of a day's fields in the box removes it.
- The route hides itself when **Show Itinerary** is off, when "Route / itinerary" is switched Off in the Sections table, or when the itinerary is empty.
- **Today's itinerary table is removed from the new design** (the route replaces it). Today's public page is unchanged.
- **A frame must never hide Virgin's logo/branding or change what the photo shows.**

### Common mistakes
- Typing the boarding time on the Embarkation row: the card then says "Sail-away" at the wrong time.
- Leaving a row without a date, so the day shows in the wrong place or with no date.
- Adding itinerary days and looking for them in the Route days box before clicking Update / Save.
- Typing a Code on some ports but not all, then wondering why the code chain is missing.
- Typing the time again in a day's Text: it is already in the automatic time line (or hide the time line first).
- A photo that is not 3:2 (for example very wide or tall) with a logo near the edge and the focus point left at Centre: the edge can be trimmed. Set the focus point towards the logo.
- Grey placeholder text in a field is not a saved value; type it in.

---

## Coming in later steps (this file will be extended after each)
- Featured moment and gallery
- Price board: pricing data, all-in price breakdown, how a price column is tagged
- Registration opt-outs (gratuities and Voyage Protection) and the decline acknowledgment
- Included cards and upgrades; pack list, group perks and timeline
- How to book, member hint, travel documents editor on the trip
- Wedding sections; "Copy this trip"; unlisted trips with an access code; go-live checklist
