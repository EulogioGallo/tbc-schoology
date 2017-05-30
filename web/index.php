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

 
use OAuth_io\OAuth;
 
// Storage class
class SchoologyStorage implements SchoologyApi_OauthStorage {
  private $db;
 
  public function __construct(){
    // local db
    //$this->db = new PDO('pgsql:host=localhost;dbname=schoology', 'eulogio', 'TBCfosho2015!');
    // heroku db
    $this->db = new PDO('pgsql:host=ec2-54-83-26-65.compute-1.amazonaws.com;dbname=df6v2am65gvvil', 'dsskzsufyjspyz', '5573cbf997c1edb2c3d416fd6b4af3e59549df9f547bca100c8ee362f553767c');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 
    $query = $this->db->query("SELECT * FROM oauth_tokens");
    if(!$query) {
      throw new Exception("Could not connect to DB\r\n");
    }
  } 
 
 
  public function getAccessTokens($uid) {
    $query = $this->db->prepare("SELECT * FROM oauth_tokens WHERE uid = :uid AND token_is_access = TRUE LIMIT 1");
    $query->execute(array(':uid' => $uid));
 
    return $query->fetch(PDO::FETCH_ASSOC);
  }
 
  public function saveRequestTokens($uid, $token_key, $token_secret) {
    // check if we have request token already to update instead
    $query = $this->getRequestTokens($uid);
    
    if(!$query) {
      $query = $this->db->prepare("INSERT INTO oauth_tokens (uid, token_key, token_secret, token_is_access) VALUES(:uid, :key, :secret, FALSE)");
      $res = $query->execute(array(':uid' => $uid, ':key' => $token_key, ':secret' => $token_secret));
      
    } else {
      $query = $this->db->prepare("UPDATE oauth_tokens SET token_key = :key, token_secret = :secret, token_is_access = FALSE WHERE uid = :uid");
      $res = $query->execute(array(':uid' => $uid, ':key' => $token_key, ':secret' => $token_secret));
    }
 
  }
 
  public function getRequestTokens($uid) {
    $query = $this->db->prepare("SELECT * FROM oauth_tokens WHERE uid = :uid AND token_is_access = FALSE");
    $query->execute(array(':uid' => $uid));
    return $query->fetch(PDO::FETCH_ASSOC);
  }
 
  public function requestToAccessTokens($uid, $token_key, $token_secret) {
    $query = $this->db->prepare("UPDATE oauth_tokens SET token_key = :key, token_secret = :secret, token_is_access = TRUE WHERE uid = :uid");
    $query->execute(array(':key' => $token_key, ':secret' => $token_secret, ':uid' => $uid));
  }
 
  public function revokeAccessTokens($uid) {
    $query = $this->db->prepare("DELETE FROM oauth_tokens WHERE uid = :uid");
    $query->execute(array(':uid' => $uid));
  }
}

// API Class
class Schoology extends SchoologyApi {
    
}

// Salesforce functions
function salesforceCall($data, $objectType) {
    error_log("In Salesforce Call\n");
    $response = '';
    try {
        //$mySFConnection = new SforceEnterpriseClient();
        //$mySFConnection->createConnection("/app/sandbox.wsdl.xml");
        $mySFConnection = new SforcePartnerClient();
        $mySFConnection->createConnection("/app/partnersandbox.wsdl.xml");
        $mySFConnection->login('egallo@broadcenter.org.tbc', 'TBCfosho2016!aey46irNb8KgOr1iNxzjZ31R');

        //$response = $mySFConnection->create($data, $objectType);
        $response = $mySFConnection->update($data, $objectType);
        error_log("Salesforce Call Go!\n");
        error_log(print_r($response, TRUE) . "\n");
    } catch(Exception $e) {
        error_log($mySFConnection->getLastRequest() . "\n");
        error_log($e->faultstring . "\n");
    }

    return $response;
}

