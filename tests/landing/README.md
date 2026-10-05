# Landing redesign tests

Test suites for the public trip landing redesign. They run on the live server
with WP-CLI against a **temporary copy** of `mu-plugins`, never the live
folder, and make **no database writes** (fake posts live in the object cache,
meta is faked by a filter, every meta write is intercepted).

## Setup (once per session, on the server)

```bash
cd ~/www/bagsandvibes.com/public_html
rm -rf /tmp/cbv_mu /tmp/cbv_t && cp -r wp-content/mu-plugins /tmp/cbv_mu && mkdir -p /tmp/cbv_t
printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mu" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\n' > /tmp/cbv_t/define.php
```

Then copy the changed PHP files from this repo over `/tmp/cbv_mu/`, and these
test files into `/tmp/cbv_t/`.

## Run

```bash
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_step4.php
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_step5.php
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_step6.php
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_step7.php
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_step8.php
bash /tmp/cbv_t/mutate_step5.sh
bash /tmp/cbv_t/mutate_step6.sh
bash /tmp/cbv_t/mutate_step7.sh
bash /tmp/cbv_t/mutate_step8.sh
```

Set `CBV_VERBOSE=1` to list every passing check. Each suite ends with
`N checks, N failures`. The mutation script breaks one behaviour at a time in a
throwaway copy (`/tmp/cbv_mut`) and every mutant must cause at least one failure.
Each mutation script expects its suite in `/tmp/cbv_t/`.

| File | Covers | Expected |
|---|---|---|
| `test_step4.php` | Hero, boarding pass, itinerary stop codes | 123 checks, 0 failures |
| `test_step5.php` | Status board (three stages), intro, GATE counter, link safety in the text formatter | 138 checks, 0 failures |
| `mutate_step5.sh` | Mutation tests for Step 5 | 23 mutants, all caught |
| `test_step6.php` | Route: days from the itinerary, time line, code chain, Route days box, legacy table removal | 102 checks, 0 failures |
| `mutate_step6.sh` | Mutation tests for Step 6 | 19 mutants, all caught |
| `test_step7.php` | Featured moment, gallery, their boxes and save | 64 checks, 0 failures |
| `mutate_step7.sh` | Mutation tests for Step 7 | 17 mutants, all caught |
| `test_step8.php` | Price board data, Pricing Tiers save and editor, proposal PDF Fare column | 59 checks, 0 failures |
| `mutate_step8.sh` | Mutation tests for Step 8 (handles the CRLF file) | 20 mutants, all caught |

Some checks read trip 181 (Annual Family and Friends) as it is on the live
site (its intro text, itinerary and stored times), so they can need updating if
that trip's data changes (for example when its Embarkation / Disembarkation
times are corrected).

Note: `wp eval-file` runs each file inside a function, so a variable a helper
function needs must be shared through `$GLOBALS` (a plain `global` does not see it).
