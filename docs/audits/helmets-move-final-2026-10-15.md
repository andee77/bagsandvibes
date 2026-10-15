# Helmets → Annual Family and Friends move: FINAL plan + email draft (2026-10-15)

**Status: DONE 2026-10-15** (owner's OK the same day; the sign-off was changed to "The Checked Bags & Good Vibes Team").

## Results (2026-10-15, ~18:42–18:44 UTC)
- **Helmets minimum: 4 → 3**, saved through its trip box. Only `cb_min_group_size` changed. Snapshots: `~/logs/old/trip320-{before,after}-min-20261015-184227.json`.
- **Move done:**
  - Trip 181 roster: 1, 105, 106, 97, 98, 99, 108 (7 of 50).
  - Helmets roster: 1, 105, 106 (3 of 25).
  - The stored rosters match the plan. Undo copy: `~/logs/old/roster-181-320-before-20261015-184235.json`.
- **Checked as LaTanya P (user 97):**
  1. Signing in sends her to `/reaccept-terms/` (Master Traveler Agreement v2).
  2. Trip 181 then shows the Travel Details form and the Trip Agreement v4 checkbox ("Continue to trip details"). Trip details stay hidden until she accepts.
  3. The Payment Disclaimer comes before payment.
  4. Helmets shows her only its public landing page.
  5. The Annual Family and Friends board (183) is readable; the Helmets board (322) is not.
- **Emails:** 4 sent at 18:44 UTC, one per member, from the site's normal sender with Reply-To LaDon@journeywellglobal.com. `wp_mail` accepted all four, with no mail errors in the PHP error log. Send log (masked addresses): `~/logs/old/helmets-move-emails-*.txt`.
- Caches purged (SiteGround + Cloudflare). Updates the 2026-10-14 plan (`helmets-move-plan-2026-10-14.md`) after a fresh read-only check today.

## Re-checked today (read-only)
- **Who moves:** LaTanya P (97), Brenda N (98), Laurie W (99), Joshua K (108).
  - Each is on Helmets and not on trip 181.
  - Each has an account with an email address.
  - None has anything on Helmets to carry over: no payments, no Trip Agreement, no Travel Details, no board posts.
- **Who stays:** Janae Y (105) and Reginald R (106) remain on both trips, and Andee P (1) is unchanged.
- **Dry run of the move script** (nothing written):
  - Trip 181: from 3 to **7 of 50** travelers.
  - Helmets: from 7 to **3 of 25** travelers.
- **No automatic emails.** The move uses the site's own roster functions, and nothing listens to them.
- **Boards follow the roster:** the four get the Annual Family and Friends board and lose the Helmets board.
- **Kept as history:** the four keep their "invited via trip 320" record and their old Helmets admin flags (all "no"). Laurie W and Joshua K keep their profile-wall forums; these are not trip boards.

## What changed since the Oct 14 plan
- **Trip 181 fee:** CBGV Group Experience Fee $225 per traveler, **no deposit, up to 3 installments**. The old "$500 deposit" point no longer applies.
- **Master Traveler Agreement v2** was published on Oct 14. All four accepted v1, so the site will first ask them to accept v2 when they next sign in. The email now says so.
- **Trip 181 is live on the new design.** As roster members, the four will see the members' trip page. They will be asked to accept Trip Agreement v4 before trip details show.

## Point to decide
**Helmets drops to 3 travelers, below its minimum of 4.** Its page will say "This trip needs a few more people before it's confirmed." Lower the minimum to 3, or leave it as is?

## Steps once approved
1. Run the move script with writing on. It saves an undo copy of both rosters to `~/logs/old/`.
2. Check that the stored rosters match the dry run.
3. Render trip 181 and Helmets as one moved member. Trip 181 should ask for the Master Traveler Agreement v2, then Trip Agreement v4. Helmets and its board should be denied.
4. Send the email: one per member, no group email, no CC. It goes from the site's normal sender (the same as the welcome email) with **Reply-To: LaDon@journeywellglobal.com**.
5. Report the results; email addresses stay masked.

## Email draft (for approval)

**Subject:** Your trip is changing: Annual Family and Friends, Oct 25–30, 2027

Hi {first name},

As we discussed, you're moving from the Helmets, Hearts & High Seas group to our **Annual Family and Friends** trip, sailing October 25–30, 2027. Your Virgin Voyages booking is being handled with you separately; this email is only about your Checked Bags & Good Vibes account.

**What changes on the site**
- Annual Family and Friends now shows under your trips, with its own trip page and discussion board.
- Helmets, Hearts & High Seas and its discussion board no longer show for you.

**What to do next** (sign in at https://bagsandvibes.com/login/)
1. When you sign in, you'll first be asked to review and accept our updated Master Traveler Agreement.
2. Open the Annual Family and Friends trip page and accept the Trip Agreement.
3. Fill in your Travel Details on the trip page.
4. Pay the CBGV Group Experience Fee ($225 per traveler) on Gate 09 – Payments. There's no deposit, and you can pay in up to 3 installments. You'll first be asked to accept the Payment Disclaimer.

Your cruise is paid directly to Virgin Voyages, not to CBGV, and must be paid in full by June 27, 2027 (120 days before sailing). This trip is for adults only (18+).

Questions? Just reply to this email.

Warmly,
The Checked Bags & Good Vibes Team

*Compliance check:*
- one fee name (CBGV Group Experience Fee);
- no InteleTravel;
- CBGV does not collect travel money;
- no Uplift;
- never "CBGV books your cabin";
- adults only.
