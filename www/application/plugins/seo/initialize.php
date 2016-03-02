<?php

// initialize seo
include("seo.php");

$seo = new SEO(array(
    "title" => "Clicks99 AdNetwork",
    "photo" => CDN . "images/logo.png"
));

Framework\Registry::set("seo", $seo);
