<?php

do_action( 'plain_action' );
do_action( "action_with_double_quotes" );
do_action( $variable . '-action' );
do_action( "another-{$variable}-action" );
do_action( 'hook_' . $object->property . '_pre' );
apply_filters( 'plain_filter', $variable, $filter_context );
do_action( '\xC0 hook' );
do_action( "\x09tab" );
do_action( '\x09tab' );
do_action( "\xC0 bad" );
apply_filters( 'heredoc_filter', <<<EOT
heredoc body
EOT, 2 );
apply_filters( 'nowdoc_filter', <<<'EOT'
nowdoc $body
EOT, 2 );
do_action( "it's" );
do_action( 'it\'s' );
