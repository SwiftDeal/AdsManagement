/**
Author: vNative Dev Team
Email: info@vnative.com
File: jvectormap
*/
(function($) {
    "use strict";

    var VectorMap = function() {};

    VectorMap.prototype.init = function(obj) {
        //various examples
        $('#country').vectorMap({
            map: 'world_mill_en',
            normalizeFunction: 'polynomial',
            hoverOpacity: 0.7,
            hoverColor: false,
            series: {
            	regions: [{
            		values: obj.country,
            		scale: ['#C8EEFF', '#0071A4'],
            		normalizeFunction: 'polynomial'
            	}]
            },
            onRegionTipShow: function(e, el, code){
            	if(obj.country.hasOwnProperty(code)){
            		el.html(el.html()+' (Clicks - '+obj.country[code]+')');
            	} else {
            		el.html(el.html()+' (Clicks - 0');
            	}
            }
        });
    },
    //init
    $.VectorMap = new VectorMap, $.VectorMap.Constructor = VectorMap;
}(window.jQuery));