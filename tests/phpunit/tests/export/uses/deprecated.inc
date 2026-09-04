<?php

function old_thing() {
	do_something_first();
	_deprecated_function( __FUNCTION__, '6.1.0', 'new_thing' );
}
