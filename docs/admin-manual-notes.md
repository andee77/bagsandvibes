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
The new design is being built one step at a time. **Right now the new page has the new hero and boarding pass, the status board, the intro, the route, the featured moment and the gallery at the top** (Steps 4 to 7): new header and footer, those sections, then today's trip content underneath. The remaining new sections (route, price board, included cards, timeline, travel documents and so on) are built in later steps. Everything you enter in the boxes below is saved and waiting; **most of it will not show on the page until the step that draws that section is finished.** This file tells you when each section starts showing.

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
- Expecting the board on the page now: it is drawn in Step 9.

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
| **Group Experience Fee is live** | Off (the default): the fee only shows in admin previews of the new design (`?preview=new`) and on these admin screens. **Proposal PDFs and public pages leave it out.** On: it is added to the price board and to proposal PDFs. |

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

PHP's error log is kept **outside the website folder**, so it can never be downloaded from the web. (Changed 2026-10-08.)

### Where it is
- **The log:** `/home/u2922-bn1ak4vn2swx/logs/php_errorlog` on the SiteGround server (the `logs` folder in the account's home folder, readable only by the account). Open it with SiteGround's **File Manager** (Site Tools > Site > File Manager, then go up to the home folder) or over SSH.
- **The setting:** one line in `public_html/.user.ini`:
  `error_log = /home/u2922-bn1ak4vn2swx/logs/php_errorlog`
  It covers the whole site, front end and wp-admin. After any change to the file, PHP takes up to **5 minutes** to pick it up.
- **Older logs** from before the change are still at `public_html/php_errorlog` and `public_html/wp-admin/php_errorlog`. The server blocks both from the web (403). Moving them is a separate, later step.
- **Not covered:** commands run on the server with WP-CLI still write any warnings to `php_errorlog` in the folder they run from (usually `public_html`).

### Undo
Delete `public_html/.user.ini`. Within about 5 minutes PHP goes back to SiteGround's default: a file called `php_errorlog` inside `public_html` (and `wp-admin`), still blocked from the web.

### Rules worth knowing
- **SiteGround's PHP Manager (Site Tools > Devs > PHP Manager) may rewrite PHP settings.** After **any** change there (PHP version, PHP variables), open `public_html/.user.ini` and check the `error_log` line is still exactly as above. If it is missing or changed, put it back.
- Never put the log back inside `public_html`, and never make `~/logs` readable by others.
- The log can contain file paths and error details. Don't paste it into emails or chats outside the team.

---

## Coming in later steps (this file will be extended after each)
- Price board on the page (Step 9)
- Registration opt-outs (gratuities and Voyage Protection) and the decline acknowledgment
- Included cards and upgrades; pack list, group perks and timeline
- How to book, member hint, travel documents editor on the trip
- Wedding sections; "Copy this trip"; unlisted trips with an access code; go-live checklist
