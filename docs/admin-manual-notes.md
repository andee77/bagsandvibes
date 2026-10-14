# Admin manual notes: the new trip landing page

Working notes for the CBGV admin manual. A new section is added after each build step, written for an admin who is not technical. At the end of the build this file becomes the admin user manual.

**Last updated:** 2026-10-11 (covers Steps 1 to 9a, the media rule, payments and the server error log).

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
The new design is being built one step at a time. **Right now the new page has the new hero and boarding pass, the status board, the intro, the route, the featured moment, the gallery and the price board at the top** (Steps 4 to 9): new header and footer, those sections, then today's trip content underneath. The remaining new sections (included cards, timeline, travel documents and so on) are built in later steps. Everything you enter in the boxes below is saved and waiting; **most of it will not show on the page until the step that draws that section is finished.** This file tells you when each section starts showing.

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
- **Photo credits are optional for First Mates toolkit images** (Virgin does not require them), so **we don't show photo credits on the page**. A photographer's name in a file name or in the file's details is not a reason to hold a toolkit image back. Credit or copyright details already inside a file are kept when it is compressed for the web.
- **Every photo used on the new page (intro, route days, featured moment, gallery) needs Alt Text in its Media Library entry**: open the picture in https://bagsandvibes.com/wp-admin/upload.php, fill in **Alt Text** with a short description of what the picture shows (e.g. "Sailors in red dancing at the Scarlet Night deck party"), and it saves by itself. The page reads the alt text from there, so screen readers describe the picture and search engines understand it. A caption on the page does not replace it.
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
| "Claim your seat" button | Jumps down to the price board (since Step 9; see section 13). When the board is hidden, it goes to the registration page for this trip (uses the trip code). |
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

## 8. Featured moment and gallery (Step 7)

Two sections after the route:
- The **featured moment**: one signature event per trip (for example Scarlet Night) on a coloured band, with a small label ("GATE 16 · DAY 02 · AFTER DARK"), a big title, a paragraph, an optional pinned note card ("Dress code · All red") and up to two tall photos.
- The **gallery** (GATE 17 · LIFE ON BOARD on a cruise): a heading, one intro line and up to 10 photos shown as tilted prints with short captions.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- **Featured moment (new design)** box and **Gallery (new design)** box (main column).
- **Landing Page Settings (new design)** box: the **Sections** table, where "Featured moment" and "Photo gallery" can be switched On or Off for this trip.

### The fields in the Featured moment box
| Field | What it does |
|---|---|
| **Title** | The big heading. **No title = no featured section.** Tokens work. |
| **Day** | Optional. Pick one of the trip's days (the list comes from the Day-by-Day Itinerary, as in the Route days box). Adds "DAY 02" to the small label. Stored by day number, so moving the trip's dates keeps it on the right day. A day that is no longer in the itinerary stays selected but is not shown on the page. |
| **Text** | A paragraph or two. Blank line = new paragraph; `**bold**`, `*italic*`, `[link](https://...)` and tokens work. |
| **Note card** | Optional: a small label (up to 40 characters) and one line (up to 200), shown on a cream card. Leave both blank for no card. |
| **Photo 1**, **Photo 2** + focus points | Optional, shown in a tall 2:3 frame; photo 2 sits a little lower. One photo works too. No photos = the text runs full width. |
| **Background colour** | Horizon blue (default), Ink, Deep palm teal, or Deep red (for red-themed events). All four keep the text easy to read. The teal is a deeper shade of the brand teal, used only here. Ink is the page's own background, so an Ink band gets a thin gold line at its top and bottom. |

### The fields in the Gallery box
| Field | What it does |
|---|---|
| **Heading** | The big heading. Blank = the section name ("Life on board" on a cruise, "The place" for a resort ...). |
| **Intro line** | One short line under the heading. `**bold**` and links work. |
| **Photos** | Up to **10**. **Add photos** opens the Media Library; choose several at once (only the first ones that fit are added once the gallery holds 10). Each photo has a **Caption** (up to 60 characters, optional) and a **Focus** point. **Up** / **Down** change the order; **Remove** takes a photo out of the gallery (the picture stays in the Media Library). Changes are saved when you click Update / Save. **No photos = no gallery section.** |

### Order to do things in
1. Upload the pictures (Virgin pictures from the First Mates toolkit only, unaltered, logged in `docs/media-sources.md`; see section 4).
2. **Give every picture Alt Text in its Media Library entry** (section 4). The page reads it from there; a caption does not replace it.
3. Fill in the Featured moment box (title, day, text, note, photos, colour) and the Gallery box (heading, intro, photos in the order you want, captions).
4. Click **Update** / **Save**, then open **Preview new design** (section 1) on a computer and a phone.

### Rules worth knowing
- **Only pictures you choose from the Media Library are used.** Photos members upload to a trip's own gallery (Gate 08) never appear on the public page.
- Photos are shown in fixed frames (2:3 for the featured moment, 4:5 for gallery prints). A picture of another shape is trimmed at the edges; use the focus point so the subject and any logo stay in view. **A frame must never hide Virgin's logo/branding or change what the photo shows.**
- On a tablet the gallery shows three prints per row; a last row with fewer prints is centred.
- GATE numbers follow on automatically; a hidden section does not use a number.
- Defaults by event type: the featured moment is On for Cruise, Destination and Party / Add-on; the gallery is On for every type except Party / Add-on and Corporate. Any trip can switch either in the Sections table.
- Today's "Highlights" boxes on the current page stay for now; they move into "What's included" / "Group perks" in later steps.

