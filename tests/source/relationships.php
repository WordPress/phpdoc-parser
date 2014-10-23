<?php

function relate_function1() {
	relate_function2();
}

function relate_function2() {

	/**
	 * A relationship hook
	 */
	$relate = apply_filters( 'relate-hook', true );

	echo $relate;
}

function relate_function3() {
	wpdb::relate_method1();
}

function relate_function4() {
	$wpdb = new wpdb();

	$wpdb->relate_method4();
}

function relate_function5() {
	wpdb::relate_method2()->some_function();
}

function relate_function6() {
	wp_screen()->relate_method1();
}

class wpdb {

	public function __construct() {}

	public static function relate_method1() {
		self::relate_method2();
	}

	public static function relate_method2() {
		/**
		 * Filter a aCustomize setting value in un-slashed form.
		 *
		 * @since 3.5.0
		 *
		 * @param mixed                $value Value of the setting.
		 * @param WP_Customize_Setting $this WP_Customize_Setting instance.
		 */
		$meh = apply_filters( 'meh-hook', $meh );
	}

	public static function relate_method3() {
		relate_function1();
	}

	public function relate_method4() {
		relate_function2();
	}

	public function relate_method5() {
		$this->relate_method4();
	}

	public static function relate_method6() {
		wpdb::relate_method1();
	}

	public static function relate_method7() {
		global $wpdb;

		$wpdb->relate_method5();
	}
}

class WP_Screen {
	public function relate_method1() {}
}
