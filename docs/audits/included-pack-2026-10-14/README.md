# What's included + Pack list step, and trip 181 itinerary updates: review (2026-10-14)

Nothing is deployed or written yet. Every "after" in this folder comes from a temp copy of the code with the proposed content held in memory only.

## Files in this folder
- `review.diff`: the code diff for 9 files:
  - new `checkedbags-lp-included.php`;
  - `lp-core`, `gate07`, `lp-route` and `trip-landing.php`;
  - both CSS files;
  - the new test and `test_step6`.
- `tests.txt`: the new suite passes 27 of 27. Run against the live files it fails 22, so it catches the missing build. All 23 regression suites pass.
- `shots/`:
  - desktop: route-before, route-after, included-after, pack-after, member-after;
  - phone (375px): route-after-phone, included-after-phone, pack-after-phone.

## Code changes (the deploy)
1. **New sections on the new design**, placed after Travel documents:
   - **Gate 22, What's included.**
     - Price parts are listed only when every price includes them.
     - The cruise line's verified perks.
     - The note: "Perks vary by fare...".
     - The CBGV group program list and its note.
   - **Gate 23, Pack list.**
     - Forecast chip and luggage tags.
     - "Leave at home" items, with a link to Virgin's full list.
   - Either section hides when it has no content.
2. **The members' trip page** shows both sections after the travel documents, but only after the traveler accepts the agreement.
3. **The old Highlights block** is hidden on the new design only. The old page keeps it.
4. **Route note:** a new field in the route box. It shows in small italics under the day cards.
5. **Share image:** the page now outputs one og:image: the trip's Yoast Social image, which is poster 406. It used to put cover photo 351 first.
6. **Admin boxes:**
   - provider box "Included & Leave at home": lines plus source and date, the link and its label, and the every-fare line;
   - trip box "Group program & Pack list": program, note, forecast, tags, extra Leave-at-home lines.

## Content writes after the deploy (real boxes, snapshot + diff)

### Trip 181
| Field | Before (live now) | After |
|---|---|---|
| Featured moment day | 4 | **2** |
| Sticker "Tonight: Scarlet Night" (coral) | Day 4 | **Day 2** |
| Day 1 | (blank) | Sail-away toast: Kick off the trip together at 7:30 PM, after everyone has checked in and finished the safety drill. |
| Day 2 | (blank) | Scarlet Night meetup: Come dressed in your best red. We meet 30 minutes before Scarlet Night for a massive group photo. |
| Day 3 | (blank) | Puerto Plata, on your own: Soak up island culture: waterfall hikes, a cable car ride or a beach day, at your own pace. |
| Day 4 | (blank) | Recovery brunch & group dinner: A relaxed late-morning brunch at The Wake, then a group dinner that night to celebrate our time at sea. |
| Day 5 | (blank) | Private cabana day: A private area reserved just for our group at The Beach Club at Bimini. |
| Day 6 | (blank) | Coffee & goodbyes: One last coffee together before we disembark, and hugs until next year's adventure. |
| Route note | (none) | All events and times are subject to change. |
| Intro, paragraph 2 | Expect a **sail-away toast** on the top deck before Miami fades from view, **Scarlet Night** with the whole crew dressed in red, a **private beach cabana day** in Puerto Plata, and a lazy **recovery brunch** before one last sunset dinner as a family. | Expect a sail-away toast on our first night, Scarlet Night with the whole crew dressed in red, a free day to explore Puerto Plata at your own pace, a lazy recovery brunch and a group dinner at sea, and a private cabana day at The Beach Club at Bimini. |
| Highlights: Private Beach Cabana Day (old page) | ...lounging in a shaded, private beach cabana with tailored drinks in Puerto Plata... | Our day to take over the tropics together, lounging in a private cabana at The Beach Club at Bimini. This is all about effortless group relaxation. |
| Group program (new) | (none) | Six items: group chat, sail-away toast (Day 1), Scarlet Night meetup and group photo (Day 2), recovery brunch at The Wake and a group dinner (Day 4), private cabana day at The Beach Club at Bimini (Day 5), coffee and goodbyes (Day 6). Note: "All events and times are subject to change." No swag line, no host line. |
| Pack forecast (new) | (none) | FORECAST · LOW 80s °F · WARM & TROPICAL |
| Pack tags (new) | (none) | 4 tags. **Theme nights:** Scarlet Night (Day 2), something red, with a swimsuit underneath that can get wet. **Days:** daywear, swimsuits, water shoes. **Nights:** smart casual, plus a light layer. **Cabin kit:** magnetic hooks, phone pouch, empty water bottle, sunscreen. PJ Party and 80s Workout are removed. |

Day times stay automatic: the boxes keep the provider's time lines, and only the title and text change.

### Virgin Voyages provider (443)
| Field | Before | After |
|---|---|---|
| Note under the cards | (none) | Perks vary by fare. Your travel advisor will confirm exactly what's included when you book. |
| Leave at home (6) | (none) | Clothing irons; liquor and beer (wine: two 750 mL bottles per cabin); drinks in bottles (cans or cartons only); CBD, marijuana and kratom; drones; seashells and coral. Each line has a source on virginvoyages.com, checked 2026-10-14. Sources are admin-only. |
| Link | (none) | "Virgin Voyages' full prohibited items list" → virginvoyages.com/safety-security/prohibited-items (opens in a new tab) |

## Done already
- Share image is the hero poster (media 406), and its alt text is already set.
- Media 351 is logged with its talent-usage note.
