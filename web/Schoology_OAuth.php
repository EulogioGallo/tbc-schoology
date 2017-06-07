<?php
	// Schoology SDK
	require_once('/app/schoology_php_sdk-master/SchoologyApi.class.php');
	require_once('/app/schoology_php_sdk-master/SchoologyContentApi.class.php');
	require_once('/app/schoology_php_sdk-master/SchoologyExceptions.php');
	
	// Storage class
	class SchoologyStorage implements SchoologyApi_OauthStorage {
	  private $db;
	  private $dbHost = 'host=ec2-54-83-26-65.compute-1.amazonaws.com';
	  private $dbName = 'dbname=df6v2am65gvvil';
	  private $dbUser = 'dsskzsufyjspyz';
	  private $dbPassword = '5573cbf997c1edb2c3d416fd6b4af3e59549df9f547bca100c8ee362f553767c';
	 
	  public function __construct(){
		// heroku connect db
		$this->db = new PDO('pgsql:' . $this->dbHost . ';' . $this->dbName, $this->dbUser, $this->dbPassword);
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
	  
	  public function getDbHost() {
		  return $this->dbHost;
	  }
	  
	  public function getDbName() {
		  return $this->dbName;
	  }
	  
	  public function getDbUser() {
		  return $this->dbUser;
	  }
	  
	  public function getDbPassword() {
		  return $this->dbPassword;
	  }
	}
	
	// Schoology API / Authorization class
	class SchoologyContainer {
		// Eulogio's Schoology Stuffs
		private $schoology_secret = 'da12e9775938fc21a144e8d39292f10a';
		private $schoology_key = '1dd53b528230ad488c8545fa1c263dba05568d1e8';
		private $schoology_uid = '22135108';
		public $schoology;
		public $storage;
		public $token;
		
		public function __construct() {
			$this->schoology = new SchoologyApi($this->schoology_key, $this->schoology_secret, '', '', '', TRUE);
			$this->storage = new SchoologyStorage();
			 
			$this->token = $this->storage->getAccessTokens($this->schoology_uid);
		}
		
		public function schoologyOAuth() {
			if($this->token) {
			  $this->schoology->setKey($token['token_key']);
			  $this->schoology->setSecret($token['token_secret']);

			/*
			  error_log(file_get_contents("php://input"));
			  $json_result = json_decode(file_get_contents("php://input"));
			  $submitted_user_id = $json_result->uid;
			  $submitted_assignment_id = $json_result->data->assignment_nid;
			  error_log(print_r($json_result, TRUE));
			  error_log(print_r("UID: " . $json_result->uid, TRUE));
			  error_log(print_r("Section ID: " . $json_result->data->section_id, TRUE));
			  error_log(print_r("Assignment ID: " . $json_result->data->assignment_nid, TRUE));
			*/
			
			return true;

			/*
			  try {
				  if(isset($_GET['req_type'])) {
					  error_log("Requirement Type found - " . $_GET['req_type'] . "\n");
					  $result = updateAssessments($submitted_user_id, $submitted_assignment_id);
					  error_log(print_r($result, TRUE) . "\n");
					  $reqType = $_GET['req_type'];
					  $reqType = 'courses/302180662';
					  
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
				  } else {
					  error_log("Requirement Type NOT FOUND!\n");
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
			  */
			} else {
				if(!isset($_GET['oauth_token'])) {
				  $api_result = $this->schoology->api('/oauth/request_token');
				  $result = array();
				  parse_str($api_result->result, $result);
			 
				  $this->storage->saveRequestTokens($this->schoology_uid, $result['oauth_token'], $result['oauth_token_secret']);
			 
				  // now lets see if we can get some info via API
				  /*
				  $token = $storage->getAccessTokens($schoology_uid);
				  error_log(print_r($token, true));
				  $schoology->setKey($token['token_key']);
				  $schoology->setSecret($token['token_secret']);
				  $api_result = $schoology->apiResult('users/22135108/sections');
				  error_log(print_r($api_result, true));
				  exit;
				  */
				  return true;

				} else {
					// get existing record from DB
					$request_tokens = $this->storage->getRequestTokens($schoology_uid);

					// If the token doesn't match what we have in the DB, someone's tampering with the requests
					if($request_tokens['token_key'] !== $_GET['oauth_token']) {
						throw new Exception('Invalid oauth_token received');
					}

					// Request access tokens using our newly approved request tokens
					$this->schoology->setKey($request_tokens['token_key']);
					$this->schoology->setSecret($request_tokens['token_secret']);
					$api_result = $this->schoology->api('/oauth/access_token');

					// Parse the query-string-formatted result
					$result = array();
					parse_str($api_result->result, $result);

					// Update our DB to replace the request tokens with access tokens
					$this->storage->requestToAccessTokens($this->schoology_uid, $result['oauth_token'], $result['oauth_token_secret']);

					// Update our $ouath credentials and proceed normally
					$this->schoology->setKey($result['oauth_token']);
					$this->schoology->setSecret($result['oauth_token_secret']);
					
					return true;
				}
			   
			}
		}
	}
	
?>
