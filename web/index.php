<?php
 require_once('Schoology_OAuth.php');
 require('../vendor/autoload.php');
 ini_set('log_errors_max_len', 0);
 
 
 // establish connection
$SchoologyApi = new SchoologyContainer();
$SchoologyApi->schoologyOAuth();

error_log(print_r($SchoologyApi->token,true));

try {
    $oauth = new OAuth($SchoologyApi->getSchoologyKey(),$SchoologyApi->getSchoologySecret());
    $oauth->setToken($SchoologyApi->token['token_key'],$SchoologyApi->token['token_secret']);

    $oauth->fetch("http://api.schoology.com/v1/system/files/drop_items/m/201707/course/1101895390/Word_Document_test_595fbfed6ba4f.docx",null,OAUTH_HTTP_METHOD_GET);

    $response_info = $oauth->getLastResponseInfo();
	$keys = array_keys($response_info);
	error_log("Keys: \n");
	error_log(count($keys));
	error_log(print_r(array_keys($response_info),true));
	for($i = 0; $i < count($keys); $i++) {
		error_log($keys[$i]);
	}
	error_log(print_r($response_info['http_code'],true));
	$response = $oauth->getLastResponse();
	error_log("Actual response: \n");
	error_log(print_r($response,true));
} catch(OAuthException $E) {
    error_log("Exception caught!\n");
    error_log("Response: ". $E->getMessage() . "\n");
	error_log($E->debugInfo . "\n");
}

/*
$testurl = "http://api.schoology.com/v1/system/files/drop_items/m/201707/course/1101895390/Word_Document_test_595fbfed6ba4f.docx";
$test_array[] = $SchoologyApi->schoology->_makeOauthHeaders($testurl);

$context = stream_context_create(array (
	'http' => array (
		'header' => 'Authorization: ' . $test_array[0]
	)
));
$attachmentBody = file_get_contents($testurl, false, $context);
error_log(print_r($attachmentBody,true));

error_log($testurl);
error_log(print_r($test_array,true));
error_log(print_r($test_array[0],true));
*/



$object_result = json_decode(file_get_contents("php://input"));	
error_log(print_r($object_result,true));
error_log(print_r($object_result->action,true));

// determine operation type and object
switch($object_result->action) {
	case 'INSERT':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->createCourse($object_result);
		} else if($object_result->table == 'ram_assignment_master__c') {
			$SchoologyApi->createAssignment($object_result);
		} else if($object_result->table == 'ram_assignment__c') {
			$SchoologyApi->gradeAssignment($object_result);
		}
		break;
	case 'UPDATE':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->updateCourse($object_result);
		} else if($object_result->table == 'ram_assignment_master__c') {
			$SchoologyApi->updateAssignment($object_result);
		} else if($object_result->table == 'ram_assignment__c') {
			$SchoologyApi->gradeAssignment($object_result);
		}
		break;
	case 'DELETE':
		if($object_result->table == 'ram_cohort__c') {
			$SchoologyApi->deleteCourse($object_result);
		} else if($object_result->table == 'ram_assignment_master__c') {
			$SchoologyApi->deleteAssignment($object_result);
		}
		break;
	default: // this means that Schoology is sending back info
		if(strpos($object_result->type, 'dropbox_submission') !== false) {
			$SchoologyApi->getAssignmentSubmission($object_result->data);
		}
		break;

		error_log("Hey!");
}


?>