<?php

do_action( 'plain_action' );
do_action( "action_with_double_quotes" );
do_action( $variable . '-action' );
do_action( "another-{$variable}-action" );
do_action( 'hook_' . $object->property . '_pre' );
