<?php

My_Class::static_method( $var );

$wpdb->update( $table, $data, $where );

function test() {
	Another_Class::another_method();

	get_class()->call_method();
}

class My_Class extends Parent_Class {

	static function static_method() {
		Another_Class::do_static_stuff();
		self::do_stuff();
		$this->go();
		parent::do_parental_stuff();
	}
}