function salesforceQuery($query) {
    error_log("In Salesforce Query\n");
    $response = '';
    try {
        //$mySFConnection = new SforceEnterpriseClient();
        //$mySFConnection->createConnection("/app/sandbox.wsdl.xml");
        $mySFConnection = new SforcePartnerClient();
        $mySFConnection->createConnection("/app/partnersandbox.wsdl.xml");
        $mySFConnection->login('egallo@broadcenter.org.tbc', 'TBCfosho2016!aey46irNb8KgOr1iNxzjZ31R');

        $response = $mySFConnection->query($query);
        error_log(print_r($response, TRUE) . "\n");

    } catch(Exception $e) {
        error_log($mySFConnection->getLastRequest() . "\n");
        error_log($e->faultstring . "\n");
    }

    error_log(print_r($response, TRUE));
    return $response;
}

// Create Assessments for assignments for each student
function createAssessments($submissions, $grade_item_id) {
    $contactIds = array();
    $query = "SELECT Id, Contact__c FROM Program__c WHERE Contact__r.Schoology_ID__c IN (";

    for($i = 0; $i < count($submissions); $i++) {
        array_push($contactIds, $submissions[$i]->uid);
        $query .= "'" . $submissions[$i]->uid . "',";
    }

    $query = rtrim($query, ",");
    $query .= ") AND Cohort__c = 'TBA XIII (2016)'";
    error_log($query . "\n");

    $response = salesforceQuery($query);
    error_log(print_r($response, TRUE));

    $records = array();
    $count = 0;
    foreach($response->records as $record) {
        $records[$count] = new stdclass();
        $records[$count]->Assignment_Name__c = 'Test Assignment 1';
        $records[$count]->Program__c = $record->Id;
        $records[$count]->Schoology_Grade_ID__c = $grade_item_id;
        $records[$count]->type = 'Assessment__c';

        $count++;
    }

    $response = salesforceCall($records, 'Assessment__c');
    error_log(print_r($response, TRUE));

    return $response;
}

// Update Assessment records in Salesforce
function updateAssessments($student_id, $assignment_id) {
	$query = "SELECT Id, Submitted_from_Schoology__c, Contact_Schoology_ID__c, Schoology_Grade_ID__c FROM Assessment__c WHERE Contact_Schoology_ID__c = '$student_id' AND Schoology_Grade_ID__c = '$assignment_id' LIMIT 1";
	error_log(print_r($query, TRUE) . "\n");
	$response = salesforceQuery($query);
	error_log(print_r($response, TRUE) . "\n");

	if($response != '') {
		$records = array();
		$count = 0;
		$fields_to_update = array(
			'Submitted_from_Schoology__c' => true
		);
		$update_record = new SObject();
		$update_record->fields = $fields_to_update;
		$update_record->type = 'Assessment__c';
		$update_record->Id = $response->records[0]->Id[0];
		error_log(print_r($update_record, TRUE) . "\n");
		/*
		foreach($response->records as $record) {
			error_log("Lets see....\n");
			error_log(print_r($record, TRUE) . "\n");
			$records[$count] = new stdclass();
			$records[$count]->Id = $record->Id;
			$records[$count]->Submitted_from_Schoology__c = true;
			$records[$count]->type = 'Assessment__c';
			$count++;
		}
		*/


		//$response = salesforceCall($records, 'Assessment__c');
		$response = salesforceCall(array($update_record), 'Assessment__c');
		error_log(print_r($response, TRUE) . "\n");
	}

	return $response;
}
 
// Eulogio's Schoology Stuffs
$schoology_secret = 'da12e9775938fc21a144e8d39292f10a';
$schoology_key = '1dd53b528230ad488c8545fa1c263dba05568d1e8';
$schoology_uid = '22135108';
$schoology = new SchoologyApi($schoology_key, $schoology_secret, '', '', '', TRUE);
$storage = new SchoologyStorage();
 
$token = $storage->getAccessTokens($schoology_uid);

error_log("Test!\n");
error_log(print_r($schoology));
 
