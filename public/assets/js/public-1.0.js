/* ---- mixitup.js our work sorting ---- */
$('#thework').mixItUp({
    animation: {
		duration: 1000
    }
});

/* ---- our ideology hover ---- */
$('.process-box').hover(function() {
	$(this).find('.process-intro').hide();
	$(this).find('.process-content').fadeIn();
}, function() {
	$(this).find('.process-content').hide();
	$(this).find('.process-intro').fadeIn();
});

/* ---- contact form ---- */
$("#contactForm").validator().on("submit", function(event) {
    if (event.isDefaultPrevented()) {
        formError();
        submitMSG(false, "Did you fill in the form properly?");
    } else {
        event.preventDefault();
        submitForm();
    }
});
function submitForm() {
    // Initiate Variables With Form Content
    var name = $("#name").val();
    var email = $("#email").val();
    var msg_subject = $("#msg_subject").val();
    var message = $("#message").val();
    $.ajax({
        type: "POST",
        url: "php/contact.php",
        data: "name=" + name + "&email=" + email + "&msg_subject=" +
            msg_subject + "&message=" + message,
        success: function(text) {
            if (text == "success") {
                formSuccess();
            } else {
                formError();
                submitMSG(false, text);
            }
        }
    });
}
function formSuccess() {
    $("#contactForm")[0].reset();
    submitMSG(true, "Message Submitted!")
}
function formError() {
    $("#contactForm").removeClass().addClass('shake animated').one(
        'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend',
        function() {
            $(this).removeClass();
        });
}
function submitMSG(valid, msg) {
    if (valid) {
        var msgClasses = "h4 text-success";
    } else {
        var msgClasses = "h4 text-danger";
    }
    $("#msgSubmit").removeClass().addClass(msgClasses).text(msg);
}

/* ---- our work gallery ---- */
$('#work').magnificPopup({
    delegate: 'a.zoom',
    type: 'image',
    fixedContentPos: false,
    removalDelay: 300,
    mainClass: 'mfp-fade',
    gallery: {
        enabled: true,
        preload: [0,2]
    }
});

/* ---- popup image ---- */
$('.popup-img').magnificPopup({
    type: 'image',
    removalDelay: 300,
    mainClass: 'mfp-fade'
});

/* ---- popup video ---- */
$(document).ready(function() {
    $('.popup-youtube, .popup-vimeo, .popup-gmaps').magnificPopup({
        disableOn: 700,
        type: 'iframe',
        mainClass: 'mfp-fade',
        removalDelay: 160,
        preloader: false,
        fixedContentPos: false
    });
});

/* ---- nav smooth scroll ---- */
$(document).ready(function() {
    $('.scroll-link').on('click', function(event){
        event.preventDefault();
        var sectionID = $(this).attr("data-id");
        scrollToID('#' + sectionID, 750);
    });
    $('.scroll-top').on('click', function(event) {
        event.preventDefault();
        $('html, body').animate({scrollTop:0}, 1200);       
    });
});

/* ---- navbar offset ---- */
function scrollToID(id, speed){
    var offSet = 69;
    var targetOffset = $(id).offset().top - offSet;
    $('html,body').animate({scrollTop:targetOffset}, speed);
}

/* ---- animations ---- */
if (typeof sr == 'undefined') {
    window.sr = ScrollReveal({
        duration: 1500,
        delay: 50
    });
}
Royal_Preloader.config({
    onComplete: function () {
        triggerReveals();
    }
});
function triggerReveals() {
    sr.reveal('.bottomReveal', {
        origin: 'bottom'
    }).reveal('.leftReveal', {
        origin: 'left'
    }).reveal('.rightReveal', {
        origin: 'right'
    }).reveal('.topReveal', {
        origin: 'top'
    });

    sr.reveal('.rotateBottomReveal', {
        origin: 'bottom',
        rotate: { x: 90 }
    }).reveal('.rotateLeftReveal', {
        origin: 'left',
        rotate: { x: 90 }
    }).reveal('.rotateRightReveal', {
        origin: 'right',
        rotate: { x: 90 }
    }).reveal('.rotateTopReveal', {
        origin: 'top',
        rotate: { x: 90 }
    })

    sr.reveal('.scaleReveal', {
        origin: 'top',
        scale: 0.6
    });
}

/* ---- close mobile nav on click ---- */
$(document).on('click','.navbar-collapse.in',function(e) {
    if( $(e.target).is('a') && $(e.target).attr('class') != 'dropdown-toggle' ) {
        $(this).collapse('hide');
    }
});

/* ---- Counter (our count) ---- */
$('#ourcount').one('inview', function(event, isInView) {
    if (isInView) {
        $('.timer').countTo({speed: 3000});
    }
});

/* ---- Google Maps ---- */
function initMap() {
    var mapOptions = {
        zoom: 15,
        zoomControl: false,
        scaleControl: false,
        scrollwheel: false,
        disableDoubleClickZoom: true,
        center: new google.maps.LatLng(40.6700, -73.9400), // New York
        styles: [{"featureType":"landscape","stylers":[{"saturation":-100},{"lightness":65},{"visibility":"on"}]},{"featureType":"poi","stylers":[{"saturation":-100},{"lightness":51},{"visibility":"simplified"}]},{"featureType":"road.highway","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"road.arterial","stylers":[{"saturation":-100},{"lightness":30},{"visibility":"on"}]},{"featureType":"road.local","stylers":[{"saturation":-100},{"lightness":40},{"visibility":"on"}]},{"featureType":"transit","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"administrative.province","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"labels","stylers":[{"visibility":"on"},{"lightness":-25},{"saturation":-100}]},{"featureType":"water","elementType":"geometry","stylers":[{"hue":"#ffff00"},{"lightness":-25},{"saturation":-97}]}]
    };
    var mapElement = document.getElementById('map');
    var map = new google.maps.Map(mapElement, mapOptions);
    var marker = new google.maps.Marker({
        position: new google.maps.LatLng(40.6700, -73.9400),
        map: map,
        title: 'Our Office!'
    });
}

//Google Analytics
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-78834277-1', 'auto');
ga('send', 'pageview');

//Facebook Pixel Code
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');

fbq('init', '1610620902522914');
fbq('track', "PageView");