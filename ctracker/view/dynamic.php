<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    <title><?php echo $track->link->title;?></title>
</head>
<body>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-74080200-2', 'auto');
  ga('send', 'pageview');

</script>
<script type="text/javascript">
<?php
if (!isset($_SERVER["HTTP_USER_AGENT"])) {
    echo "redirect2();";
}
?>
redirect();
function process() {
    var xhttp;
    if (window.XMLHttpRequest) {
        xhttp = new XMLHttpRequest();
    } else if (window.ActiveXObject) {
        try {
            xhttp = new ActiveXObject('Msxml2.XMLHTTP');
        } 
        catch (e) {
            try {
                xhttp = new ActiveXObject('Microsoft.XMLHTTP');
            }
            catch (e) {
                redirect2();
            }
        }
    }
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
    window.location = '<?php echo $track->redirectUrl();?>';
}

function redirect2() {
    window.location = "/includes/process.php?id=<?php echo $_GET['id'];?>&Clicks99Track=<?php echo base64_encode($_SESSION['track']);?>";
}
</script>
</body>

</html>