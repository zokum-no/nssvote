<?php
	$GLOBALS['salt'] = 		'$LONGSALTVALUEHERE';
	// time stamps are in UTZ, add 1 or 2 hours for CET.
//	$GLOBALS['start_voting'] =	1380535200; // 2013
//	$GLOBALS['end_voting'] =	1381140000; // 2013

	$GLOBALS['start_voting'] =	1443196800; // Unix time stamp
	$GLOBALS['end_voting'] =	1443801600;

	$GLOBALS['dbhost'] =		"localhost";
	$GLOBALS['dbuser'] =		"vote";
	$GLOBALS['dbpassword'] =	"votepass";
	$GLOBALS['dbdatabase'] =	"ballot";

	// Here we turn on or off development mode
	$GLOBALS['devmode'] = 0;

?>
