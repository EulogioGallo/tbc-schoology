<?php
 require_once('Schoology_OAuth.php');
 
// Here we will determine how this page is used
// if this is called via scheduler, then we want to start polling
// otherwise, we're being visited directly by Schoology so we want
// to process the info payload
if(php_sapi_name() == 'cli') {
	error_log("CLI!\n");
	$SchoologyApi = new SchoologyContainer();
	$listenConn = pg_pconnect(
							$SchoologyApi->storage->getDbHost() . ' ' .
							$SchoologyApi->storage->getDbName() . ' ' .
							'user=' . $SchoologyApi->storage->getDbUser() . ' ' .
							'password=' . $SchoologyApi->storage->getDbPassword()
						);
						
	if(!$listenConn) {
		echo "An error occurred when trying to listen\n";
		error_log("An error occurred when trying to listen\n");
		exit;
	}
	
	// Lets try and listen, but only for about 10 seconds
	pg_query($listenConn, 'LISTEN events');
	
	$timer = 0;
	while ($timer < 100) {
	  $arr=pg_get_notify($listenConn);
	  if (!$arr) {
		usleep(100000);
	  }
	  else {
		error_log(print_r($arr,true));
	  }
	  
	  $timer += 1;
	}
	
} else {
	require('../vendor/autoload.php');
	$SchoologyApi = new SchoologyContainer();
	$SchoologyApi->schoologyOAuth();
	$json_result = json_decode(file_get_contents("php://input"));
	error_log(print__r($json_result,true));
	//$api_result = $SchoologyApi->schoology->apiResult('users/22135108/sections');
}
?>