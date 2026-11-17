<?php

// Contact Form 7 - Shortcode tags.
ldc()->add_filter_once('do_shortcode_tag', [ldc(), '_cf7_do_shortcode_tag'], 10, 3);
ldc()->add_filter_once('ldc_cf7_shortcode_tag_output', [ldc(), '_cf7_do_form_tags'], 10, 3);
ldc()->add_filter_once('ldc_cf7_shortcode_tag_output', [ldc(), '_cf7_maybe_remove_shortcode_tag_comments'], 15, 3);
ldc()->add_filter_once('ldc_cf7_shortcode_tag_output', [ldc(), '_cf7_maybe_add_shortcode_tag_errors'], 20, 3);
ldc()->add_filter_once('ldc_cf7_shortcode_tag_output', [ldc(), '_cf7_maybe_hide_on_builder_active'], 30, 3);
