(function (window, $) {
    // local variables to be used in the script
    var document = window.document,
        Content,
        c,
        css;

    css = '\
        #bsaHolder{             right: 25px;position: absolute; top: 0; width: 345px;z-index: 10;}\
        #bsaHolder span{        text-shadow:1px 1px 0 #fff;}\
        #bsap_aplink,\
        #bsap_aplink:visited{   bottom: 10px;color: #aaa;font: 11px arial, sans-serif;position: absolute;right: 14px;border:none;}\
        #bsaHolder .bsa_it_p{ display: block; }\
        #bsaHolder .bsa_it_ad{  background: -moz-linear-gradient(#F3F3F3, #FFFFFF, #F3F3F3) repeat scroll 0 0 transparent; background: -webkit-gradient(linear,0% 0%,0% 100%,color-stop(0, #f3f3f3),color-stop(0.5, #fff),color-stop(1, #f3f3f3)); background-color:#f4f4f4;\
                                border-color: #fff;overflow: hidden;padding: 10px 10px 0;-moz-box-shadow: 0 0 2px #999;-webkit-box-shadow: 0 0 2px #999;box-shadow: 0 0 2px #999;\
                                -moz-border-radius: 0 0 4px 4px;-webkit-border-radius: 0 0 4px 4px;border-radius: 0 0 4px 4px;}\
        #bsaHolder img{         display:block;border:none;}\
        #bsa_closeAd{           width:15px;height:15px;overflow:hidden;position:absolute;top:10px;right:11px;border:none !important;z-index:1;\
                                text-decoration:none !important;background:url("http://cdn.tutorialzine.com/misc/enhance/x_icon.png") no-repeat;}\
        #bsa_closeAd:hover{     background-position:left bottom;}\
        .one{position:relative}.one .bsa_it_ad{display:block;padding:15px;border:1px solid #e1e1e1;background:#f9f9f9;font-family:helvetica,arial,sans-serif;line-height:100%;position:relative}.one .bsa_it_ad a{text-decoration:none}.one .bsa_it_ad a:hover{text-decoration:none}.one .bsa_it_ad .bsa_it_t{display:block;font-size:12px;font-weight:bold;color:#212121;line-height:125%;padding:0 0 5px 0}.one .bsa_it_ad .bsa_it_d{display:block;color:#434343;font-size:12px;line-height:135%}.one .bsa_it_ad .bsa_it_i{float:left;margin:0 15px 10px 0}body .one .bsa_it_p{text-align:center;display:block !important}.one .bsa_it_p a{font-size:10px;color:#666;text-decoration:none}.one .bsa_it_ad .bsa_it_p a:hover{font-style:italic}\
    ';

    // Create style for our Content
    (function (css, $) {
        var $style = $('head style');
        if ($style.length === 0) {
            $('head').append('<style>' + css + '</style>');
        } else {
            $style.append(css);
        }

        $('html').css('background-attachment', 'scroll');
    }(css, $));

    $.ajaxSetup({
        headers: {
            'X-Api': 'ServeAds',
            'X-Api-JSON': 'C99'
        }
    });

    Content = (function ($) {
        function Content() {
            this.domain = 'clicks99.com';
        }

        Content.prototype = {
            get: function () {
                var self = this;
                $.ajax({
                    url: 'http://' + self.domain + '/campaign/serve?xapi=c99ads&callback=?',
                    type: 'GET',
                    async: true,
                    jsonpCallback: 'jsonCallback',
                    contentType: "application/json",
                    dataType: 'jsonp',
                })
                .done(function (d) {
                    self.insert(d);
                })
                .fail(function () {
                    console.log("error");
                });
            }, // insert the content into the page
            insert: function (data) {
                var item = data,
                    self = this;

                $('body').prepend('<div id="bsaHolder"><a id="bsa_closeAd" title="Hide this ad!" href="#"></a><div class="bsap"><div class="bsa_it one"><div class="bsa_it_ad"><a href="' + item._url + '" target="_blank"><span class="bsa_it_i"><img src="' + item._image + '" width="130" height="100" alt="Content Image"></span></a><a href="' + item._url + '" target="_blank"><span class="bsa_it_d">' + item._title + '</span></a><div style="clear:both"></div></div><span class="bsa_it_p"><a href="http://' + self.domain + '/" target="_blank">ads via Clicks99</a></span></div></div></div>');
            }
        }

        return Content;
    }($));

    c = new Content();
    c.get(); // fetch Content using ajax

    // Add actions for various id's
    $(document.body).on("click", "#bsa_closeAd", function (e) {
        e.preventDefault();
        $(this).parent().remove();
        return false;
    });
}(window, jQuery));
