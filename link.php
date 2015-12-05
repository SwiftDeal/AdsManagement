<?php
define("URL", "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
$item_id = $_GET['item'];

$str = base64_decode($item_id);
$datas = explode("&", $str);
foreach ($datas as $data) {
    $property = explode("=", $data);
    $item[$property[0]] = $property[1];
}

GA($item);

function GA($item) {
    // Get cURL resource
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => "https://www.google-analytics.com/collect?v=1&tid=UA-70464246-1&cid=".$item["username"]."&t=pageview&dp=".$item["id"]."&ua=ChocoGhar",
        CURLOPT_USERAGENT => 'CloudStuff'
    ));

    $resp = curl_exec($curl);
    curl_close($curl);
}

function getImage($name) {
    $image = explode(".", $name);
    $cdn = "http://www.chocoghar.com/public/assets/uploads/images/resize/{$image[0]}-470x246.{$image[1]}";
    return $cdn;
}

$image = getImage($item["image"]);

?>

<!DOCTYPE html>
<html>

<head>
    <meta property="fb:app_id" content="583482395136457">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noodp">
    <meta property="og:title" content="<?php echo $item['title']?>" />
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="article">
    <meta property="og:description" content="<?php echo $item['description']?>">
    <meta property="og:image" content="<?php echo $image;?>">
    <meta name="og:image-new" content="<?php echo $image;?>">
    <meta property="og:site_name" content="The Chocoghar Media Group">
    <meta property="og:url" content='<?php echo URL;?>'>
    <meta name="generator" content="SwiftMVC 1.1.1">
</head>

<body>
<!--<img src="https://www.google-analytics.com/collect?v=1&tid=UA-70464246-1&cid=<?php echo $item['username']?>&t=pageview&dp=<?php echo $item['id']?>&ua=ChocoGhar" alt="tracking" />-->
<script type="text/javascript">
    window.location.href = '<?php echo $item["url"];?>?utm_source=chocoghar.com&utm_medium=<?php echo $item["username"];?>&utm_campaign=<?php echo $item["title"];?>';
</script>
</body>

</html>