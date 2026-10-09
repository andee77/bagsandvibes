# Payment safeguard tests

Proves that **CBGV never collects travel funds**: the member Payment page and
the Stripe checkout bill only the CBGV Group Experience Fee x the member's
travelers + the trip's approved extras, never `cb_price` ("Travel price") or
`cb_quoted_price`, whatever their values, including after a Gate 12 quote is
accepted; a hard ceiling refuses and logs anything above that.

Same setup as `tests/landing/README.md` (a temporary copy of `mu-plugins` in
`/tmp/cbv_mu`, `/tmp/cbv_t/define.php`). The suite makes **no database
writes, sends no email and never calls Stripe**: every outgoing web request is
answered locally by a filter and recorded (the Stripe request body is checked,
its headers are never stored or printed). The safeguard's own PHP error-log lines go to `/tmp/cbv_t/test_errors.log`, not the site's log.

```bash
wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/test_fee_safeguard.php
bash /tmp/cbv_t/mutate_fee_safeguard.sh
```

| File | Covers | Expected |
|---|---|---|
| `test_fee_safeguard.php` | What may be billed (fee for the party: full per adult, half per child, + approved extras; never the travel price), installments, the Stripe amount and its label, the hard ceiling and its log, the webhook, Gate 12 quote acceptance, the Payment page card, admin labels, the roster export | 54 checks, 0 failures |
| `mutate_fee_safeguard.sh` | Mutation tests (each breaks one protection) | 27 mutants, all caught |

The suite fakes every value it uses on real trip 181 (so the Payment page's own
trip list finds it) and checks at the end that the real trip was not touched.
