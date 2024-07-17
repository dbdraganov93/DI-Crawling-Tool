#!/usr/bin/php
<?php
/**
 * This script can be called instead of the pdf2image tool and
 * will ensure that pdf2image is only running once. All additional
 * requests will be qued, saving resources, mainly RAM.
 * 
 * @author Michael Mönch
 */

// Check if there is already a conversion in progress:
$start = time();
$lockFile = '/tmp/pdf2image_lock.pid'; // If this file exists, the program is running
while(file_exists($lockFile)){

	// If we've been waiting for 10 minutes, check if the process which created the
	// lock-file still exists (the PID is written in the file):
	if(time()-$start>60*10){
		$pid = (int) file_get_contents($lockFile);
		if(!is_dir("/proc/$pid")){
			// The process was killed or crashed, remove the lock-file:
			unlink($lockFile);
		}
	}

	// To avoid race conditions, sleep for a random amount of time and the try again:
	usleep(rand(10,500)*1000); // 10-500 ms
	clearstatcache(); // Must be done if we continiously check the same files
}

// We are the only / first script, save our PID and lock pdf2image for other scripts:
file_put_contents($lockFile,getmypid());

// Execute the pdf2image-tool with the exact same parameters and pass all input and
// output through to the caller:
array_shift($argv); // Remove name of this script
passthru("./pdf2image ".implode(' ',$argv),$code);

// We are done, the next process can now use pdf2image:
unlink($lockFile);
exit($code); // Return error-code of pdf2image
