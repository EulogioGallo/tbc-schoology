<?php
 require_once('Schoology_OAuth.php');
 require_once('Schoology_Functions.php');
 require('../vendor/autoload.php');
 
 
$SchoologyApi = new SchoologyContainer();
$SchoologyApi->schoologyOAuth();
$json_result = json_decode(file_get_contents("php://input"));
error_log(print_r($json_result,true));
Schoology_Functions::sendAssignmentToSF($json_result);

?>