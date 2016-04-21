<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="X-Frame-Options" content="deny">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo $track->link->title;?>" />
    <meta property="og:description" content="<?php echo $track->link->description;?>">
    <meta property="og:url" content="<?php echo URL;?>">
    <meta property="og:image" content="https://dh3fr73b75uve.cloudfront.net/images/resize/<?php $img = explode(".", $track->link->image); echo $img[0]."-560x292.".$img[1];?>">
    <meta property="og:site_name" content="Clicks99">
    <meta property="article:section" content="Pictures" />
    
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo $track->link->title;?>">
    <meta name="twitter:description" content="<?php echo $track->link->description;?>">
    <meta name="twitter:url" content="<?php echo URL;?>">

    <title><?php echo $track->link->title;?></title>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
</head>
<body>
<script type="text/javascript">
process();
function process() {
    $.ajax({
        url: 'includes/process.php',
        headers: { 'Clicks99Track': "<?php echo uniqid();?>" },
        type: 'GET',
        cache: true,
        data: {id: "<?php echo $_GET['id'];?>"},
        success: function (data) {
            window.location = '<?php echo $track->redirectUrl();?>';
        }
    });
}
</script>
</body>
</html>