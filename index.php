<?php
/*
   Voting system for NSS, by Kim Roar Foldøy Hauge
   Written September 2012. Augmented and updated August and September 2013.

   Code sources:
   http://stackoverflow.com/questions/666811/fatal-error-class-mysqli-not-found
   http://php.net/manual/en/mysqli.quickstart.connections.php
   http://www.echoecho.com/htmlforms12.htm
   http://www.echoecho.com/htmlforms10.htm
   http://php.net/manual/en/function.crypt.php
   http://php.net/manual/en/function.date.php
   http://www.onlineconversion.com/unix_time.htm

   Also some helper functions / algorithms from the NSS CMS.
 */
header("Content-type:text/html; charset=utf-8"); // Modern char encoding.
include("config.php");

// We verify we got MySQLi working
if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
	echo 'Error: MySQLi missing.';
	exit();
} 

// Now we connect to the database using mysqli
$mysqli = new mysqli($GLOBALS['dbhost'], $GLOBALS['dbuser'], $GLOBALS['dbpassword'], $GLOBALS['dbdatabase']);
if ($mysqli->connect_errno) {
	echo "Error: Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	exit();
}

function error($msg) {
	print("<h1>Error: $msg</h1>");
	print("Return to the web site <a href=\"https://vote.samfunnet.no/\">https://vote.samfunnet.no/</a>");
	print("      </body>
			</html>");
	exit();
}
// fix date
date_default_timezone_set("Europe/Berlin");

// Process mail sending if needed.
include("mail.php");

// Actual XHTML output
print("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
		\"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">

		<html xmlns=\"http://www.w3.org/1999/xhtml\">

		<head>
		<link rel=\"stylesheet\" media=\"screen\" href=\"/stil.css\" />
		<title>Narvik Studentersamfunn online voting system - Version 0.2.1, 25.09.2013</title>
		<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"/>
		</head>

		<body>");

// Do we have a vote key?
if (isset($_GET['votekey']))	{
	$vk = $_GET['votekey'];
	if (strlen($vk) != 22) {
		$vk = html_entity_decode($vk);
	}
	$vk_has_key = 1;
} else {
	$vk = "";
	$vk_has_key = 0;
}

print("<h1>How to vote</h1>\n<p>To vote you need a vote key. The key will be sent to your student email, #@student.hin.no.<br/>\nThe key ensures you are a verified student and that a voter only votes once.<br/>\nOnce your vote has been cast, you CANNOT change your vote.</p>\n");


if ($vk_has_key == 0) {
	if ($GLOBALS['mailsent'] == 0) {
		print("<h1>Key request</h1>\n<p>Use your school student number to request a key. It may take up to 24 hours to receive the email. If you have problem reading your school email, contact HiN IT support.</p>\n<form action=\"index.php\" method=\"post\"><fieldset><input name=\"snr\" type=\"text\"/>Your student number.</fieldset> <fieldset><br/><input type=\"submit\" value=\"Request key\"/></fieldset> </form>\n");
	} else {
		print("<h1>Key requested</h1>A vote key has been sent to your school email address, #@student.hin.no.<br/>");
	}
}
// This was removed due to the fact that some browsers mangled the input.

/*
if ($vk_has_key == 0) {
	print("<h1>Vote key</h1>\n<p>If you have a vote key, please type it in or use the URL in the email.</p>\n");
	print("<form action=\"index.php\">\n");

	print("<fieldset><input value=\"$vk\" type=\"text\" name=\"votekey\"/></fieldset>\n");
	print("<fieldset><input value=\"Use vote key\" type=\"submit\"/></fieldset></form>\n");

}*/
// Is this vote key on the list of voters
$vl = $mysqli->stmt_init();
$vl = $mysqli->prepare('SELECT has_voted FROM voters WHERE hash = ?');
$vl->bind_param("s", $vk);
$vl->execute();
$vl->bind_result($db_has_voted);

$eligible = 0;
while ($vl->fetch())
{
	if ($db_has_voted == 1)	error("This vote key has already been used");
	else			$eligible = 1;
}
$vl->close();

// We need to check if we have a vote key to display the rest
if (strlen($vk) > 8)
{
	if ($eligible == 0)	error("This vote key is invalid.");

	// we got a vote key, it's eligible, let's see if the person is voting!

	// Ok, we REQUIRE a vote for every position!

	$vp = $mysqli->stmt_init();
	$vp = $mysqli->prepare('SELECT * FROM positions');
	$vp->execute();
	$vp->bind_result($db_id, $db_name);

	$missing_vote_error = "";

	$can_vote = 1; 	// voting is valid, unless we find an error...
	$error_header = 1;
	$error_show = 0;
	while ($vp->fetch())
	{
		//			print($_POST[$db_id] . " <br/>");
		if ( !isset($_POST[$db_id])) {
			$can_vote = 0;
			if ($error_header == 1) {
				$error_header = 0;
				$missing_vote_error .= "<h1>Missing votes</h1>\n";
				$error_show = 1;
			}
			$missing_vote_error .= "Missing vote for: $db_name<br/>\n";
		}
	}
	// If we received too few votes, missing a vote on a candidate.
	
	if (isset($_POST['performvote'])) {
		if ($error_show == 1) {
			print($missing_vote_error);
		}
		else {
			print("<h1>Thank you for voting</h1>\n");
			print("A new voter can go to <a href=\"https://vote.samfunnet.no\">https://vote.samfunnet.no</a>");
		}
	}

	// We use these variables here and once later on
	$vs = date("H:i, d.m.Y", $GLOBALS['start_voting']);
	$ve = date("H:i, d.m.Y", $GLOBALS['end_voting']);

	// Is this within the voting timeframe?
	if ((time() < $GLOBALS['start_voting']) || (time() > $GLOBALS['end_voting']))
	{
		error("The voting period is from $vs to $ve"); 
	}

	// should we insert someting into the vote db
	if ($can_vote == 1)
	{
		// we simply "redo" the previous SELECT, since we have votes for all positions.
		$vp = $mysqli->stmt_init();
		$vp = $mysqli->prepare('SELECT * FROM positions');
		$vp->execute();
		$vp->bind_result($db_id, $db_name);

		$update_votes = array();

		$i = 0; // index :)
		while ($vp->fetch())
		{
			// ok, now we add all this to  an array :)	
			$update_votes[$i] =  $db_id;
			$i++;
		}
		$vp->close();
		foreach ($update_votes as $item)
		{
			//				print("UPDATE candidates SET votes = votes + 1 WHERE cand_id = " . $_POST[$item] . "<br/>");

			$update = $mysqli->stmt_init();
			$update = $mysqli->prepare('UPDATE candidates SET votes = votes + 1 WHERE cand_id = ?');
			$update->bind_param("i", $_POST[$item]);
			$update->execute();

		}
		$has_voted = $mysqli->stmt_init();
		$has_voted = $mysqli->prepare('UPDATE voters SET has_voted = 1 WHERE hash = ?');
		$has_voted->bind_param("s", $vk);
		$has_voted->execute();

	}
	else if ($GLOBALS['mailsent'] == 0) // minor fix, probably only needed during debugging :)
	{

		// Ok, we're not voting, and the person can vote, let's show him what he can vote on!

		print("<h1>Vote</h1>\n");

		print("<p>You can vote for one candidate for each position. If you are unsure about which candidate to choose, you can vote blank.</p>\n");

		print("<form action =\"index.php?votekey=$vk\" method=\"post\">\n");
		print("<table style=\"align: top\" >\n");

		//		mysqli_report(MYSQLI_REPORT_ALL);

		$get_positions = $mysqli->stmt_init();
		$get_positions = $mysqli->prepare('SELECT cand_id AS id, candidates.position AS pid, candidates.name AS cname, positions.name AS position, candidates.description AS description FROM candidates JOIN positions ON candidates.position = positions.position_id ORDER BY positions.position_id;');
		$get_positions->execute();
		$get_positions->bind_result($id, $pid, $cname, $pos, $desc);

		$old_pos = "";

		while ($get_positions->fetch()) {
			if ($old_pos != $pos)
			{
				if ($old_pos != "")
				{
					print("<tr>");
					print("<td><fieldset><input type=\"radio\" name=\"$oldpid\" value=\"0\"></fieldset></td>");
					print("<td><fieldset>none</fieldset></td>");
					print("<td><fieldset>Cast a blank vote.</fieldset></td>");
					print("</tr>\n");

				}

				print("<tr><td colspan=\"3\"><h1>$pos</h1></td></tr>\n");
				print("<tr><th>Selection</th><th>Name</th><th>Presentation</th></tr>\n");
				print("<tr><td colspan=\"3\"><hr/></td></tr>\n");

				$oldpid = $pid;
				$old_pos = $pos;

			}
			print("<tr>");
			print("<td><fieldset><input type=\"radio\" name=\"$pid\" value=\"$id\"></fieldset></td>");
			print("<th><fieldset>$cname</fieldset></th>");
			print("<td><fieldset>$desc</fieldset></td>");

			print("</tr>\n");

			print("<tr><td colspan=\"3\"><hr/></td></tr>\n");
		}

		// fix for the last votable pos.
		print("<tr>");
		print("<td><fieldset><input type=\"radio\" name=\"$pid\" value=\"0\"></fieldset></td>");
		print("<td><fieldset>none</fieldset></td>");
		print("<td><fieldset>Cast a blank vote.</fieldset></td>");
		print("</tr>\n");

		print("</table>");

		//		print("<input value=\"$vk\" type=\"text\" name=\"key\"> ");

		if ((time() > $GLOBALS['start_voting']) && (time() < $GLOBALS['end_voting'])) {
			print("<br/><br/><fieldset><input type=\"hidden\" name=\"performvote\" value=\"true\"/>");	
			print("<input value=\"Click here to cast your vote\" type=\"submit\"/></form></fieldset>");
		} else 	{
			print("Voting opens up at $vs and ends at $ve ");
			print(time());
			print("</form>");
		}

	}
}
print("<hr>Source code available here: <a href=\"nssvote_sep2013.tar.gz\">nssvote_sep2013.tar.gz</<a>");
print("      </body>\n
		</html>\n");

?>