### Common mistakes
- Filling in the featured photos and text but no **Title**, then wondering why the section is missing.
- Uploading gallery pictures without **Alt Text**.
- Expecting **Remove** to delete the picture: it only takes it out of this trip's gallery.
- Adding photos and leaving the page without clicking **Update** / **Save** (the order and removals are not kept).
- Choosing the Deep red band for a night that is not red-themed; Horizon blue is the safe default.
- Grey placeholder text in a field is not a saved value; type it in.

---

## 9. Price board data (Step 8)

Step 8 adds a few fields to the **Pricing Tiers** box so the new design's price board (drawn in Step 9) knows how to arrange your prices. **Nothing changes on any page yet**, and no price is changed: the new fields only say where each price goes.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- **Pricing Tiers** box: each tier (cabin category) now has a dashed **Price board (new design)** area, and each occupancy price point has a **Price board column (new design)** dropdown.
- The column names come from the trip's provider: Provider Library, https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider, the provider's **Price column names** box (e.g. Base, Essential, Premium).

### How the price board will read your prices
- **Prices are all-in:** cruise fare (Voyage Fare less Discount) + Taxes & Fees + Gratuities (prepaid gratuities) + Insurance (Voyage Protection). The board shows the **all-in price per cabin for 2 travelers**, with the per-person price underneath, and an itemized line: "All-in for 2 travelers: cruise fare $X · taxes & fees $X · prepaid gratuities $X · Voyage Protection $X".
- The **Discount is folded into the cruise fare**; there is no separate "savings" line.
- The per-person / per-cabin rule is the one you already use: tick "priced per cabin" on a price point when the amounts you typed are whole-cabin totals.
- For each column the board uses the price point for **2 travelers**; if a column has none for 2, it uses the smallest headcount in that column.

