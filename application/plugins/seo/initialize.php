<?php

// initialize seo
include("seo.php");

$seo = new SEO(array(
    "title" => "A Smarter Performance Marketing Network",
    "photo" => CDN . "img/logo.png"
));

Framework\Registry::set("seo", $seo);
