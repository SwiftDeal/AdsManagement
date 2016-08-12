/**
 * Request Library a wrapper around jQuery Ajax
 * @param  {Object} window The Global window object
 * @param  {function} $      jQuery function
 * @return {Object}        A new object of the library
 * @author  Hemant Mann http://github.com/Hemant-Mann
 */
(function (window, $) {
    var Request = (function () {
        function Request() {
            this.api = window.location.origin + '/'; // Api EndPoint
            this.extension = '.json';

            this.entityMap = {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': '&quot;',
                "'": '&#39;',
                "/": '&#x2F;'
            };

            this.escapeHtml = function escapeHtml(string) {
                var self = this;
                return String(string).replace(/[&<>"'\/]/g, function (s) {
                    return self.entityMap[s];
                });
            };
        }

        Request.prototype = {
            get: function (opts, callback) {
                this._request(opts, 'GET', callback);
            },
            post: function (opts, callback) {
                this._request(opts, 'POST', callback);
            },
            delete: function (opts, callback) {
                this._request(opts, 'DELETE', callback);
            },
            _clean: function (entity) {
                if (!entity || entity.length === 0) {
                    return "";
                }
                return entity.replace(/\./g, '');
            },
            _request: function (opts, type, callback) {
                var link = this.api + this._clean(opts.url) + this.extension,
                    self = this;

                $.ajax({
                    url: link,
                    type: type,
                    data: opts.data || {},
                }).done(function (data) {
                    callback.call(self, null, data);
                }).fail(function (err) {
                    callback.call(self, err || "error", {});
                });
            }
        };
        return Request;
    }());
    // Because "window.Request" is already taken
    window.request = new Request();
}(window, jQuery));

(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-78834277-1', 'auto');
ga('send', 'pageview');

jQuery(document).ready(function($) {
    $('.counter').counterUp({
        delay: 100,
        time: 1200
    });
});

(function($) {

    'use strict';

    function initNavbar() {

        $('.navbar-toggle').on('click', function(event) {
            $(this).toggleClass('open');
            $('#navigation').slideToggle(400);
        });

        $('.navigation-menu>li').slice(-1).addClass('last-elements');

        $('.navigation-menu li.has-submenu a[href="#"]').on('click', function(e) {
            if ($(window).width() < 992) {
                e.preventDefault();
                $(this).parent('li').toggleClass('open').find('.submenu:first').toggleClass('open');
            }
        });
    }

    function init() {
        initNavbar();
    }

    init();

})(jQuery)



//portlets
! function($) {
    "use strict";

    /**
    Portlet Widget
    */
    var Portlet = function() {
        this.$body = $("body"),
            this.$portletIdentifier = ".portlet",
            this.$portletCloser = '.portlet a[data-toggle="remove"]',
            this.$portletRefresher = '.portlet a[data-toggle="reload"]'
    };

    //on init
    Portlet.prototype.init = function() {
            // Panel closest
            var $this = this;
            $(document).on("click", this.$portletCloser, function(ev) {
                ev.preventDefault();
                var $portlet = $(this).closest($this.$portletIdentifier);
                var $portlet_parent = $portlet.parent();
                $portlet.remove();
                if ($portlet_parent.children().length == 0) {
                    $portlet_parent.remove();
                }
            });

            // Panel Reload
            $(document).on("click", this.$portletRefresher, function(ev) {
                ev.preventDefault();
                var $portlet = $(this).closest($this.$portletIdentifier);
                // This is just a simulation, nothing is going to be reloaded
                $portlet.append('<div class="panel-disabled"><div class="loader-1"></div></div>');
                var $pd = $portlet.find('.panel-disabled');
                setTimeout(function() {
                    $pd.fadeOut('fast', function() {
                        $pd.remove();
                    });
                }, 500 + 300 * (Math.random() * 5));
            });
        },
        //
        $.Portlet = new Portlet, $.Portlet.Constructor = Portlet

}(window.jQuery),

/**
 * Components
 */
function($) {
    "use strict";

    var Components = function() {};

    //initializing tooltip
    Components.prototype.initTooltipPlugin = function() {
            $.fn.tooltip && $('[data-toggle="tooltip"]').tooltip()
        },

        //initializing popover
        Components.prototype.initPopoverPlugin = function() {
            $.fn.popover && $('[data-toggle="popover"]').popover()
        },

        //initializing custom modal
        Components.prototype.initCustomModalPlugin = function() {
            $('[data-plugin="custommodal"]').on('click', function(e) {
                Custombox.open({
                    target: $(this).attr("href"),
                    effect: $(this).attr("data-animation"),
                    overlaySpeed: $(this).attr("data-overlaySpeed"),
                    overlayColor: $(this).attr("data-overlayColor")
                });
                e.preventDefault();
            });
        },

        //initializing nicescroll
        Components.prototype.initNiceScrollPlugin = function() {
            //You can change the color of scroll bar here
            $.fn.niceScroll && $(".nicescroll").niceScroll({ cursorcolor: '#98a6ad', cursorwidth: '6px', cursorborderradius: '5px' });
        },

        //range slider
        Components.prototype.initRangeSlider = function() {
            $.fn.slider && $('[data-plugin="range-slider"]').slider({});
        },

        /* -------------
         * Form related controls
         */
        //switch
        Components.prototype.initSwitchery = function() {
            $('[data-plugin="switchery"]').each(function(idx, obj) {
                new Switchery($(this)[0], $(this).data());
            });
        },
        //multiselect
        Components.prototype.initMultiSelect = function() {
            if ($('[data-plugin="multiselect"]').length > 0)
                $('[data-plugin="multiselect"]').multiSelect($(this).data());
        },

        /* -------------
         * small charts related widgets
         */
        //peity charts
        Components.prototype.initPeityCharts = function() {
            $('[data-plugin="peity-pie"]').each(function(idx, obj) {
                var colors = $(this).attr('data-colors') ? $(this).attr('data-colors').split(",") : [];
                var width = $(this).attr('data-width') ? $(this).attr('data-width') : 20; //default is 20
                var height = $(this).attr('data-height') ? $(this).attr('data-height') : 20; //default is 20
                $(this).peity("pie", {
                    fill: colors,
                    width: width,
                    height: height
                });
            });
            //donut
            $('[data-plugin="peity-donut"]').each(function(idx, obj) {
                var colors = $(this).attr('data-colors') ? $(this).attr('data-colors').split(",") : [];
                var width = $(this).attr('data-width') ? $(this).attr('data-width') : 20; //default is 20
                var height = $(this).attr('data-height') ? $(this).attr('data-height') : 20; //default is 20
                $(this).peity("donut", {
                    fill: colors,
                    width: width,
                    height: height
                });
            });

            $('[data-plugin="peity-donut-alt"]').each(function(idx, obj) {
                $(this).peity("donut");
            });

            // line
            $('[data-plugin="peity-line"]').each(function(idx, obj) {
                $(this).peity("line", $(this).data());
            });

            // bar
            $('[data-plugin="peity-bar"]').each(function(idx, obj) {
                var colors = $(this).attr('data-colors') ? $(this).attr('data-colors').split(",") : [];
                var width = $(this).attr('data-width') ? $(this).attr('data-width') : 20; //default is 20
                var height = $(this).attr('data-height') ? $(this).attr('data-height') : 20; //default is 20
                $(this).peity("bar", {
                    fill: colors,
                    width: width,
                    height: height
                });
            });
        },



        //initilizing
        Components.prototype.init = function() {
            var $this = this;
            this.initTooltipPlugin(),
                this.initPopoverPlugin(),
                this.initNiceScrollPlugin(),
                this.initCustomModalPlugin(),
                this.initRangeSlider(),
                this.initSwitchery(),
                this.initMultiSelect(),
                this.initPeityCharts(),
                //creating portles
                $.Portlet.init();
        },

        $.Components = new Components, $.Components.Constructor = Components

}(window.jQuery),
//initializing main application module
function($) {
    "use strict";
    $.Components.init();
}(window.jQuery);


/**
 * jQuery.browser.mobile (http://detectmobilebrowser.com/)
 *
 * jQuery.browser.mobile will be true if the browser is a mobile device
 *
 **/
(function(a) {
    (jQuery.browser = jQuery.browser || {}).mobile = /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))
})(navigator.userAgent || navigator.vendor || window.opera);

