<?php
/* Test helper (read only): load the setup file named in CBV_SO_BASE, then treat the member social features as ON
   in memory (Settings > CBGV Member Features ships OFF since 2026-10-15). */
require getenv( 'CBV_SO_BASE' ) ?: '/tmp/cbv_t/define_v2.php';
WP_CLI::add_wp_hook( 'pre_option_cbv_member_social_enabled', function () { return '1'; } );
