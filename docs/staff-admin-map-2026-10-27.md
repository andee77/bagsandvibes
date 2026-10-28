# Checked Bags & Good Vibes: staff & admin map

*Read-only survey of bagsandvibes.com, 27 Oct 2026 (updated as the gap fixes go live), written as source material for the staff manual. Nothing on the site was changed. Every URL below was confirmed on the live site, either from the live admin menu or from the live page list. No passwords, keys or member personal data are included.*

---

## Before you start: five things every staff member must know

1. **Staff need the Administrator role.** The Editor role cannot use the dashboard: Ultimate Member blocks it from wp-admin. Every CBGV settings screen also requires Administrator.
2. **Gate numbers vs. file names are swapped.** Use the page names and URLs, never the code file numbers:
   - **Gate 11 = Vacation Requests**: https://bagsandvibes.com/gate-11-vacation-requests/
   - **Gate 12 = Travel Rules**: https://bagsandvibes.com/gate-12-travel-rules/
3. **Staff emails (since 28 Oct 2026).** These go to the **Staff notifications email** set in Settings > CBGV Notifications (blank = the site Administration Email):
   - a new Gate 11 Vacation Request;
   - a Lead Client accepting a quote;
   - a Trip Guest's first Full Membership request;
   - a traveler first saving, or later changing, their Trip Registration.

   Contact-form messages and Schedule Appointment requests still go to the site **Administration Email Address** (Settings > General).
