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
        #bsaHolder .bsa_it_p{   display:none;}\
        #bsaHolder .bsa_it_ad{  background: -moz-linear-gradient(#F3F3F3, #FFFFFF, #F3F3F3) repeat scroll 0 0 transparent; background: -webkit-gradient(linear,0% 0%,0% 100%,color-stop(0, #f3f3f3),color-stop(0.5, #fff),color-stop(1, #f3f3f3)); background-color:#f4f4f4;\
                                border-color: #fff;overflow: hidden;padding: 10px 10px 0;-moz-box-shadow: 0 0 2px #999;-webkit-box-shadow: 0 0 2px #999;box-shadow: 0 0 2px #999;\
                                -moz-border-radius: 0 0 4px 4px;-webkit-border-radius: 0 0 4px 4px;border-radius: 0 0 4px 4px;}\
        #bsaHolder img{         display:block;border:none;}\
        #bsa_closeAd{           width:15px;height:15px;overflow:hidden;position:absolute;top:10px;right:11px;border:none !important;z-index:1;\
                                text-decoration:none !important;background:url("http://cdn.tutorialzine.com/misc/enhance/x_icon.png") no-repeat;}\
        #bsa_closeAd:hover{     background-position:left bottom;}\
    ';

    // Create style for our Content
    (function (css) {
        var style = document.createElement('style');
        
        style.setAttribute("type", "text/css");
        if (style.styleSheet) {   // IE
            style.styleSheet.cssText = str;
        } else {                // the world
            style.appendChild(document.createTextNode(str));
        }
        
        $('head').append(style);
        $('html').css('background-attachment','scroll');
    }(css));

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
                    url: 'http://'+ self.domain +'/campaign/serve',
                    type: 'GET'
                    
                })
                .done(function(d) {
                    self.insert(d);
                })
                .fail(function() {
                    console.log("error");
                });
            }, // insert the content into the page
            insert: function (data) {
                if (!data.item) {
                    return false;
                }

                var item = data.item,
                    self = this;

                $('body').prepend('<div id="bsaHolder"><a id="bsa_closeAd" title="Hide this ad!" href=""></a><div class="bsap"><div class="bsa_it one"><div class="bsa_it_ad"><a href="'+ item._url +'" target="_blank"><span class="bsa_it_i"><img src="'+ item._image +'" width="130" height="100" alt="Content Image"></span></a><a href="'+ item._url +'" target="_blank"><span class="bsa_it_d">' + item._title + '</span></a><div style="clear:both"></div></div><span class="bsa_it_p"><a href="http://'+ self.domain +'/" target="_blank">ads via Clicks99</a></span></div></div></div>');
            }
        }

        return Content;
    }($));

    c = new Content();
    c.get();    // fetch Content using ajax

    // Add actions for various id's
}(window, jQuery));