<?php

class Schoology_Functions {
	static function sendAssignmentMaster(String jsonObject) {
		error_log(jsonObject);
	}
	
	static function sendAssignmentToSF(Object jsonObject) {
		error_log(print_r(jsonObject,true));
	}
}

?>