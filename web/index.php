<?php
 
// for heroku
require('../vendor/autoload.php');
 
 /*
require_once('../schoology_php_sdk-master/SchoologyApi.class.php');
require_once('../schoology_php_sdk-master/SchoologyContentApi.class.php');
require_once('../schoology_php_sdk-master/SchoologyExceptions.php');
require_once('../salesforce/soapclient/SforceEnterpriseClient.php');
require_once('../salesforce/soapclient/SforcePartnerClient.php');
*/
require_once('Schoology_OAuth.php');

 
 $SchoologyApi = new SchoologyContainer();
 error_log(print_r($SchoologyApi, true));
 $SchoologyApi->schoologyOAuth();
 error_log(print_r($SchoologyApi, true));
 $api_result = $SchoologyApi->schoology->apiResult('users/22135108/sections');
 error_log(print_r($api_result, true));
?>