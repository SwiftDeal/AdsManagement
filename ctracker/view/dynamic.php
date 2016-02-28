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

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
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
$.ajaxSetup({
    headers: { 'Clicks99Track': '<?php echo $_SESSION["track"];?>' }
});
process();
function process() {
    $.ajax({
        url: 'includes/process.php',
        data: {id: '<?php echo $_GET["id"]?>'}
    })
    .always(function() {
        window.location.href = '<?php echo $track->redirectUrl();?>';
    });
}
// Track basic JavaScript errors
window.addEventListener('error', function(e) {
    _gaq.push([
        '_trackEvent',
        'JavaScript Error',
        e.message,
        e.filename + ':  ' + e.lineno,
        true
    ]);
});

// Track AJAX errors (jQuery API)
$(document).ajaxError(function(e, request, settings) {
    _gaq.push([
        '_trackEvent',
        'Ajax error',
        settings.url,
        e.result,
        true
    ]);
});
</script>
</body>

</html>