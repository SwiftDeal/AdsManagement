<?php

require 'includes/config.php';

$m = new \MongoClient("mongodb://".DBUSER.":".DBPASS."@ds025849-a0.mlab.com:25849,ds025849-a1.mlab.com:25849/clicks99?replicaSet=rs-ds025849");
$db = $m->clicks99;
$urls = $db->urls;