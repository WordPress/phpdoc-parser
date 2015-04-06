<?php

function test() {

	a_function();

	function sub_test() {

		b_function();

		My_Class::static_method();
	}

	sub_test();

	My_Class::do_things();
}

class My_Class extends Parent_Class {

	public function a_method() {

		$this->do_it();

		function sub_method_test() {

			b_function();

			My_Class::a_method();
		}

		do_things();
	}
}