$(function () {
    $('select[value]').each(function () {
        $(this).val(this.getAttribute("value"));
    });
});
$(document).ready(function() {
    //plugins initialized
    $('select').select2();
    $('.noselect2').select2('destroy');

    //initialize beautiful date picker
    $.fn.datepicker.defaults.format = "yyyy-mm-dd";
    $("input[type=date]").datepicker({
        format: 'yyyy-mm-dd'
    });

    // creates a new Resource by sending a POST request to the server
	$(document.body).on("click", ".newResource", function (e) {
		e.preventDefault();
		var self = $(this),
			href = self.attr('href') || self.data('href');

		if (!href) return bootbox.alert('Internal Server Error');

		request.post({ url: href, data: self.data('send') }, function (err, d) {
			if (err) {
				return bootbox.alert('Something went wrong!!');
			}

			bootbox.alert(d.message || 'Successful');
		});
	});

    $(document.body).on("click", ".fetchClickStats", function (e) {
        e.preventDefault();
        var self = $(this),
            data = self.data('send'),
            url = self.data('href');

        request.get({ url: url, data: data }, function (err, data) {
            if (err) {
                return bootbox.alert('Something went wrong!!');
            }

            self.html('Total Clicks: ' + data.total_click);
            var $span = self.parent().find('.earningStats');
            if ($span.length > 0) {
                $span.html('Total Earning: ' + data.total_earning);
            }
        });
    });

    // This is for adding 'selected="true"' on <option> of <select> tag
    var selectTags = $('.selectVal');
    if (selectTags.length > 0) {
        $.each(selectTags, function (i, el) {
            var $el = $(el);

            var optValue = $el.data('value');   // This will contain all the values of select tag
            optValue.forEach(function (val) {
                $el.find('option[value="' + val + '"]').attr('selected', true);
            });
        });
    }

    $('.createLink').on('click', function (e) {
        e.preventDefault();
        $('#campaignId').val($(this).data('adid'));
        $('#createLink').modal('show');
    });

    $('#createLinkForm').on('submit', function (e) {
        e.preventDefault();

        var self = $(this);
        request.post({ url: self.attr('action'), data: self.serialize() }, function (err, d) {
            $('#createLink').modal('hide');
            if (err) {
                return bootbox.alert('Internal Server Error');
            }

            bootbox.alert(d.message);
        });
    });
});