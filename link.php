<?php

$item_id = $_GET['item'];

$str = base64_decode($item_id);
$datas = explode("&", $str);
foreach ($datas as $data) {
    $property = explode("=", $data);
    $item[$property[0]] = $property[1];
}

//echo "<pre>", print_r($item), "</pre>";
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
    <meta property="og:image" content="http://www.chocoghar.com/public/assets/uploads/images/<?php echo $item['image'];?>">
    <meta name="og:image-new" content="http://www.chocoghar.com/public/assets/uploads/images/resize/<?php echo $item['image'];?>">
    <meta property="og:site_name" content="The EarnBugs Media Group">
    <meta name="generator" content="SwiftMVC 1.1.1">
</head>

<body>
<img src="https://www.google-analytics.com/collect?v=1&tid=UA-70464246-1&cid=".$item["username"]."&t=pageview&dp=".$item["id"]."&ua=ChocoGhar" alt="tracking" />
<script type="text/javascript">
    window.location.href = '<?php echo $item["url"];?>?utm_source=chocoghar.com&utm_medium=<?php echo $item["username"];?>&utm_campaign=<?php echo $item["title"];?>';
</script>
</body>

</html>