if($token) {

  error_log("got token!\n");
  error_log(print_r($token, TRUE) . "\n");
  $schoology->setKey($token['token_key']);
  $schoology->setSecret($token['token_secret']);
  //echo("<br/>");

  error_log(file_get_contents("php://input"));
  $json_result = json_decode(file_get_contents("php://input"));
  $submitted_user_id = $json_result->uid;
  $submitted_assignment_id = $json_result->data->assignment_nid;
  error_log(print_r($json_result, TRUE));
  error_log(print_r("UID: " . $json_result->uid, TRUE));
  error_log(print_r("Section ID: " . $json_result->data->section_id, TRUE));
  error_log(print_r("Assignment ID: " . $json_result->data->assignment_nid, TRUE));

  try {
      $reqType = 'Not Set';
      $api_result = 'nada';
      if(isset($_GET['req_type'])) {
          error_log("Requirement Type found - " . $_GET['req_type'] . "\n");
          $result = updateAssessments($submitted_user_id, $submitted_assignment_id);
          error_log(print_r($result, TRUE) . "\n");
          //$reqType = $_GET['req_type'];
          //$reqType = 'courses/302180662';
          
          /*
          $reqType = 'sections/302180664/assignments';
          error_log("Req var: " . $reqType . "\n");
          
          // get list of assignments for course
          $api_result = $schoology->apiResult($reqType);
          $result = array();
          $grade_item_id = $api_result->assignment[0]->grade_item_id;
          error_log(print_r($grade_item_id, TRUE));
          $reqType = 'sections/302180664/submissions/' . $grade_item_id;
          error_log("Req var: " . $reqType . "\n");
          $api_result = $schoology->apiResult($reqType);
          $result = createAssessments($api_result->revision, $grade_item_id);
          
          error_log(print_r($_GET, TRUE) . "\n");
          */
      } else {
          error_log("Requirement Type NOT FOUND!\n");
          $api_result = $schoology->apiResult('courses/35146867');
          //$api_result = $schoology->apiResult('courses/{course_id}/sections');

      }
      
    $header_string = 'Location: https://skuid.cs16.visual.force.com/apex/skuid__ui?page=Schoology&returned=true';
    header($header_string);
    return $result;

  } catch(Exception $e) {
    if($e->getCode() == 401) {
      $storage->revokeAccessTokens($schoology_uid);
      // use temp variables cuz php is dumb
      $tempKey = $schoology->getKey();
      $tempSecret = $schoology->getSecret();
      error_log("Exception!\n");
      error_log("Key: " . $tempKey . "\n");
      error_log("Secret: " . $tempSecret . "\n");
      unset($token, $tempKey, $tempSecret);
    }
  }
} else {
    if(!isset($_GET['oauth_token'])) {
      $api_result = $schoology->api('/oauth/request_token');
 	  error_log(print_r($api_result));
      $result = array();
      parse_str($api_result->result, $result);
      error_log("Request Token Results!\n");
      error_log(print_r($api_result) . "\n");
 
      $storage->saveRequestTokens($schoology_uid, $result['oauth_token'], $result['oauth_token_secret']);
 
 	  /*
      $params = array(
        'oauth_callback=' . urlencode('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']),
        'oauth_token=' . urlencode($result['oauth_token']),
      );
 
      $query_string = implode('&', $params);
      $header_string = 'Location: https://www.schoology.com/oauth/authorize?' . $query_string;
      header($header_string);
	  */
      exit;

    } else {
        // get existing record from DB
        $request_tokens = $storage->getRequestTokens($schoology_uid);

        // If the token doesn't match what we have in the DB, someone's tampering with the requests
        if($request_tokens['token_key'] !== $_GET['oauth_token']) {
            throw new Exception('Invalid oauth_token received');   
        }

        // Request access tokens using our newly approved request tokens
        $schoology->setKey($request_tokens['token_key']);
        $schoology->setSecret($request_tokens['token_secret']);
        $api_result = $schoology->api('/oauth/access_token');

        // Parse the query-string-formatted result
        $result = array();
        parse_str($api_result->result, $result);
        error_log("Access Token Results!\n");
        error_log(print_r($api_result, TRUE) . "\n");

        // Update our DB to replace the request tokens with access tokens
        $storage->requestToAccessTokens($schoology_uid, $result['oauth_token'], $result['oauth_token_secret']);

        // Update our $ouath credentials and proceed normally
        $schoology->setKey($result['oauth_token']);
        $schoology->setSecret($result['oauth_token_secret']);
    }
   
}
 
 
 
 
?>