### The new fields
| Field | Where | What it does |
|---|---|---|
| **Group** | each tier | A heading that gathers tiers on the board, e.g. "Sea Terrace · Balcony + Hammock". Tiers with the same Group (capitals don't matter) form one section, in the order they are listed. Blank = the tier is its own section, named after the tier. |
| **Badge** | each tier | A small label on the row, e.g. "Partial view" or "★ Group favorite" (up to 40 characters). |
| **Note** | each tier | Optional, one line under the row (up to 160 characters), e.g. "Booked alongside the group; group perks apply to Sea Terrace". |
| **Highlight this row** | each tier | Marks the row (e.g. the group favorite). |
| **One price only (suites)** | each tier | Shows a single price for the row (the first column that has one) instead of one per column; a suite does not add columns to the board. |
| **Price board column** | each price point | Which column the price is shown in: Column 1 (the default), the provider's other named columns, or **Not on the price board**. Columns are stored by position, so renaming a column in the Provider Library keeps every price in place. |

### Order to do things in
1. Check the provider's **Price column names** (Provider Library).
2. On the trip, in **Pricing Tiers**: give each tier its **Group**, any **Badge** / **Note**, tick **Highlight** on the favorite and **One price only** on suites.
3. Set each price point's **Price board column**. Use **Not on the price board** for fares that cannot be held in the group (see below).
4. Click **Update** / **Save**. The proposal PDF now shows a **Fare** column with each price's column name.

### Rules worth knowing
- **Lock-It-In and Base fares cannot be held in our group:** keep them in the Pricing Tiers (they still appear in the admin and the proposal PDF) but set their price points to **Not on the price board**. (Base stays off until Virgin Groups confirms.)
- **Virgin's Groups Offer** (to be confirmed with Virgin Groups): only Sea Terrace and Central Sea Terrace cabins (Essential or Premium) are held in the group block; other cabins join "alongside the group" without the group bar tab or discount. Use the tier **Note** to say so on those rows once confirmed.
- A tier whose price points are all "Not on the price board" does not appear on the board; a Group with no rows left does not appear.
- **Prices entered before Step 8 keep working:** with no tags, every price is in column 1 and every tier is its own section.
- Saving a trip now also stores the (empty) new fields with each tier; prices are untouched.
- Today's public page and Gate 07's price range are unchanged (they still use every tier, including Lock-It-In).

### Common mistakes
- Typing a Group slightly differently on two tiers ("Sea Terrace" vs "Sea Terraces"): they become two sections. Capitals don't matter; spelling does.
- Leaving Lock-It-In price points on Column 1: they would appear on the board as a normal group price.
- Ticking "priced per cabin" on a point whose amounts are per person (or the reverse): every price for that point is then 2-3 times off. Check the "= $... per person · $... per cabin" line under each point after saving.
- The board itself (how it looks, the photos and the header note): section 13.

---

## 10. CBGV Group Experience Fee (Step 8b)

The **CBGV Group Experience Fee** is Checked Bags & Good Vibes' own fee for the group program (organising and coordinating the group). Checked Bags & Good Vibes is a d/b/a of JourneyWell Global LLC. It is **not** a travel agent fee and **not** part of the cruise fare: cruise (and other supplier) payments go directly to the cruise line or supplier. On the new design it is the fifth line of the all-in price, **"CBGV Group Experience Fee"**, and it is included in the all-in total. Travelers cannot opt out of it.

**Nothing changes on any page or proposal yet.** The fee stays hidden from clients until **Group Experience Fee is live** is ticked (see below), and that waits for the go-live business checks.

### Where it is
- Standard fees: Settings > **Group Experience Fees**, https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-experience-fees (administrators only).
- Each trip: Trips list, https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, click the trip's name, then the **Group Experience Fee (new design)** box (administrators only; other users don't see the box and can't change the fee).
- Refund policy (approved by LaDon): `docs/policies/cbgv-group-experience-fee-refunds.md`.

### The settings page
| Field | What it does |
|---|---|
| **Amount ($)** for each event type | The standard fee for every trip of that type. Type just the number, e.g. 250 or 250.00 ($ and commas are fine). Blank or 0 = no fee for that type. |
| **Basis** | **Per traveler** (the amount times the number of travelers in the cabin), **Per cabin / room** (once per cabin), or **Flat per booking** (once in each cabin's price, with the note "One fee per booking, however many cabins you book together"). |
| **Children are under __ years old** | The age that makes a traveler a child. **Children always pay half** the fee when it is charged per traveler (per cabin and flat per booking are not affected). Set to **18**: children are 17 and under, 18 and over is an adult (LaDon, 2026-10-08). The traveler intake shows it next to the boxes: "Additional adults traveling with you (age 18 and over)" and "Additional children traveling with you (under 18)". Change it only on LaDon's say-so. |
| **Group Experience Fee is live** | Off (the default): the **standard** fee only shows in admin previews of the new design (`?preview=new`) and on these admin screens, and **proposal PDFs leave every fee out**. A trip's **own** fee ("Use a different fee for this trip") shows on the new design's price board even while this is off, because the Payment page already bills it (since Step 9; see section 13). On: every fee is added to the price board and to proposal PDFs. |

### The trip's Group Experience Fee box
- **Use the standard fee**: the default. The box shows what that is, e.g. "Cruise: $250 per traveler".
- **Use a different fee for this trip**: type an amount and choose a basis.
- **No Group Experience Fee on this trip**: waives it (for example a trip planned for free).

### What travelers will see (once live)
- On the price board, each price includes the fee for that cabin: for $250 per traveler, a cabin for 2 includes $500. The itemized line ends "· CBGV Group Experience Fee $500".
- A note under the board, the same for every trip type: "The CBGV Group Experience Fee is paid to Checked Bags & Good Vibes (a d/b/a of JourneyWell Global LLC) for the group program; travel payments go directly to the cruise line or supplier. It's fully refundable within 7 days of paying, 50% refundable until [the trip's final payment date], and non-refundable after that. Full refund if the trip is cancelled." The date comes from the trip's key dates; if the trip has no final payment date it says "the trip's final payment date". Flat-per-booking trips add "One fee per booking, however many cabins you book together."
- When the fee is per traveler, the board note and the proposal PDF also say: "Children pay half the CBGV Group Experience Fee." (Board and PDF prices are for 2 adults.)
- In the proposal PDF: a **CBGV Group Experience Fee / Cabin** column after Discount, and both totals (per person and per cabin) include the fee.

### Order to do things in
1. Enter the standard fees on the settings page and click **Save Group Experience Fees**. Leave **Group Experience Fee is live** unticked.
2. On any trip that needs a different fee, or none, set its **Group Experience Fee** box and click **Update**.
3. Check the trip with `?preview=new` (signed in as an administrator): the board prices should include the fee.
4. Before ticking **Group Experience Fee is live**: the go-live business checks are done (InteleTravel terms, Seller-of-Travel registration, how CBGV collects the fee), and each trip's Pricing Tiers have been checked to make sure no CBGV fee is already typed into a price.

### Rules worth knowing
- The fee is never folded into the Voyage Fare or the Discount. Don't type it into the Pricing Tiers.
- Changing a standard fee changes it for every trip of that type that uses the standard.
- Today's public page, Gate 07's price range and the legacy "From $..." prices never include the fee.
- Never write that CBGV books the cabin: **the travel advisor books it**. CBGV runs the group program.
- Public wording never names InteleTravel: say "independent travel advisor" (Andrea M. Peaten). The host agency is given on request, or where the law requires it.
- The member **Payment page** bills this same fee (it used to be called the "CBGV Commitment Fee"): the trip's own fee x the member's travelers, plus any approved extras. See section 11, "Payments and Stripe".
- Editing the Payment Disclaimer on its Settings page always raises its version, so every member must accept it again. A wording-only fix is done by the developer without a version bump.
- Refunds: full within 7 days of paying (if the trip is 120+ days away), 50% until the trip's final payment date, non-refundable after. Full refund if CBGV or the supplier cancels. Free transfer to a replacement traveler. The fee carries over if the trip is rescheduled (full refund if the traveler can't make the new date). Refunds go back to the original payment method within 14 days.
- Recording who has paid the fee, and when, comes with registration and payments (Step 9b).

### Common mistakes
- Ticking **Group Experience Fee is live** before the business checks are done: every proposal PDF generated after that includes the fee.
- Typing the fee into a trip's Voyage Fare or Taxes as well: it would be counted twice.
- Choosing **Per cabin** when the fee is meant per person: a cabin for 2 would show $250 instead of $500.
- Picking "Use a different fee" and leaving the amount blank: that means no fee on the trip.

---

## 11. Payments and Stripe (CBGV fee only)

**CBGV never collects travel funds.** The only thing members pay on this site, by card through Stripe, is the **CBGV Group Experience Fee** (plus any approved extras). Cruise fares, deposits and every other travel payment are booked by the independent travel advisor and paid directly to the cruise line or supplier.

### Where it is
- Members pay on **Gate 09 — Payments**: https://bagsandvibes.com/gate-09-payments/
- Each trip's fee: Trips list, https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, click the trip's name, **Group Experience Fee (new design)** box.
- Approved extras, payment mode, deposit and installments: the same trip screen, **Gate 09 — Payment Settings** box.
- Standard fees and the **Payment safeguard log**: Settings > Group Experience Fees, https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-experience-fees
- Stripe: https://dashboard.stripe.com/payments

### How a member's amount is worked out
- **Fee for the member's party + approved extras.** The fee is the trip's own fee (its Group Experience Fee box). The standard fee is only billed once **Group Experience Fee is live** is on.
- **Who is in the party:** the member plus the additional adults (18 and over, full fee each) and additional children (17 and under, **half the fee each**) on their traveler intake. If the intake is not filled in, it is the member alone. Example on trip 181 ($225 per traveler): 2 adults + 1 child = $225 + $225 + $112.50 = **$562.50**. Per cabin and flat per booking fees are charged once, whoever is in the party.
- The Payment page shows the working, e.g. "CBGV Group Experience Fee: $562.50 (2 adults x $225.00 + 1 child x $112.50)".
- **Approved extras ($ per member)** is for CBGV extras the trip has approved. It is never for travel costs.
- **Travel price (reference only, never charged here)** (it used to say "Price per person") and the Gate 12 **Quoted travel price** are for reference only. Whatever they say, the site never charges them, including after a member accepts a Gate 12 quote.
- With **Deposit + installments**, the first payment is the deposit setting or the fee, whichever is smaller, and the rest is split over the installments. The total is never more than the amount owed.

### Stripe rules
- Every charge is named **"CBGV Group Experience Fee: {trip name}"** in Stripe. A charge with any other name did not come from this site.
- **Hard limit:** the site refuses any charge that would take a member above the fee for their party (full per adult, half per child) + approved extras, and logs it. If Stripe ever reports a payment above that limit, it is logged too. The log is at the bottom of Settings > Group Experience Fees.
- Never create a manual charge, invoice or payment link in Stripe for travel costs.
- Refunds of the fee follow the refund policy (`docs/policies/cbgv-group-experience-fee-refunds.md`) and are made in Stripe, back to the original card.

### Monthly reconciliation (first week of each month, for the month before)
1. In Stripe (https://dashboard.stripe.com/payments), filter last month's **successful** payments and export them.
2. Check every charge is named "CBGV Group Experience Fee: ..." and is no more than that member's fee for their party (full per adult, half per child) + approved extras.
3. For each trip, download its roster (trip screen, **Export Roster** box) and compare each member's amount paid with Stripe. Every Stripe payment should appear once, and nothing should appear that is not in Stripe.
4. Open the **Payment safeguard log** (Settings > Group Experience Fees). Look into every entry from last month: a "checkout refused" entry means a member could not pay (check the trip's fee, approved extras and the member's travelers); a "webhook over limit" entry means money was taken above the limit and must be refunded or explained.
5. Check that refunds made in Stripe match the refund policy.
6. Write down the date, who did it and anything that was fixed.

### Common mistakes
- Typing the fee into **Approved extras** as well as the trip's Group Experience Fee box: the member is billed twice. (Trips 181 and 320 held their fee in Extras Cost before this change; their extras are set to $0 when their own fee is set.)
- Expecting a Travel price or quoted price to be paid on Gate 09: it never is.
- A member putting a child under "Additional adults" (or the reverse): the fee is then wrong for that member. Check the intake when a member asks about their amount.
- Entering the standard fee and expecting it to be billed straight away: it is billed only once **Group Experience Fee is live** is on, or when a trip has its own fee.

---

## 12. Server: the PHP error log

PHP's error log is kept **outside the website folder**, so it can never be downloaded from the web. (Set up 2026-10-08; confirmed working 2026-10-10.)

### Where it is
- **The log:** `/home/u2922-bn1ak4vn2swx/logs/php_errorlog` on the SiteGround server (the `logs` folder in the account's home folder, readable only by the account). Open it with SiteGround's **File Manager** (Site Tools > Site > File Manager, then go up to the home folder) or over SSH.
- **The setting:** **Site Tools > Devs > PHP Manager > PHP Variables**, variable `error_log`, value `/home/u2922-bn1ak4vn2swx/logs/php_errorlog`. SiteGround saves it as one line in `public_html/php.ini`:
  `error_log = /home/u2922-bn1ak4vn2swx/logs/php_errorlog`
  It covers the whole site, front end and wp-admin. Change it only in the PHP Manager, not by editing `php.ini` by hand.
- **Older logs** from before the change were moved on 2026-10-10, contents unchanged, to `~/logs/old/public_html-php_errorlog` (was `public_html/php_errorlog`) and `~/logs/old/wp-admin-php_errorlog` (was `public_html/wp-admin/php_errorlog`). The `old` folder and both files are readable only by the account. Nothing is left in `public_html` or `wp-admin`.
- **Not covered:** commands run on the server with WP-CLI still write any warnings to `php_errorlog` in the folder they run from (usually `public_html`). If a new `php_errorlog` appears there, it came from a WP-CLI command; the server still blocks that file name from the web (403).

### How it was confirmed
A one-time test page printed the error-log setting the website uses and wrote one test line. On 2026-10-10 it printed the `~/logs` path and the line `bagsandvibes log location test` landed in `~/logs/php_errorlog`. The test page was deleted straight away.

An earlier attempt used a `public_html/.user.ini` file; SiteGround's PHP does not read `.user.ini`, so it had no effect and was removed on 2026-10-10. Use the PHP Manager (or `php.ini`) for PHP settings on this hosting.

### Undo
In **PHP Manager > PHP Variables**, remove the `error_log` value (or reset it). PHP then goes back to SiteGround's default: a file called `php_errorlog` inside `public_html` (and `wp-admin`), still blocked from the web.

### Rules worth knowing
- After **any** change in the PHP Manager (PHP version, other PHP variables), check that `error_log` is still set to the `~/logs` path above. If it is missing or changed, put it back.
- Never put the log back inside `public_html`, and never make `~/logs` readable by others.
- The log can contain file paths and error details. Don't paste it into emails or chats outside the team.

---

## 13. Price board (Step 9)

The price board is the "Pick your cabin" section of the new page: one bar per cabin group, the all-in price per cabin for 2 travelers with the per-person price under it, and a "What's in this price?" link under each cabin. It is drawn from the **Pricing Tiers** box (section 9) and the trip's **Group Experience Fee** (section 10). Today's "Choose Your Option" price cards are left out of the new design. **The public page is unchanged** until the trip is switched live on the new design (section 1); check it with `?preview=new`.

### Where it is
- Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, then click the trip's name.
- The prices: **Pricing Tiers** box (Group, Badge, Note, Highlight this row, One price only, and each price point's **Price board column**). See section 9.
- **Price board (new design)** box (main column): an optional header note and two optional photos.
- On or off for a trip: the **Price board** row in the trip's Sections table (section 2).

### What the board shows, and where each part comes from
| On the page | Comes from |
|---|---|
| "Gate 18 · Fare classes" and "Pick your cabin" | The event type's words ("Pick your room" for a resort, and so on). The Gate number follows the sections above it. |
| Note beside the heading | Automatic, unless you type your own (see the box below). Automatic wording: "**All-in prices per cabin for 2 travelers:** cruise fare, taxes & fees, prepaid gratuities and Voyage Protection, plus the CBGV Group Experience Fee. Travel is booked by your travel advisor and paid directly to the cruise line." A part no price contains (for example no Voyage Protection anywhere) is left out; "plus the CBGV Group Experience Fee" only appears when the trip shows a fee. Other event types say "paid directly to the supplier". It never mentions a deposit amount or payment plans. |
| Coloured bar with the column names | The price columns the board actually uses (from the provider's **Price column names**). Columns no price uses are left out. |
| One bar per group, with "From $..." | Each tier's **Group**; "From" is the lowest all-in price in that group. Tap a bar to open it; opening one closes the others. **The group with the highlighted row starts open**; with no highlighted row, the first group does. |
| Cabin row: name, badge, note | The tier's name, **Badge** and **Note**. A highlighted row is tinted gold. |
| Price | The all-in price for the cabin for 2 travelers, with "$... per person" under it. When a column only has a price for another headcount, the line says so ("for 3 travelers"). A dash means that cabin has no price in that column. |
| Suites | **One price only** rows show a single price across the columns, marked "Suite fare" ("One price" for other event types). |
| "What's in this price?" | Opens the itemized all-in line for each of the cabin's prices: cruise fare, taxes & fees, prepaid gratuities, Voyage Protection and, when the trip shows a fee, **CBGV Group Experience Fee** as the fifth item. |
| Dark bar under the board | "Final payment due · [date]" from the trip's final payment key date (left out if the trip has none), and "Tips & protection included" on cruises, only when **every** price on the board includes both. |
| "Reserve a cabin" button | The trip's registration page (uses the trip code). |
| Note under the board | Only when the trip shows a CBGV Group Experience Fee: the approved fee and refund note, plus "Children pay half the CBGV Group Experience Fee." for per-traveler fees, or "One fee per booking, however many cabins you book together." for flat-per-booking fees (section 10). |

### When the fee shows on the board
- **The trip's own fee** (its Group Experience Fee box set to **Use a different fee for this trip**) **always shows on the board**, even while **Group Experience Fee is live** is off, because the member Payment page already bills that fee. Trip 181 ($225 per traveler) and Helmets ($150 per traveler) are set this way, so their boards show the fee.
- **The standard fee** (Settings > Group Experience Fees) shows only once **Group Experience Fee is live** is ticked, except in your own `?preview=new`.
- **Proposal PDFs** still include a fee only once **Group Experience Fee is live** is ticked, whichever kind it is.
- **No Group Experience Fee on this trip**: no fee and no note anywhere.

### The Price board (new design) box
| Field | What it does |
|---|---|
| **Header note** | Optional. Replaces the automatic note beside the heading. Up to 600 characters; **bold**, *italic*, links and tokens such as {date:final_payment} work. Leave blank to use the automatic note (recommended: it updates itself when the fee or the prices change). |
| **Photo 1** / **Photo 2** | Optional, e.g. a cabin and a deck. Shown beside the board on a computer and in a row above it on tablets and phones. No photos = the board runs full width. Choose them from the Media Library; each needs Alt Text there. Virgin pictures from the First Mates toolkit only, unaltered (section 4). |
| **Photo focus point** | Keeps the subject (and any logo) in view when the frame crops the picture, as in the other sections. |

### The boarding pass now
- **"Claim your seat"** (on the boarding pass at the top) jumps down to the price board, so people pick a cabin first; the board's **Reserve a cabin** button then goes to registration. When the board is hidden (Price board section Off, or no price left on it), "Claim your seat" goes straight to registration, as before.
- A faint CBGV logo coin now sits behind the pass text. It is decorative only (screen readers skip it) and nothing needs setting.

### Order to do things in
1. In **Pricing Tiers**, set each tier's Group, Badge, Note, Highlight and One price only, and each price point's **Price board column** (section 9). Put fares that can't be held in the group on **Not on the price board**.
2. Check the trip's **Group Experience Fee** box (section 10).
3. Optional: add the photos (and a header note) in the **Price board (new design)** box.
4. Click **Update**, then open the trip with `?preview=new` and open each group to check the prices and the "What's in this price?" lines.

### Rules worth knowing
- The board hides itself when its section is **Off** or when no price is left on it (every price "Not on the price board").
- Never type a deposit amount or "payment plans" into the header note; never name the host agency; never say CBGV books the cabin: **the travel advisor books it** and travel is paid directly to the cruise line or supplier.
- Prices on the board are for 2 adults. Children's half fee is explained in the note under the board.
- Today's public page and Gate 07 keep their old prices (no fee) until the trip is switched live on the new design.

### Common mistakes
- **Leaving every price untagged**: everything then sits in column 1 (on a Virgin trip, "Base"), and Lock-It-In tiers show as normal group prices. Trip 181 is like this today, waiting for its fare classes to be confirmed.
- Ticking **Highlight this row** on rows in two different groups: only the first of those groups starts open.
- Typing a header note with a fixed fee amount or date: it won't update when the trip changes. Use the automatic note or a token instead.
- Expecting the fee on the board for a trip that uses the **standard** fee while the fee is not live: it only shows in your own `?preview=new`.

---

## 14. Price notes, the old Reserve button, and members-only forums (Step 9a)

### Price notes
Two short, optional notes about prices and payment, in the **Price notes (current page and Gate 07)** box on the trip's edit screen (Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, open the trip).
- **Pricing note**: shown under the "Choose Your Option" prices on **today's** public trip page. Up to 600 characters; **bold** and [words](https://link) work. It is **not** shown on the new design: there, use the **Header note** in the Price board box (section 13).
- **Gate 07 card note**: one short line (up to 80 characters) under the price on the trip's card in Gate 07 (members only), e.g. "Pay in full or monthly with Uplift".
- Leave either blank and nothing shows.

### Wording rules for payment text (header note, pricing note, card note)
- Travel is booked by the travel advisor and paid directly to the cruise line or supplier. Never name the host agency, and never say CBGV books the cabin.
- **Virgin trips: Uplift may be named**, always with **"subject to approval"**. Never give monthly amounts or rates, and never promise approval.
- No deposit amounts unless they come from the trip's own data.
- Give the fee per traveler ("$150 per traveler", not just "$150"), matching the trip's fee.

### The old "Reserve Your Spot" button
On the **new design**, the old "Reserve Your Spot" button and its "This submits a request to join" line are gone: the price board and "Claim your seat" are the one way to book. The **Download Full Itinerary PDF** button stays where it was. If the Price board is switched **Off** for a trip, the old Reserve button comes back, so there is always a way to book. Today's public design is unchanged.

### Forums are members only
Anyone who isn't signed in and opens a forum page (the forum list, the Lounge, a trip board, a topic) or a forum feed is sent to the site's sign-in page (/login/) and brought back after signing in. Every gate's "Please sign in" link goes to the same /login/ page and back. Members see the forums as before. Forums are also hidden from search engines (Yoast, set 2026-10-11).

Every forum page (the forum list, each board, topics, replies and forum search) now uses the same CBGV header, menu, footer and fonts as the dashboard and gates, with a "GATE 10" ribbon; the forum list is titled "Discussion Boards".

Personal **walls** never appear in the forum lists, topic lists or forum search, for anyone (admins included). A member's wall lives on their profile page and in the Feed; who can open it is unchanged (the owner, admins, and anyone on a trip with the owner). Opening a wall's old forum address takes you to the owner's profile.

### Proposal PDFs and "Not on the price board"
A price tagged **Not on the price board** (in the Pricing Tiers box) is left off the **Client Proposal PDF** completely. A cabin tier whose prices are all tagged that way disappears from the client PDF, add-ons included. The **Internal Data Sheet** still lists everything, marked "Not on price board". Trip 181's three Locked In tiers work this way.

### Adults only (18+)
Some cruise lines take adults only (Virgin Voyages is 18+). On the provider's edit screen (Provider Library: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider, open the provider), the **Who can travel** box has an **Adults only (18+)** checkbox. Each trip's edit screen has an **Adults only (18+)** box: **Same as the provider** (the usual choice), **Yes, adults only**, or **No, children can travel**.

On an adults-only trip:
- the price board and proposal PDFs don't say "Children pay half the CBGV Group Experience Fee";
- the traveler form has no "Additional children" or "Children's ages" fields.

Prices and the fee per adult are unchanged.

### Rejection email replies
When you reject an applicant, Ultimate Member sends the rejection email (Ultimate Member > Settings > Email). Replies to it go to **LaDon@journeywellglobal.com**. Changing that address needs a code change; tell the developer.

### Common mistakes
- Putting the same payment text in the **Pricing note** and the **Disclaimer**: on today's page it then shows twice. Use the Pricing note for payment wording; keep the Disclaimer for fine print.
- Expecting the Pricing note on the new design: it isn't there. Use the Price board's Header note.
- A card note that is too long: it stops at 80 characters.

---

## 15. Travel Details form (members' trip registration)

Each trip member fills in **Travel Details** on the trip's page (the "Trip Registration" box). Besides the always-asked items (travel insurance decision and the Credit Card Authorization confirmation), it has four optional sections: **Air Travel, Cruise, Hotel and Car Rental**. A section appears when the trip's type includes it, or when the member ticks it under "Need something else for your own travel".

Rules (2026-10-13):
- Fields in a section that is **not shown are never required**. A member with every optional section turned off can save.
- When a section **is** shown, its starred fields are required.
- **Frequent flyer / loyalty number** is optional.
- **I have TSA PreCheck** is an optional checkbox in Air Travel. The site does **not** store Known Traveler Numbers.

Where you see the answers: the trip's **roster export** (Trips list: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, open the trip, Roster Export box). The export has a **TSA PreCheck** column (Yes / blank) right after Frequent Flyer Number.

### Common mistakes
- Expecting a Known Traveler Number in the export: it is never collected. Ask the traveler to give it to the travel advisor directly if a booking needs it.

---

## 16. Page design: Privacy, Terms, Contact, sign-in pages, trip pages (design Group A + B part 1)
What changed for visitors and members (no settings to change):
- **Privacy Policy, Terms of Service, Contact** use the members' page design: the dark CBGV header, the page title on the paper ribbon, and the text in a readable column. The text itself is still edited in the normal page editor (Pages > Privacy Policy, etc.). The ribbon shows the **page title**, so the first heading inside the text ("Privacy Policy", "Contact Us") is hidden on screen and kept for screen readers; you do not need to delete it.
- **Contact form** (WPForms) has brand fields and a coral **Submit** button. The form's own settings (who gets the emails) are unchanged.
- **Login, Register, Password Reset** (Ultimate Member forms) use brand fonts, coral main buttons and ink-outline second buttons. The forms work as before.
- **Register**: the empty grey box above "Read the Terms of Service" is gone. It comes back by itself if you ever put text into the Membership Terms (Settings > Membership Terms: https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-membership-terms).
- **Trip pages**: the "Discuss this trip" button now has a dark outline (it was cream on cream).
- **Signed in as an admin**: the CBGV header now sits below the black WordPress admin bar, so the logo is no longer covered.

### Who sees "Who's going" on a trip page
Only travelers on that trip's roster who have accepted the trip's current agreement, plus admins. Not the public, and not members who are not on the trip.

### Common mistakes
- Editing the ribbon text inside the page body: the ribbon is the page **Title** field.

---

## 17. Master Traveler Agreement, Register page and small wording fixes
- **New name:** what members accept at sign-up is the **Master Traveler Agreement, Code of Conduct & Travel Policies** (it used to be called "Membership Terms"). The version number is unchanged (still v1).
- **Its text is the Travel Rules page (Gate 12):** Pages > Travel Rules. Everyone (signed in or not) can read it at https://bagsandvibes.com/master-traveler-agreement/ (not listed in search engines). Editing the Travel Rules page updates that public page right away, but does **not** start a new version and asks nobody to re-accept.
- **Starting a new version (re-acceptance):** first change the text on the Travel Rules page, then go to Settings > Master Traveler Agreement (https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-membership-terms), tick "I understand every member will be asked to re-accept" and click **Publish version N**. Every member (you too) is asked to read and accept the agreement once, on their next page load. Leave the text box on that screen empty: anything typed there is shown in a box on the Register page. Version 2 (new Section 6, Emergency Medical Information & Consent) was published on 2026-10-14.
- **Register:** one checkbox, "I have read and agree to the Terms of Service and the Master Traveler Agreement, Code of Conduct & Travel Policies (v1)", both linked. Required fields show a red *.
- **Contact form:** Name and Email are full width.
- **Dashboard:** the Gate 09 card reads "Track your CBGV Group Experience Fee payments."

### Common mistakes
- Expecting an edit on the Travel Rules page to make members re-accept: it doesn't. Use the Settings screen above for a new version.

---

## 18. Trip rules = the Trip Agreement
Each trip has **one** rules text: its **Trip Agreement** (Trips: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip, open the trip, **Trip Agreement** box).
- Members see it on the trip page ("Rules for this trip") and on Gate 12 under "Rules for Your Trips", with its **version** and whether they accepted it ("You accepted version 4 on Oct 14, 2026" or "Not accepted yet").
- **Changing the text and saving starts a new version**: every traveler on the trip must accept it again before they see the trip's details.
- The old "Trip-specific rules addendum" box is gone. (Trip 181's old line, "Trip must be paid in full 120 days prior to sailing.", is kept in the database but no longer shown; its agreement v4 already says it.)

### Common mistakes
- Fixing a typo in the Trip Agreement: that also starts a new version and asks everyone to re-accept. Batch changes together.
- A trip with an empty Trip Agreement shows "No trip agreement for this trip." (Helmets today).

---

## 19. How to book and "Who does what" (Step 12a)
**Where:** Settings > How to Book & Disclosure (https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-how-to-book).
- **How-to-book steps:** the steps every trip shows in the new design, after the price board ("Gate nn · Check-in"). A trip whose provider has its own How-to-book steps shows those instead. Tick **Fee step** on the step about the CBGV Group Experience Fee: it is left out on a trip with no fee, and the heading's count adjusts ("Four steps to the gangway" on cruises, "Four steps to your trip" on other types). To remove a step, empty its title and text; two empty rows are always there for new steps. Each trip can switch the section off (Trip edit screen, Sections table, "How to book").
- **"Who does what" disclosure:** shown on every trip page (new design, old page, members' page) and on the Terms of Service page after section 2 (the page holds the short code `[cbv_who_does_what]`; don't delete it). "Last updated" changes by itself when you change the text. Plain text; a blank line starts a new paragraph.
- **Rules:** never write "CBGV books your cabin" (the travel advisor books the cabin) and never name InteleTravel on the site.
- **Installments with no deposit (Gate 09):** on a trip set to deposit + installments with a **$0 deposit**, the CBGV Group Experience Fee is split into equal payments, up to the number of installments (e.g. $225 over 3 = $75 each). With a deposit, the first payment is the deposit, as before.

### Common mistakes
- A provider with placeholder How-to-book steps (e.g. "Test Provider") replaces your steps on its trips: clear the provider's steps or switch the trip's provider.

---

## 20. The Virgin Voyages provider
**Where:** Providers (https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider) > **Virgin Voyages**. Created 2026-10-14 from the checked text (only lines verified with Virgin; the rest is listed in `docs/audits/virgin-provider-2026-10-14/dry-run.txt` and can be added one by one once verified).
- **Adults only (18+)** is ticked here, so every trip on this provider is adults only unless the trip's own Adults only box says otherwise.
- It has **no How-to-book steps** on purpose: its trips use the site's steps (Settings > How to Book & Disclosure). Adding steps here would replace those on every Virgin trip.
- It holds the price column names (Base, Essential, Premium), the What's included cards, 4 key dates (final payment 120 days before, last change day 46, payments final 45, name changes close 2) and 4 travel documents. Cards, key dates and documents show on the page as those sections are built.
- **Trip 181** uses it (Trip edit screen, landing settings box, Provider). The old "Test Provider" is in the trash; don't restore it.

### Common mistakes
- Typing a fact into a card or document that hasn't been checked with Virgin. Only add lines you have confirmed.

---

## 21. Countdown (key dates) and Travel documents
- **Countdown:** the trip's key dates, oldest first, with a coloured dot, the date, a title and a short note. They come from the trip's **provider** (Providers > e.g. Virgin Voyages > Key dates, counted in days before the trip's start date), changed or added per trip in the trip's own key-date box. Dates already past are dimmed and tagged "Passed".
- **Travel documents** ("The fine print, folded neatly"): the provider's documents (then any of the trip's own), as fold-open panels. Under the heading: "Key {provider} policies, in short. The cruise line's own terms always apply." The documents' "Last reviewed" dates stay admin-only.
- **Where they show:** on the new design (after How to book) and on the **members' trip page** (after "Rules for this trip"; a traveler sees them once they have accepted the Trip Agreement).
- **Switch off for a trip:** Trip edit screen, Sections table: "Timeline / key dates" and "Travel documents" (this hides them on both pages).

### Common mistakes
- Changing a date on the provider changes it for every trip on that provider: use the trip's own key-date box for a one-trip change.

---

## 22. Status board (the band under the hero)
It reads the trip's **Travelers booked (paid deposits)** number (Trip edit screen, "Status board & intro" box) and the trip's **Minimum group size** and **Capacity**:
- **Blank, or fewer than the minimum:** only the chip ("Boarding" on cruises), on the right. No number is shown.
- **The minimum or more:** "7 travelers booked" (other trip types: "guests", "attendees"...), the tiles "07 OF 50" (capacity; the minimum if no capacity is set) and the chip "On time".
- **At capacity:** the same, with the chip "Fully booked · Ask about the waitlist".
Update the number by hand as deposits come in; it never counts by itself.

---

## 23. What's included and Pack list
Two sections on the new design, after Travel documents (and on the **members' trip page** after the travel documents, once the traveler has accepted the Trip Agreement). Each hides when it has nothing to show.

**What's included** ("Every fare comes with extras"):
- **In your all-in price:** built automatically from the price board. A part (taxes & fees, prepaid gratuities, Voyage Protection, the CBGV Group Experience Fee) is listed only when **every** price on the board includes it. The fare itself reads just "Cruise fare" (no "group rate" wording).
- **Included by the cruise line:** the provider's fare cards (Providers > Virgin Voyages > fare cards), with the line under them ("Perks vary by fare...") in the same box's "Footnote under the cards" field. **The cards follow the trip's price board:** a fare card (Base, Essential, Premium...) shows only when the board has that fare column; a suite card only when the board lists one of its suites (the card's title or a name in its cabin line). A trip with no prices on its board yet shows every card. An optional "On every fare" line comes from the provider's **Pack list: Leave at home · and "On every fare"** box.
- **The CBGV group program:** per trip, Trip edit screen > **What's included & pack list** box, one item per line, plus a short note (e.g. "All events and times are subject to change.").

**Pack list** ("Pack List: Vibes Only"), Trip edit screen > **What's included & pack list** box:
- **Forecast:** the gold chip (e.g. "FORECAST · LOW 80s °F · WARM & TROPICAL").
- **Tags (up to 6):** category, title and items (one per line; `**bold**` works). They show as luggage tags numbered "Tag 01", "Tag 02"...
- **Leave at home:** the provider's list (Providers > **Pack list: Leave at home · and "On every fare"**: each line with its source link and the date you checked it; sources and dates are admin-only) plus any extra lines for this trip, then the link to the cruise line's full list (opens in a new tab).

**Other changes in this step:**
- The old **Highlights** block no longer shows on the new design (it still shows on the old page).
- **Route note:** the route box has a "Note under the route" field (small italics under the day cards).
- **Share image:** when the trip has a Yoast Social image set, that is the only image shared (trip 181: the hero poster).

**Switch off for a trip:** Sections table, "What's included" and "Pack list".

### Common mistakes
- Adding a Leave-at-home line you haven't checked on the cruise line's own site: always fill in the source and the date checked.
- Editing the provider's lists changes every trip on that provider: use the trip's own box for one-trip extras.

---

## Coming in later steps (this file will be extended after each)
- Registration opt-outs (gratuities and Voyage Protection) and the decline acknowledgment
- Upgrades; group perks
- How to book, member hint, travel documents editor on the trip
- Wedding sections; "Copy this trip"; unlisted trips with an access code; go-live checklist