4. **Refunds made in Stripe do not show on the site.** The site keeps showing the payment as paid.
5. **Contact-form messages are kept for 90 days only** in Tools > CBGV contact log (https://bagsandvibes.com/wp-admin/tools.php?page=cbv-contact-log) (since 28 Oct 2026). Save anything you need longer from the email.

---

## 1. Members

### Roles

| Role | What they can do |
|---|---|
| **Administrator** | Everything in wp-admin. Sees every trip without being on its roster. Lands in wp-admin after login. |
| **Full Member** (WordPress role "Subscriber") | Full My Passport with all gates, including Gate 11 Vacation Requests. |
| **Trip Guest** (`trip_guest`) | Only their own trip(s) on My Passport, plus a **Request Full Membership** button. Cannot use Gate 11 Vacation Requests. |
| **Forum Participant** (added alongside the role above) | Can post in the Lounge and in their trip's discussion board. |
| Editor / Author / Contributor / SEO roles | Exist but unused; Editors can't reach the dashboard. |

### How people join

**A. Personal invite link → Trip Guest. No staff step.**
1. A member on a trip clicks **Generate invite link** on their trip card in https://bagsandvibes.com/my-passport/ and shares the link or QR code.
2. The guest opens the link (https://bagsandvibes.com/join/), then registers at https://bagsandvibes.com/register/. They must tick the Terms of Service / Master Traveler Agreement checkbox.
3. The site makes them a **Trip Guest**, adds them to that trip's roster and **approves them automatically**.
4. **There is no email-confirmation step**, despite the "confirm email" step people may expect.

**B. Public trip QR or code, or plain registration → Full Member, after admin approval.**
1. The person registers. Their account is **Pending Review**, and the trip they came from is saved as a "trip interest".
2. Ultimate Member sends the applicant a "your account is under review" email and the admin a "New user awaiting review" email.
3. **Staff approve:** go to Users at https://bagsandvibes.com/wp-admin/users.php, hover over the user, and click **Approve** (or **Reject**). Bulk actions offer the same choices.
4. On approval the member gets the Ultimate Member "You're Cleared for Boarding" email plus the custom founder welcome email. Their dashboard then highlights the trip they came from.

**Login:** https://bagsandvibes.com/login/
- After login, members and trip guests land on the **Feed**, https://bagsandvibes.com/member-feed/.
- My Passport is at https://bagsandvibes.com/my-passport/.
- **Agreement re-acceptance:** members behind the current Master Traveler Agreement version are sent to https://bagsandvibes.com/reaccept-terms/ until they accept.

### Turning a Trip Guest into a Full Member
1. The guest clicks **Request Full Membership** on My Passport. Staff get a **"Full Membership request"** email (first request only) with a link to Trip Guests.
2. Go to Users > Trip Guests: https://bagsandvibes.com/wp-admin/users.php?page=cbv-trip-guests. Guests who asked are highlighted at the top.
3. Click **Promote to Full Member**. Their trips and history stay the same. **No email is sent**, so tell them yourself.

### Password resets
- Members reset their own password at https://bagsandvibes.com/password-reset/. They get a "Reset your password" email, then a "your password has been changed" email. The limit is 3 attempts.
- Members can also change their password at https://bagsandvibes.com/account/ (Change Password tab).

### Deactivating, rejecting, deleting
All of these are on Users (https://bagsandvibes.com/wp-admin/users.php): hover over the user, or use bulk actions.

| Action | Effect | Email to the member |
|---|---|---|
| **Deactivate / Reactivate** | Turns access off or back on | "Your account has been deactivated" |
| **Reject** (applicant) | Refuses a pending applicant. Replies go to an address set in code; the developer changes it. | "An update on your … application" |
| **Delete** | Removes the account and takes them off every trip roster automatically | "Your account has been deleted" |

- **Members can delete themselves** from https://bagsandvibes.com/account/ (Delete Account tab, password required).
- **Remove someone from one trip only:** open the trip, find the roster in the **Trip Details** box, and click **Remove**.

### Ultimate Member screens
- Dashboard: https://bagsandvibes.com/wp-admin/admin.php?page=ultimatemember
- Settings, including the **Email** templates (the one Ultimate Member area staff may edit): https://bagsandvibes.com/wp-admin/admin.php?page=um_options
- Forms (do not edit): https://bagsandvibes.com/wp-admin/edit.php?post_type=um_form
- User Roles, which hold the login redirects (do not edit): https://bagsandvibes.com/wp-admin/admin.php?page=um_roles

### Where member data lives
- **The account:** a normal WordPress user, plus Ultimate Member's `account_status`.
- **Master Traveler Agreement acceptance:** `_accepted_terms_version` and `_accepted_terms_date`.
- **Invites:** `_invited_by_user_id`, `_invited_by_trip_id` and `_trip_interest`.
- **Full-membership request:** `_requested_full_membership_date`.

Staff can see the agreement version and date, who invited the member, trip interest and their full-membership request in the admin-only **"Trip-Invite Build — Field Status"** panel on each user's edit screen.

---

## 2. Planned trips

### Where
- Trips: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip
- Add New Trip: https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_trip
- Trip Types: https://bagsandvibes.com/wp-admin/edit-tags.php?taxonomy=cb_trip_type&post_type=cb_trip
- Provider (Cruise Line) Library: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider. A provider must be **Published** before a trip can use it.

### Two kinds of "status"
1. **WordPress Publish / Draft:** whether the trip exists publicly.
2. **CBGV Status** (in the **Trip Details** box): Requested → Quoted → Accepted → **Active** → Completed, or Declined.
   - **Active** trips appear on Gate 07: https://bagsandvibes.com/gate-07-pre-planned-vacations/
   - Making a trip Active for the first time creates its private discussion board automatically.

### Making a trip live: steps
1. Create and fill in the trip: dates, capacity, pricing tiers, itinerary, Trip Type, Provider, Group Experience Fee box, Gate 09 Payment Settings, Trip Agreement and so on.
2. Click **Publish** and set **Status = Active**.
3. In the **Trip Code & Visibility** box:
   - **Trip code:** suggested automatically and kept unique. **Never change it once shared**, because that breaks printed QR codes and links.
   - **Visibility:** Public, or Private (invite-only).
   - **Create Public Landing Page:** Public trips only.
4. The public landing page is at `https://bagsandvibes.com/trip/{trip-slug}/` (for example https://bagsandvibes.com/trip/annual-family-and-friends/).
   - **New design:** shown only when the trip's **Use new landing design** box is ticked. The site-wide new-design switch is already on, and it's developer-only.
   - **Previewing:** admins can add `?preview=new` to the trip address to see the new design before switching it on.
5. **Who sees what at the trip address:**
   - Roster members and admins see the private trip page. Members must accept the trip's agreement first.
   - Everyone else sees the public landing page, or a sign-in message.

### Trip link and QR code
- **Public join QR:** in the trip's **Trip Code & Visibility** box (Public trips only). It points to `https://bagsandvibes.com/join/?trip=CODE`. Right-click the image to save it for flyers. Private trips have none.
- **Personal invite link and QR:** a roster member, or an admin, clicks **Generate invite link** on the trip card in My Passport.
  - The link is reusable and never expires.
  - There is no screen to list or revoke these links.

### How members get onto a trip (the roster)
- Registering through a personal invite link (automatic).
- Clicking "join" on Gate 07 (refused when the trip is full).
- Accepting a Gate 11 quote.
- **Admins cannot add someone by hand**; they can only **Remove**.
- Registering through a *public* code only records interest. It does not add the person to the roster.

### My Passport trip cards
Each trip the member is on shows:
- a cover photo, with **Change / Add a cover photo** (preset or upload)
- **View trip**
- **Generate invite link**

Cover photo presets are managed at Settings > Cover Photo Presets: https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-cover-presets

### Roster export
Open the trip, go to the **Export Roster** box and click **Export Roster to Excel**. This downloads a spreadsheet with one row per traveler:
- contact details, emergency contact and passport details
- role, who invited them, and agreement versions
- the travel price ("Travel Price (reference only)", from the trip's Travel price field; never billed) and payment status
- insurance waiver and credit-card authorization flags
- all Trip Registration answers

Passports expiring within 6 months of the trip are highlighted. It's a sensitive file, so store and share it carefully.

---

## 3. Trip Registration (traveler intake)

- **Where members fill it in:** the **Trip Registration** section of the private trip page. Only roster members see it.
- **When it disappears:** once staff mark both **Insurance Waiver Received** and **CC Auth Received** for that traveler in the trip's roster.
- **What's collected:**
  - **Opt-in checkboxes:** Air Travel, Cruise, Hotel, Car Rental. Package Tour sections show automatically when the trip is tagged that way.
  - **Air:** seat preference, departure airport, cabin class, preferred airline, loyalty number, TSA PreCheck yes/no.
  - **Cruise:** company, dates, duration, region, port, **pre/post cruise nights** (the only "hotel before/after" question), cabin class, beverage plan.
  - **Hotel:** nights, preferences, rooms, room type.
  - **Car:** preferences, add-ons, category.
  - **Travel insurance:** accept or decline, plus the Allianz waiver download.
  - **Credit card authorization:** a form download and a confirmation checkbox. Card numbers are never collected on the site.
  - **Companions:** additional adults and children, names and ages. The children fields are hidden on adults-only trips.
- **Where it's stored:** on each traveler's account, one record per trip (`_traveler_intake_{trip}`).
- **Who can see it:** the member, and staff **only through Export Roster to Excel**. No admin screen shows it.
- **How requests reach the travel advisor (since 28 Oct 2026):** each time a traveler first saves, or later changes, their Trip Registration:
  - **The travel advisor** (Settings > CBGV Notifications) gets a **"Travel requests"** email: the traveler's name and email address, the extras they ticked, their answers for each section (ticked, or part of the trip type), loyalty numbers, and companions' names and ages. **No passport, card, insurance or medical details**, and no phone number.
  - **Staff** get a short "Trip Registration submitted/updated" email.
  - Saving with no changes sends nothing.
- **Export Roster to Excel** now has **Requested: Air / Cruise / Hotel / Car / Package Tour** columns: **Yes** = the traveler ticked it, **Trip type** = it comes with the trip, blank = neither. Package Tour can't be ticked; it only comes from the trip type.
- **Fixed 28 Oct 2026:** the export's old blank "Trip Price" column is now **"Travel Price (reference only)"** and shows the trip's Travel price field (blank while that field is 0).

---

## 4. Gate 11 Vacation Requests (group trips)

1. **Request arrives.** A Full Member, the Lead Client, completes the form at https://bagsandvibes.com/gate-11-vacation-requests/. A minimum of 4 travelers applies.
   - The site creates a **Trip** with Status **Requested**, saves every answer, and puts the requester on the roster.
   - Staff get a **"New Vacation Request"** email with a link to the trip.
2. **Find it.** Check Trips (https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip) for Status "Requested". There is no separate requests list or filter.
   - Open it: **Trip Details** shows Source "Member-built", and the **"Gate 12 — Member Request Details"** box shows every answer. (The box name uses the old gate number.)
3. **Send the quote.** In Trip Details, fill in:
   - **Quoted travel price per person** (reference only; it is never charged on the site)
   - **Quote / plan notes**

   Then set **Status = Quoted** and click Update. The Lead Client sees a quote card and an **Accept this quote** button on Gate 11. **No email is sent**, so tell them yourself.
4. **Optional branded proposal PDF.**
   - Go to Proposals > Add New Proposal (https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_proposal; the list is at https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_proposal).
   - Pick the trip and click **Download Client Proposal PDF**, then email it yourself.
   - Never share the **Internal Data Sheet**; it is for staff only.
   - Standard proposal text is set at https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_proposal&page=cb-proposal-boilerplate
5. **Lead Client approves.** They click **Accept this quote** and the Status becomes **Accepted**. Staff get a **"Quote accepted"** email with a link to the trip.
6. **Make it live.** Finish building the trip and set **Status = Active** (see section 2).
7. **Link and QR for the Lead Client.**
   - They're already on the roster, so the trip appears on their My Passport, where they click **Generate invite link** to get the link and QR code to share.
   - For a Public trip, staff can also save the public QR from the trip's Trip Code & Visibility box.
   - Nothing is emailed, so tell the Lead Client where to find it.

---

## 5. Payments

### CBGV Group Experience Fee
- **How it's charged:** custom code using **Stripe Checkout**; no payment plugin is involved. Stripe is in **live mode**. Members pay on https://bagsandvibes.com/gate-09-payments/, and must accept the Payment Disclaimer first.
- **What it bills:** only the trip's Group Experience Fee for the member's party (full price per adult, half per child) plus approved extras. It refuses anything above that limit. **Travel costs are never charged on the site.**
- **Per-trip setup:**
  - The trip's **Group Experience Fee (new design)** box.
  - The **Gate 09 — Payment Settings** box: full payment or deposit + installments, number of installments, approved extras, and the next Travel Payment due date (display only).
- **Standard fees:** Settings > Group Experience Fees, https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-experience-fees. This screen also holds the **Payment safeguard log** and the owner-only **"Group Experience Fee is live"** switch, which is currently off.

### Payment history
- **In Stripe:** https://dashboard.stripe.com/payments. This is the master record.
- **In WordPress:** the trip's **Export Roster to Excel** (columns Payment Status, Amount Paid, Paid in Full). There is no payments list screen.

### Refunds
- **Done in the Stripe dashboard only**, back to the original card.
- ⚠ **A refund in Stripe does not change what the site shows**: the member still shows as paid. This needs a developer fix. Until then, keep a refund log and reconcile it monthly.

### Schedule Appointment (Travel Payment)
- **What members do:** on Gate 09, members click **Schedule Appointment** (page-level, or per trip), then enter a preferred time and notes.
- **Email:** each request emails **"New appointment request: {member}"** to the site Administration Email Address. The member gets an on-screen confirmation only.
- **Where requests are:** Appointment Requests, https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_appt_request. Open a request and change its **Status**:

| Status in admin | What the member sees on Gate 09 under "Travel Payment" |
|---|---|
| New | Requested |
| Contacted | Advisor reached out |
| Scheduled | Scheduled |
| Done | Complete |

- **Travel Payment tracking** is just this status plus the optional "next Travel Payment due" date. **CBGV never collects or records travel money**; that goes to the travel advisor or the supplier.

### Payment Disclaimer
- **Edit it at:** Settings > Payment Disclaimer, https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-payment-disclaimer
- **Versioning:** saving changed text with **Save & publish new version** makes **every member accept again** on their next Gate 09 visit.
- **Public copy:** https://bagsandvibes.com/payment-disclaimer/
- **Who has accepted it:** Users > Disclaimer Acceptance, https://bagsandvibes.com/wp-admin/users.php?page=cbv-disclaimer-acceptance (added 28 Oct 2026). It shows each member's accepted version and date, lists those not on the current version first ("Never accepted" or "Older version"), and has a **Download CSV** button. Read-only. Admin accounts appear only if they're on a trip roster.

### Exchange rates
These appear on Gate 09 for reference only. They update automatically every 24 hours, and staff have no settings for them.

---

## 6. Gate 12 and other forms

**Gate 12 Travel Rules** (https://bagsandvibes.com/gate-12-travel-rules/) is read-only, with **no form**. The "Build Your Own Trip" form is Gate 11 (section 4).

| Form | Where | Goes to | Email |
|---|---|---|---|
| Contact form | https://bagsandvibes.com/contact/ | Tools > CBGV contact log (90 days) | To the site Administration Email Address (section 9) |
| Schedule Appointment | Gate 09 | Appointment Requests | To the site Administration Email Address |
| Vacation Request | Gate 11 | A new Trip (Requested) | None |
| Trip Registration | Trip page | The member's account, read through Export Roster | None |
| Join a trip | Gate 07 | Trip roster | None |
| Request Full Membership | My Passport | The member's account, read through Users > Trip Guests | None |
| Register / Login / Password reset | https://bagsandvibes.com/register/, https://bagsandvibes.com/login/, https://bagsandvibes.com/password-reset/ | Ultimate Member | Ultimate Member emails (section 1) |
| Photo upload | Gate 08 | Media Library | None |

**Trip Suggestions** has no member form any more, and since 28 Oct 2026 it is hidden from the admin menu. Its data is kept, and it still opens at https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_suggestion.

---

## 7. Moderation: boards, photos, Feed

### Discussion boards (Gate 10, bbPress)
- **Member view:** https://bagsandvibes.com/gate-10-discussion-boards/ shows the **Lounge**, which every member can see, plus the member's own **trip boards**, which are private to each trip's roster.
- **Posts appear immediately**; there is no approval queue. Posts with more than 2 links may be held as Pending, under Settings > Discussion (https://bagsandvibes.com/wp-admin/options-discussion.php).
- **To edit, close, stick, mark as spam or trash:**
  - Topics: https://bagsandvibes.com/wp-admin/edit.php?post_type=topic
  - Replies: https://bagsandvibes.com/wp-admin/edit.php?post_type=reply
  - Hover a row for the actions. Trash first; "Delete Permanently" can't be undone.
- **Forums list:** https://bagsandvibes.com/wp-admin/edit.php?post_type=forum. **Do not rename, unhide or delete trip boards or member walls**, because access depends on them.
- **Forum settings:** https://bagsandvibes.com/wp-admin/options-general.php?page=bbpress
- **Repair tools**, only if counts look wrong: https://bagsandvibes.com/wp-admin/tools.php?page=bbp-repair
- **Emails:** members who subscribe to a forum or topic get bbPress's standard emails about new posts.

### Photo gallery (Gate 08)
- **Member view:** https://bagsandvibes.com/gate-08-photo-gallery/. Photos appear immediately; there is no approval.
- **Gallery privacy:** each trip has a **Photo gallery privacy** setting, Public or Private, in its Trip Details box.
- **To remove a photo:** go to Media > Library (https://bagsandvibes.com/wp-admin/upload.php), switch to list view (the "Uploaded to" column shows the trip), open the photo and choose **Delete permanently**. This can't be undone.
- ⚠ **Upload staff images first:** add them through Media > Add Media File (https://bagsandvibes.com/wp-admin/media-new.php), then choose them in the trip's boxes. Images uploaded from inside a trip's edit screen can also appear in that trip's member gallery.

### Feed (https://bagsandvibes.com/member-feed/)
- **Trip updates:** open the trip and use the side box **"Trip updates (shown on members' Feed)"**. Up to 5 short messages, each with an optional end date. Only that trip's travelers see them.
- **Trip Tips:**
  - Add: https://bagsandvibes.com/wp-admin/post-new.php?post_type=cb_tip
  - List: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_tip
  - The newest 6 show on the Feed.
- **Podcast:** episodes appear automatically. The feed address and the "Listen on" links are at Settings > CBGV Member Features: https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-member-features
- **Boards and open trips:** automatic.
- **Social features** (walls, follows, members directory) are **switched off** on that same settings screen. Nothing was deleted.

---

## 8. Travel rules agreements

### Master Traveler Agreement (site-wide)
- **The text** is the body of the **Travel Rules** page. Edit it under Pages: https://bagsandvibes.com/wp-admin/edit.php?post_type=page
- **Public copy:** https://bagsandvibes.com/master-traveler-agreement/
- **Editing the page text does not make members re-accept.**
- **To make everyone re-accept**, go to Settings > Master Traveler Agreement (https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-membership-terms), tick the confirmation box and click **Publish version N**. Leave that screen's text box empty. Every member, staff included, is then sent to https://bagsandvibes.com/reaccept-terms/ until they accept.

### Per-trip Trip Agreement
- **Where:** open the trip and use the **Trip Agreement** box.
- ⚠ **Any saved change creates a new version.** Every traveler on that trip must re-accept before they can see the trip, so batch typo fixes together.

### Who has agreed
- **Per member:** the "Trip-Invite Build — Field Status" panel on their user screen (Master Traveler Agreement only).
- **Per trip:** Export Roster to Excel (Master Traveler Agreement and Trip Agreement versions and dates).
- There is no site-wide report.

No emails are sent when agreements change or are accepted.

---

## 9. Contact form (WPForms Lite)

- **The form:** one form, "Contact Form" (Name, Email, Message), on https://bagsandvibes.com/contact/
- **Where messages go:** one email, "New Entry: Contact Form", to the site **Administration Email Address**. Hitting Reply goes to the sender.
- **Stored copy (added 28 Oct 2026):** Tools > CBGV contact log (https://bagsandvibes.com/wp-admin/tools.php?page=cbv-contact-log). Admins only. Each message is saved before the email is sent, and the **Email status** column shows **emailed** or **email failed** with the reason, so a failed email doesn't lose the message. Kept 90 days (last 100 messages), then deleted automatically by a daily clean-up. WPForms' spam check runs first, so rejected spam isn't stored. Messages sent before 28 Oct 2026 aren't there.
- **Changing who receives them:** WPForms > All Forms (https://bagsandvibes.com/wp-admin/admin.php?page=wpforms-overview), open Contact Form, then Settings > Notifications.
- **Email delivery:** all site email goes out through WP Mail SMTP using Gmail. If email seems broken, send a test from https://bagsandvibes.com/wp-admin/admin.php?page=wp-mail-smtp-tools. Don't change the settings.

---

## 10. Screens and shortcodes staff use

### Custom settings screens
| Screen | URL | Use |
|---|---|---|
| Group Experience Fees | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-experience-fees | Standard fees, child age, safeguard log (the "live" switch is owner-only) |
| How to Book & Disclosure | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-how-to-book | Site-wide how-to-book steps and "Who does what" text |
| CBGV Member Features | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-member-features | Social on/off, podcast links |
| Payment Disclaimer | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-payment-disclaimer | Disclaimer text and version |
| Master Traveler Agreement | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-membership-terms | Publish a new agreement version |
| Cover Photo Presets | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-cover-presets | Trip cover photo choices |
| Auto-Follow Account | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-auto-follow | No effect while social features are off |
| Trip Guests | https://bagsandvibes.com/wp-admin/users.php?page=cbv-trip-guests | Guest list, Promote to Full Member |
| Disclaimer Acceptance | https://bagsandvibes.com/wp-admin/users.php?page=cbv-disclaimer-acceptance | Who accepted which Payment Disclaimer version; CSV download |
| Proposal boilerplate | https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_proposal&page=cb-proposal-boilerplate | Standard proposal text |
| CBGV Notifications | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-notifications | Staff notifications email and Travel advisor email (blank = site Administration Email) |

### Custom content lists
- Trips: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_trip
- Provider Library: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_provider
- Proposals: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_proposal
- Appointment Requests: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_appt_request
- Travel Tips: https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_tip
- Trip Suggestions and Destination Inspiration are **hidden from the admin menu** (since 28 Oct 2026) because neither is used on the site. Their data is kept, and they still open by direct URL:
  - https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_suggestion
  - https://bagsandvibes.com/wp-admin/edit.php?post_type=cb_destination

### Shortcodes
Each one already sits on its page. **Don't remove them.**

| Shortcode | Page |
|---|---|
| `[cb_feed]` | https://bagsandvibes.com/member-feed/ |
| `[cb_gate_vacations]` | https://bagsandvibes.com/gate-07-pre-planned-vacations/ |
| `[cb_gate_gallery]` | https://bagsandvibes.com/gate-08-photo-gallery/ |
| `[cb_gate_payments]` | https://bagsandvibes.com/gate-09-payments/ |
| `[cb_gate_boards]` | https://bagsandvibes.com/gate-10-discussion-boards/ |
| `[cb_gate_requests]` | https://bagsandvibes.com/gate-11-vacation-requests/ |
| `[cb_gate_rules]` | https://bagsandvibes.com/gate-12-travel-rules/ |
| `[cbv_join]` | https://bagsandvibes.com/join/ |
| `[cbv_payment_disclaimer]` | https://bagsandvibes.com/payment-disclaimer/ |
| `[cbv_master_traveler_agreement]` | https://bagsandvibes.com/master-traveler-agreement/ |
| `[cbv_reaccept_terms]` | https://bagsandvibes.com/reaccept-terms/ |
| `[cbv_who_does_what]` | https://bagsandvibes.com/terms-of-service/ |
| `[cb_find_members]`, `[cb_following_list]` | https://bagsandvibes.com/members/, https://bagsandvibes.com/following/ (show an "off" notice while social features are off) |

---

## 11. Never touch (owner or developer only)

| Area | URL | Why |
|---|---|---|
| Plugins | https://bagsandvibes.com/wp-admin/plugins.php | Turning off Ultimate Member, bbPress or WPForms breaks sign-in, boards or the contact form |
| Themes / Customize / Site editor | https://bagsandvibes.com/wp-admin/themes.php, https://bagsandvibes.com/wp-admin/customize.php, https://bagsandvibes.com/wp-admin/site-editor.php | Breaks the CBGV layouts |
| Kadence settings | https://bagsandvibes.com/wp-admin/admin.php?page=kadence-blocks | Site-wide design |
| Group Experience Fee **live switch** | https://bagsandvibes.com/wp-admin/options-general.php?page=cbv-experience-fees | Starts public fee billing; owner only |
| Stripe keys and webhook | No admin screen (server config) | Payments stop working |
| Ultimate Member Settings (except Email templates), User Roles, Forms | https://bagsandvibes.com/wp-admin/admin.php?page=um_options, https://bagsandvibes.com/wp-admin/admin.php?page=um_roles, https://bagsandvibes.com/wp-admin/edit.php?post_type=um_form | Breaks sign-up, sign-in and redirects |
| Changing a user's role by hand | https://bagsandvibes.com/wp-admin/users.php | Use **Promote to Full Member** instead |
| Speed Optimizer (caching) | https://bagsandvibes.com/wp-admin/admin.php?page=sg-cachepress | Can break pages or show stale content (Cloudflare caches too) |
| Security Optimizer, including Post-hack Actions | https://bagsandvibes.com/wp-admin/admin.php?page=sg-security, https://bagsandvibes.com/wp-admin/admin.php?page=post-hack-actions | Can lock everyone out or force log-outs |
| SiteGround AI Agent | https://bagsandvibes.com/wp-admin/admin.php?page=sg-ai-studio | Can change the site by itself |
| UpdraftPlus (backups) | https://bagsandvibes.com/wp-admin/options-general.php?page=updraftplus | "Restore" overwrites the live site |
| WP Mail SMTP settings | https://bagsandvibes.com/wp-admin/admin.php?page=wp-mail-smtp | Stops all site email |
| Settings > General (Administration Email), Reading, Permalinks | https://bagsandvibes.com/wp-admin/options-general.php, https://bagsandvibes.com/wp-admin/options-reading.php, https://bagsandvibes.com/wp-admin/options-permalink.php | Breaks email routing, the homepage or every gate and join URL |
| Yoast SEO | https://bagsandvibes.com/wp-admin/admin.php?page=wpseo_dashboard | Could expose member-only pages to search engines |
| bbPress Reset / Import / Upgrade | https://bagsandvibes.com/wp-admin/tools.php?page=bbp-reset | **Reset deletes every forum post** |
| Tools: Export / Erase Personal Data, Import / Export | https://bagsandvibes.com/wp-admin/erase-personal-data.php, https://bagsandvibes.com/wp-admin/export.php | Data-protection actions; owner sign-off |
| Master Traveler Agreement "Publish version", trip Trip Agreement box | see section 8 | Forces everyone to re-accept |
| Trip code, after sharing | Trip Code & Visibility box | Breaks printed QR codes and links |
| Appearance > Menus | https://bagsandvibes.com/wp-admin/nav-menus.php | Has no effect; the member menu is in code |

---

## Gaps worth fixing later (none changed)

1. ~~No staff emails for new vacation requests, quote acceptances, full-membership requests or Trip Registration answers.~~ **Done 28 Oct 2026** (Settings > CBGV Notifications).
2. ~~Trip Registration's flight, hotel and car requests reach the advisor only through the roster spreadsheet, and the opt-in checkboxes themselves aren't in it.~~ **Done 28 Oct 2026:** travel advisor email and Requested columns in the export.
3. Stripe refunds don't update the site's paid amount.
4. ~~Contact-form messages aren't stored; email is the only copy.~~ **Done 28 Oct 2026:** Tools > CBGV contact log, 90 days.
5. ~~There's no admin view of who accepted the Payment Disclaimer.~~ **Done 28 Oct 2026:** Users > Disclaimer Acceptance, with CSV.
6. There's no admin "add member to trip" control.
7. Invite links can't be listed or revoked.
8. ~~Trip Suggestions and Destination Inspiration are leftover screens with no member-facing use.~~ **Done 28 Oct 2026:** both are hidden from the admin menu, and their data is kept.
9. The existing `docs/admin-manual-notes.md` is out of date in places:
   - The new-design master switch is ON.
   - The roster box is called "Export Roster".
   - Travel Tips uses "Add Post".

---

## Test data (staff testing, Oct 2026)
- **CBGV TEST TRIP** (#458): https://bagsandvibes.com/wp-admin/post.php?post=458&action=edit. Private, Cruise, Status Accepted (not on Gate 07, no discussion board). Its roster is the staff test account #109 only. Delete it when testing is finished.
