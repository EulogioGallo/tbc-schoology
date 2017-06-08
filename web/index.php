<?php
 require_once('Schoology_OAuth.php');
 require_once('Schoology_Functions.php');
 require('../vendor/autoload.php');
 
 
 // establish connection
$SchoologyApi = new SchoologyContainer();
$SchoologyApi->schoologyOAuth();
$object_result = json_decode(file_get_contents("php://input"));
error_log(print_r($object_result,true));

// determine operation type and object
switch($object_result->action) {
	case 'INSERT':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->createCourse($object_result);
		}
		break;
	case 'UPDATE':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->updateCourse($object_result);
		}
		break;
	case 'DELETE':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->deleteCourse($object_result);
		}
		break;
}


?>