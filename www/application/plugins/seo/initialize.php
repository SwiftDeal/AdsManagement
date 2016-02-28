<?php

// initialize seo
include("seo.php");

$seo = new SEO(array(
    "title" => "Clicks99 AdNetwork",
    "keywords" => "earn money, facebook page monetization",
    "description" => "Welcome to Our Affiliate Network, we let you Monetize your platform through us, get paid with high rpm value in india.",
    "author" => "Clicks99 Team",
    "robots" => "INDEX,FOLLOW",
    "photo" => CDN . "images/logo.png"
));

Framework\Registry::set("seo", $seo);
