<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta property="fb:app_id" content="583482395136457">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo $track->link->title;?>" />
    <meta property="og:description" content="<?php echo $track->link->description;?>">
    <meta property="og:url" content="<?php echo URL;?>">
    <meta property="og:image" content="<?php echo SITE;?>image.php?file=<?php echo $track->link->image;?>">
    <meta property="og:site_name" content="Clicks99">
    <meta property="article:section" content="Pictures" />
    
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo $track->link->title;?>">
    <meta name="twitter:description" content="<?php echo $track->link->description;?>">
    <meta name="twitter:url" content="<?php echo URL;?>">
</head>
<body>
<script type="text/javascript">
process();
function process() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            //redirect();
        }
        redirect();
    };
    xhttp.open("GET", "includes/process.php?id=<?php echo $_GET['id'];?>", true);
    xhttp.setRequestHeader("Clicks99Track", "<?php echo $_SESSION['track'];?>");
    xhttp.send();
}
function redirect () {
    window.location.href = '<?php echo $track->redirectUrl();?>';
}
</script>
</body>

</html>