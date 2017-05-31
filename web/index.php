<?php
 
// for heroku
require('../vendor/autoload.php');
 
// for local execution
/*
require('../vendor/oauth-io/oauth/src/OAuth_io/OAuth.php');
require('../vendor/oauth-io/oauth/src/OAuth_io/RequestObject.php');
require('../vendor/oauth-io/oauth/src/OAuth_io/NotInitializedException.php');
require('../vendor/oauth-io/oauth/src/OAuth_io/NotAuthenticatedException.php');
require('../vendor/oauth-io/oauth/src/OAuth_io/Injector.php');
require('../vendor/oauth-io/oauth/src/OAuth_io/HttpWrapper.php');
*/
require_once('../schoology_php_sdk-master/SchoologyApi.class.php');
require_once('../schoology_php_sdk-master/SchoologyContentApi.class.php');
require_once('../schoology_php_sdk-master/SchoologyExceptions.php');
require_once('../salesforce/soapclient/SforceEnterpriseClient.php');
require_once('../salesforce/soapclient/SforcePartnerClient.php');
require_once('Schoology_OAuth.php');

 
// use OAuth_io\OAuth;
 
 
 $SchoologyApi = new SchoologyContainer();
 error_log(print_r($SchoologyApi, true));
 $SchoologyApi->schoologyOAuth();
 error_log(print_r($SchoologyApi, true));
 
?>