#!/app/.heroku/php/bin/php

<?php
	// need to fork this
	$pid = pcntl_fork();
	if($pid == -1) {
		error_log("Could not daemonize process :-(");
		return 1; // error
	} else if($pid) {
		return 0; // success
	} else {
		// main process
		while(true) {
			error_log("Waiting....");
			sleep(1);
		}
	}
?>