<?php

$mem = new Memcached();
$mem->addServer("127.0.0.1", 11211);

$result = $mem->get("ads");

if ($result) {
	echo json_encode($result);
} else {
	$array = array(
    	"title" => "19 Funny Snaps That Will Make You Laugh Out Loud",
	    "url" => "http://chocoapps.in/OA==",
	    "image" => "http://chocoapps.in/image.php?file=56bdb51315180.jpg"
	);
	$mem->set("ads", $array);
}