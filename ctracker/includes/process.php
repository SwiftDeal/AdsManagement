<?php
	require 'config.php';
    require 'vendor/autoload.php';
    require 'tracker.php';

    $arr["success"] = false;
	if (isset($_GET['id']) && isset($_SERVER['HTTP_CLICKS99TRACK'])) {
		$track = new LinkTracker($_GET['id']);
		if (isset($track)) {
			$track->process();
			$track->log('ajaxvisits');
			$arr["success"] = true;
		} else {
			$arr["success"] = "Link Doesnot exist";
		}
	} else {
		$arr["success"] = "No Id Provided";
	}

	if (isset($_GET['id']) && isset($_GET['Clicks99Track'])) {
		if (base64_decode($_GET['Clicks99Track']) == $_SESSION['track']) {
			$track = new LinkTracker($_GET['id']);
			if (isset($track)) {
				$track->process();
				$url = $track->redirectUrl();
				header("Location: {$url}");
				exit;
			} else {
				$arr["success"] = "Link Doesnot exist";
			}
		}
	}

	echo json_encode($arr);