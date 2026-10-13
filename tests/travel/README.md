# Travel Details form tests

Tests for the member's Travel Details (trip registration) form on a trip page:
`cbv_render_traveler_intake_form()` and the `cb/v1/trips/{id}/traveler-intake` save in
`checkedbags-trip-invites.php`, plus its column in the roster export.

| File | Covers | Expected |
|---|---|---|
| `test_travel_details_save.php` | Server side: a form with every optional section turned off saves; the server never requires fields from a turned-off section; loyalty number optional; "I have TSA PreCheck" stored as true/false; no Known Traveler Number ever stored; roster export "TSA PreCheck" column. Read only (writes intercepted), as the admin on the $1 test trip (424) | 13 checks, 0 failures |
| `travel_details_browser_test.js` + `run_travel_details_browser_test.sh` | Browser side, in a real headless browser: every optional section off saves (the "Please complete: Room, Car category" bug); a section turned on is required again and turned off is not; loyalty number optional; PreCheck checkbox saved; no Known Traveler Number field. `fetch()` is replaced by a recorder, so nothing is sent | 19 checks, 0 failures |

## Run

Server side (on the server, temp copy as in `tests/landing/README.md`):

```bash
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_travel_details_save.php
```

Browser side (on a PC with Edge or Chrome): render a trip page with the form as a roster member, copy it
here, then run the test against it.

```bash
CBV_PATH=/trip/zz-payment-test-1-internal/ CBV_USER=1 wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/render_page.php > /tmp/cbv_t/intake.html
bash tests/travel/run_travel_details_browser_test.sh intake.html
```

The trip used must offer all four optional sections (Air, Cruise, Hotel, Car Rental) as checkboxes, i.e. a
trip with no trip type set. On the pre-2026-10-13 form the browser test fails 11 of 18 (it reproduces
"Please complete: Room, Car category." with every section off) and the save test fails 6 of 13.
