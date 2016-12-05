<?
	// this function is copied from the NSS CMS.
	function send_mail($from, $to, $subject, $message, $cc, $bcc)
	{
		
		$mime = 			"MIME-Version: 1.0\r\n";
		$mime .=        		"Content-type: text/plain; charset=UTF-8\r\n";
		$mime .= 			"From: $from\r\n";
		if (strlen($cc) > 0) $mime .= 	"Cc: $cc\r\n";
		if (strlen($bcc) > 0) $mime .=	"Bcc: $bcc\r\n";
		$mime .=			"X-Mailer: ZokumNSSMailer 0.2b-rc1\r\n";
		$mime .=			"X-Organization: Foreningen Narvik Studentersamfunn\r\n";
		$mime .=			"X-Location: Narvik\r\n";
		$mime .=			"X-Preferred-captain: Jean Luc Picard\r\n";

		if ($GLOBALS['devmode'] == 1) 	print("Email: $to, $subject, $message, $mime");
		else				mail($to, $subject, $message, $mime);

	}

	$GLOBALS['mailsent'] = 0; // we haven't sent any mails, show send mail dialog

// only send mail if a student number is sent via http POST

	if (isset($_POST['snr'])) {
	
		$snr = $_POST['snr'];

		// Is the student number actually a student number?
		if (!is_numeric($snr))		error("Student number does not appear to be a number.");	
		if ($snr < 10000)		error("Student number value too low.");
		if ($snr > 999999)		error("Student number value too high.");
		
		// Rate limit check: Student number, minimum 1 minute between emails
		$from_ip = $_SERVER['REMOTE_ADDR'];
		$timestamp = time() - 60;
		$cur_time = time();

		$mq = $mysqli->stmt_init();
		$mq = $mysqli->prepare('SELECT * FROM mails WHERE snr = \'$snr\' AND unixtime < \'$timestamp\'');
		$mq->execute();
		$mq->bind_result($db_snr, $db_ip, $db_unixtime);

		while ($mq->fetch()) {
			error("A key to this student number was recently requested. Please try again in 5 minutes.\n");	
		}
		// Rate limit check: IP, minimum 1 minute between emails

		$mq = $mysqli->stmt_init();
		$mq = $mysqli->prepare('SELECT * FROM mails WHERE snr = ? AND unixtime > ?');
		$mq->bind_param("ii", $snr, $cur_time);
		$mq->execute();
		$mq->bind_result($db_snr, $db_ip, $db_unixtime);

		while ($mq->fetch()) {
//		print("db: " . date("H:i:s", $db_unixtime) . ", current: " . date("H:i:s", $cur_time) . "<br/>");
//			print("$db_snr, $db_ip, $db_unixtime, $cur_time");
		        error("This IP address recently requested a vote key by email. Please try again in 1 minute.\n");
		}

		// Seems we got a legitimate mail to send.
		$future_time = $cur_time + 60; // 1 minute
		$mq = $mysqli->stmt_init();
		$mq = $mysqli->prepare('INSERT INTO mails(snr, ip, unixtime) VALUES (?, ?, ?)');
		$mq->bind_param("isi", $snr, $from_ip, $future_time);


		$mq->execute();
	//	$mq->bind_result($db_snr, $db_ip, $db_unixtime);

		// Now we generate the key
//		print ($GLOBALS['salt']);
		$hash = substr(crypt($snr, $GLOBALS['salt']),-22);

// fix for / and . in urls, they would look messy and weird and // as start isn't exactly well defined for a web server :D
		$hash = str_replace("/", "Z", $hash);
		$hash = str_replace(".", "z", $hash);
//		$hash = str_replace("I", "z", $hash);

		// The recipient of the mail
		$recipient = $snr . "@student.hin.no";

		// The body of the mail message
		$msg = "You have requested a vote key for use in the voting system on https://vote.samfunnet.no/\n\nUse the following URL: https://vote.samfunnet.no/$hash\n\nIf you have problems loading the URL, try copy and pasting it int a new browser window.";

		// Let's send the email!
		send_mail("valg@samfunnet.no", $recipient, "[NSS] Your vote key for the election", $msg, "", "");
		$mq->close();
		
		$GLOBALS['mailsent'] = 1;

		// We also need to add this hash to the voter list!

		$has_voted = 0;
		$ah = $mysqli->stmt_init();
		$ah = $mysqli->prepare('INSERT INTO voters(hash, has_voted) VALUES(?, ?)');
		$ah->bind_param("si", $hash, $has_voted);
		$ah->execute();

	}

?>
