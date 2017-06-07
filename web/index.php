<?php
 require_once('Schoology_OAuth.php');
 require_once('Schoology_Functions.php');
 require('../vendor/autoload.php');
 
 
$SchoologyApi = new SchoologyContainer();
$SchoologyApi->schoologyOAuth();
// Lets list all assignments to see how they're structured in Schoology
$api_result = $SchoologyApi->schoology->api('/sections/1102963310/assignments?with_attachments=1');

/*
$json_result = json_decode(file_get_contents("php://input"));
error_log(print_r($json_result,true));
Schoology_Functions::sendAssignmentToSF($json_result);
*/

?>