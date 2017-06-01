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
							$SchoologyApi->storage->getDbUser() . ' ' .
							$SchoologyApi->storage->getDbPassword()
						);
						
	if(!$listenConn) {
		echo "An error occurred when trying to listen\n";
		error_log("An error occurred when trying to listen\n");
		exit;
	}
	
	// Lets try and listen
	pg_query($listenConn, 'LISTEN events');
	
	while (!$end) {
	  $arr=pg_get_notify($listenConn);
	  if (!$arr) {
		usleep(100000);
	  }
	  else
		error_log(print_r($arr,true));
	}
	
} else {
	require('../vendor/autoload.php');
	$SchoologyApi = new SchoologyContainer();
	 error_log(print_r($SchoologyApi, true));
	 $SchoologyApi->schoologyOAuth();
	 error_log(print_r($SchoologyApi, true));
	 $api_result = $SchoologyApi->schoology->apiResult('users/22135108/sections');
	 error_log(print_r($api_result, true));
}
?>