<?php
	// Schoology SDK
	require_once('/app/schoology_php_sdk-master/SchoologyApi.class.php');
	require_once('/app/schoology_php_sdk-master/SchoologyContentApi.class.php');
	require_once('/app/schoology_php_sdk-master/SchoologyExceptions.php');
	
	// Storage class
	class SchoologyStorage implements SchoologyApi_OauthStorage {
	  //private $db; SHOULD REMAIN PRIVATE
	  public $db;
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
		private $httpSuccessCodes = array(200, 201, 202, 203, 204);
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
			
			return true;

			} else {
				if(!isset($_GET['oauth_token'])) {
				  $api_result = $this->schoology->api('/oauth/request_token');
				  $result = array();
				  parse_str($api_result->result, $result);
			 
				  $this->storage->saveRequestTokens($this->schoology_uid, $result['oauth_token'], $result['oauth_token_secret']);
			 
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
		
		/**
		 * Creates a Schoology Course
		 *
		 * @param JSON   $newCourse Salesforce Cohort 
		 * 
		 * @throws "Invalid Course Data" If $newCourse is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function createCourse($newCourse) {
		  if(!$newCourse) {
			  error_log('Error! Invalid data for creating course');
			  error_log(print_r($newCourse,true));
			  throw new Exception('Invalid Course data');
		  }
		  
			$courseOptions = array(
			"title" => $newCourse->data->name, 
			"course_code" => $newCourse->data->schoology_course_code__c, 
			"description" => $newCourse->data->description__c
			);
		
		  try {
			$api_result = $this->schoology->api('/courses', 'POST', $courseOptions);
			error_log(print_r($api_result,true));
		  } catch(Exception $e) {
			  error_log('Exception when making API call');
			  error_log($e->getMessage());
		  }
		  
		  // successful call result
		  if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
			  $query = $this->storage->db->prepare("UPDATE salesforce.ram_cohort__c SET synced_to_schoology__c = TRUE, publish__c = FALSE, schoology_id__c = :schoology_id WHERE sfid = :sfid");
			  $schoologyId = $api_result->result->id;
			  error_log('Id: ' . $schoologyId);
			  if($query->execute(array(':schoology_id' => "$schoologyId", ':sfid' => $newCourse->data->sfid))) {
				  error_log('Success! Created Course ' . $newCourse->data->name . ' with ID: ' . $api_result->result->id);
				  return true;
			  } else {
				  error_log('Could not create Course ' . $newCourse->data->name);
				  throw new Exception('Could not create Course');
			  }
		  }
		  
		}
		  
		 /**
		 * Updates a Schoology Course
		 *
		 * @param JSON   $thisCourse Salesforce Cohort 
		 * 
		 * @throws "Invalid Course Data" If $thisCourse is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function updateCourse($thisCourse) {
			  if(!$thisCourse) {
					error_log('Error! Invalid data for updating course');
					error_log(print_r($thisCourse,true));
					throw new Exception('Invalid Course data');
			  }
			  
				$courseOptions = array(
				"title" => $thisCourse->data->name, 
				"course_code" => $thisCourse->data->schoology_course_code__c, 
				"description" => $thisCourse->data->description__c
				);
			  
			 try {
			 	$api_result = $this->schoology->api('/courses/' . $thisCourse->data->schoology_id__c, 'PUT', $courseOptions);
				error_log(print_r($api_result,true));
			  } catch(Exception $e) {
				  error_log('Exception when making API call');
				  error_log($e->getMessage());
			  }
			  
			  // successful call result
			  if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				  $query = $this->storage->db->prepare("UPDATE salesforce.ram_cohort__c SET synced_to_schoology__c = TRUE, publish__c = FALSE WHERE sfid = :sfid");
				  if($query->execute(array(':sfid' => $thisCourse->data->sfid))) {
					  error_log('Success! Updated Course ' . $thisCourse->data->name . ' with ID: ' . $api_result->result->id);
					  return true;
				  } else {
					  error_log('Could not update Course ' . $thisCourse->data->name);
					  throw new Exception('Could not update Course');
				  }
			  }
		}
		  
		 /**
		 * Deletes a Schoology Course
		 *
		 * @param JSON   $thisCourse Salesforce Cohort
		 * 
		 * @throws "Invalid Course Data" If $thisCourse is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function deleteCourse($thisCourse) {
			if(!$thisCourse) {
					error_log('Error! Invalid data for deleting course');
					error_log(print_r($thisCourse,true));
					throw new Exception('Invalid Course data');
			  }
			  
			  try {
				$api_result = $this->schoology->api('/courses/' . $thisCourse->data->schoology_id__c, 'DELETE');
				error_log(print_r($api_result,true));
			  } catch(Exception $e) {
				  error_log('Exception when making API call');
				  error_log($e->getMessage());
			  }
			  
			  // successful call result
			  if($api_result != null  && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				  error_log('Success! Deleted Course ' . $thisCourse->data->name . ' with ID: ' . $thisCourse->data->schoology_id__c);
			  }
		}
	  
		/**
		 * Creates a Schoology Assignment
		 *
		 * @param JSON   $newAss Salesforce Assignment 
		 * 
		 * @throws "Invalid Assignment Data" If $newAss is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function createAssignment($newAss) {
			if(!$newAss) {
				error_log('Error! Invalid data for creating assignment');
				error_log(print_r($newAss,true));
				throw new Exception('Invalid Assignment data');
			}
			
			$assOptions = array(
				"title" => $newAss->data->assignment_title__c,
				"description" => $newAss->data->assignment_description__c,
				"due" => $newAss->data->due_date__c,
				//"grading_scale" => ,
				"grading_period" => 435422,
				//"grading_category" => ,
				//"allow_dropbox" => ,
				"published" => $newAss->data->publish__c,
				"type" => "assignment",
				"course_fid" => 83398284,
				//"assignees" => 
			);
			
			error_log(print_r($newAss));
			
			try {
				$api_result = $this->schoology->api('/sections/'.$newAss->data->schoology_course_id__c.'/assignments', 'POST', $assOptions);
				error_log(print_r($api_result,true));
			} catch(Exceptioni $e) {
				error_log('Exception when making API call');
				error_log($e->getMessage());
			}
			
			// successful call result
			if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				$query = $this->storage->db->prepare("UPDATE salesforce.ram_assignment_master__c SET synced_to_schoology__c = TRUE, publish__c = FALSE, schoology_id__c = :schoology_id WHERE sfid = :sfid");
				$schoologyId = $api_result->result->id;
				error_log('Id: ' . $schoologyId);
				if($query->execute(array(':schoology_id' => "$schoologyId", ':sfid' => $newAss->data->sfid))) {
					error_log('Success! Created Assignment ' . $newAss->data->assignment_title__c . ' with ID: ' . $api_result->result->id);
					return true;
				} else {
					error_log('Could not create Assignment ' . $newAss->data->assignment_title__c);
					throw new Exception('Could not create Assignment');
				}
			}
		}
		
		/**
		 * Updates a Schoology Assignment
		 *
		 * @param JSON   $thisAss Salesforce Assignment 
		 * 
		 * @throws "Invalid Assignment Data" If $thisAss is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function updateAssignment($thisAss) {
			if(!$thisAss) {
				error_log('Error! Invalid data for updating assignment');
				error_log(print_r($thisAss,true));
				throw new Exception('Invalid Assignment data');
			}
			
			$assOptions = array(
				"title" => $thisAss->data->assignment_title__c,
				"description" => $thisAss->data->assignment_description__c,
				"due" => $thisAss->data->due_date__c,
				//"grading_scale" => ,
				"grading_period" => 435422, // static for now, need to pass this from SF
				//"grading_category" => ,
				//"allow_dropbox" => ,
				"published" => $thisAss->data->publish__c,
				"type" => "assignment",
				"course_fid" => 83398284, // static for now, need to pass this from SF		
				//"assignees" => 
			);
			
			try {
				$api_result = $this->schoology->api('/sections/'.$thisAss->data->schoology_course_id__c.'/assignments/'.$thisAss->data->schoology_id__c, 'PUT', $assOptions);
				error_log(print_r($api_result,true));
			} catch(Exception $e) {
				error_log('Exception when making API call');
				error_log($e->getMessage());
			}
			
			// successful call result
			if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				$query = $this->storage->db->prepare("UPDATE salesforce.ram_assignment_master__c SET synced_to_schoology__c = TRUE, publish__c = FALSE WHERE sfid = :sfid");
				if($query->execute(array(':sfid' => $thisAss->data->sfid))) {
					error_log('Success! Updated Assignment ' . $thisAss->data->assignment_title__c . ' with ID: ' . $api_result->result->id);
					return true;
				} else {
					error_log('Could not update Assignment ' . $thisAss->data->assignment_title__c);
					throw new Exception('Could not update Assignment');
				}
			}
		}
		
		/**
		 * Deletes a Schoology Assignment
		 *
		 * @param JSON   $thisAss Salesforce Assignment 
		 * 
		 * @throws "Invalid Assignment Data" If $thisAss is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Eulogio Gallo <egallo@broadcenter.org>
		 * @return
		 */ 
		public function deleteAssignment($thisAss) {
			if(!$thisAss) {
				error_log('Error! Invalid data for deleting Assignment');
				error_log(print_r($thisAss,true));
				throw new Exception('Invalid Assignment data');
			}
			
			try {
				$api_result = $this->schoology->api('/sections/'.$thisAss->data->schoology_course_id__c.'/assignments/'.$thisAss->data->schoology_id__c, 'DELETE');
				error_log(print_r($api_result,true));
			} catch(Exception $e) {
				error_log('Exception when making API call');
				error_log($e->getMessage());
			}
			
			// successful call result
			if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				error_log('Success! Deleted Assignment ' . $thisAss->data->assignment_title__c . ' with ID: ' .$thisAss->data->schoology_id__c);
			}
		}
		
		/**
		 * Retrieves an Assignment submission from Schoology
		 *
		 * @param JSON   $thisAss Schoology Assignment
		 * 
		 * @throws "Invalid Assignment Data" If $thisAss is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Edgar Lopez <elopez@broadcenter.org>
		 * @return
		 */ 

		public function getAssignmentSubmission($thisAss) {
			error_log('getAssignmentSubmission');

			if(!$thisAss) {
				error_log('Error! Invalid data for Retrieving Assignment Submission');
				error_log(print_r($thisAss,true));
				throw new Exception('Invalid data for Retrieving Submission');
			}

			$myArray = array("path" => $thisAss->data->object->attachments->files->file);
			$keys = array_keys($myArray);
			$option = $myArray[$keys[0][0]];
			error_log($option);
			error_log($thisAss->uid);
			error_log($thisAss->data->uid);


			$subOptions = array("body" => $thisAss->data->object->attachments->files->file[0]->converted_download_path);
			error_log(print_r($subOptions["body"],true));
			

			return null;

		/*
			try {
				$api_result = $this->schoology->api('/sections/'.$thisAss->data->section_id.'/submissions/'.$thisAss->data->assignment_nid.'/submission_info/', 'GET', $subOptions);
				error_log(print_r($api_result,true));
			} catch(Exception $e) {
				error_log('Exception when making API call');
				error_log($e->getMessage());
			  }

			// successful call result
			if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
				error_log('Success! Assignment Submitted');
			}

        
       		 //variable holding attachementBody and attachmentName
				//$attachmentBody = $subOptions["body"];
				//$attachmentName = something;
	
			
      		  $AttachFields = array(
        	    'Body' => base64_encode($attachmentBody),
           		//    'ContentType' => $contentType,
          		'Name' => $attachmentName,
          		'ParentID' => // ,
           		'IsPrivate' => 'false'
           		 );

        	$sObject = new stdclass();
        	$sObject->fields = $createFields;
        	$sObject->type = 'Attachment';

        	echo "Creating Attachment";
        	$upsertResponse = $this->SFConnection->create(array($sObject));
        	print_r($upsertResponse);
    	*/
		}
		
		/**
		 * Creates a Schoology Grade from a grades Salesforce Assignment
		 *
		 * @param JSON   $thisAss Salesforce Assignment 
		 * 
		 * @throws "Invalid Assignment Data" If $thisAss is invalid
		 * @throws "Exception When Making API Call" If API call is unsuccessful
		 * @author Edgar Lopez <elopez@broadcenter.org>
		 * @return
		 */ 

		public function gradeAssignment($thisAss) {
			error_log('gradeAssignment');
			return null;
			
		if(!$thisAss) {
				error_log('Error! Invalid data for grading assignment');
				error_log(print_r($thisAss,true));
				throw new Exception('Invalid Assignment data');
		}

		

		//schology grade object members and coressponding salesforce object fields
			$gradeOptions = array(
				"enrollment_id" => $thisAss->data->assignment_title__c,
				"assignment_id" => $thisAss->data->assignment_description__c,
				"grade" => $thisAss->data->due_date__c
			);

		//	printf($gradeOptions["grade"]);

			/*
			try {
				$api_result = $this->schoology->api('/sections/'.$thisAss->data->schoology_course_id__c.'/grades/','PUT', $assOptions);
				error_log(print_r($api_result,true));
			} catch(Exception $e) {
				error_log('Exception when making API call');
				error_log($e->getMessage());
			}

			//successful call result
			if($api_result != null && in_array($api_result->http_code, $this->httpSuccessCodes)) {
			}
		*/	
		}

	}
?>