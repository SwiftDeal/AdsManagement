<?php

namespace Shared\Services;
use Framework\RequestMethods as RequestMethods;
use \Meta as Meta;
use \PHPMailer as PHPMailer;
use Shared\Mail as Mail;
use Shared\Utils as Utils;

class Smtp {
	public static function create(\Organization $org) {
		$msg = 'Added STMP details!!';
		$search = [ 'prop' => 'orgSmtp', 'propid' => $org->_id ];

		$meta = Meta::first($search);
		if (!$meta) {
			$meta = new Meta($search);
		}

		$fields = ['server', 'username', 'password', 'email', 'from', 'email', 'security', 'port'];
		$value = [];
		foreach ($fields as $key) {
			$v = RequestMethods::post($key);
			if (!$v) {
				return 'Please Fill the Required Fields';
			}

			$value[$key] = $v;
		}

		$value['password'] = Utils::encrypt($value['password'], $org->_id);
		$meta->value = $value;

		if ($meta->validate()) {
			$meta->save();	
		} else {
			$msg = 'Fill all required values';
		}

		return $msg;
	}

	public static function sendMail(\Organization $org, $opts = []) {
		$mail = new PHPMailer;

		// $mail->SMTPDebug = 3;                               // Enable verbose debug output
		$smtpConf = Meta::first(['prop' => 'orgSmtp', 'propid' => $org->_id]);
		if (!$smtpConf) {
			// use the Mailgun API to send mail
			$opts['org'] = $org;
			Mail::send($opts);
			return;
		}

		$smtpConf = $smtpConf->value;
		$password = Utils::decrypt($smtpConf['password'], $org->_id);

		$mail->isSMTP();
		$mail->Host = $smtpConf['server'];  // Specify main and backup SMTP servers
		$mail->SMTPAuth = true;                               // Enable SMTP authentication
		$mail->Username = $smtpConf['username'];               // SMTP username
		$mail->Password = $password;                           // SMTP password
		$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
		$mail->Port = 587;                                    // TCP port to connect to

		$mail->setFrom($smtpConf['email'], $smtpConf['from']);
		foreach ($opts['to'] as $email) {
			$mail->addAddress($email);
		}
		
		$mail->isHTML(true);

		$mail->Subject = $opts['subject'];
		$mail->Body    = Mail::_body($opts);

		if (!$mail->send()) {
		    throw new \Exception('Failed to send email: ' . $mail->ErrorInfo);
		} else {
			return true;
		}
	}
}
