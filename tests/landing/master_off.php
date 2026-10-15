<?php
/* Test helper (read only): load the setup file named in CBV_MO_BASE, then treat the new-design master switch as OFF
   in memory, so the "old public page" can still be checked after trip 181 went live (2026-10-15). */
require getenv( 'CBV_MO_BASE' ) ?: '/tmp/cbv_t/define_v2.php';
WP_CLI::add_wp_hook( 'pre_option_cbv_lp_redesign_live', function () { return '0'; } );
