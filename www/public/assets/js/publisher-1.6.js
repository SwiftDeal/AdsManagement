var a = "color:#F6782E;font-weight:bold;font-size:18px";
var f = "font-size:14px;font-weight:bold; font-style:italic;color:#2c67b3;";
var b = "CloudStuff";
var d= "\nHi there, Are you passionate about coding? So are we. Want to join us? Send email to faizan@cloudstuff.tech\n\n";
//console.log("%c%s%c%s",a,b,f,d);

(function (window) {

    var Model = (function () {
        function Model(opts) {
            this.api = window.location.origin + '/';
            this.ext = '.json';
        }

        Model.prototype = {
            create: function (opts) {
                var self = this,
                        link = this._clean(this.api) + this._clean(opts.action) + this._clean(this.ext);
                $.ajax({
                    url: link,
                    type: 'POST',
                    data: opts.data,
                }).done(function (data) {
                    if (opts.callback) {
                        opts.callback.call(self, data);
                    }
                }).fail(function () {
                    console.log("error");
                }).always(function () {
                    //console.log("complete");
                });
            },
            read: function (opts) {
                var self = this,
                        link = this._clean(this.api) + this._clean(opts.action) + this._clean(this.ext);
                $.ajax({
                    url: link,
                    type: 'GET',
                    data: opts.data,
                }).done(function (data) {
                    if (opts.callback) {
                        opts.callback.call(self, data);
                    }
                }).fail(function () {
                    console.log("error");
                }).always(function () {
                    //console.log("complete");
                });

            },
            _clean: function (entity) {
                return entity || "";
            }
        };
        return Model;
    }());

    Model.initialize = function (opts) {
        return new Model(opts);
    };

    window.Model = Model;
}(window));
(function(window, Model) {
    window.request = Model.initialize();
    window.opts = {};
}(window, window.Model));

$(function() {
    $('select[value]').each(function() {
        $(this).val(this.getAttribute("value"));
    });
});

$(function() {
    $(".navbar-expand-toggle").click(function() {
        $(".app-container").toggleClass("expanded");
        return $(".navbar-expand-toggle").toggleClass("fa-rotate-90");
    });
    return $(".navbar-right-expand-toggle").click(function() {
        $(".navbar-right").toggleClass("expanded");
        return $(".navbar-right-expand-toggle").toggleClass("fa-rotate-90");
    });
});

$(function() {
    return $('.match-height').matchHeight();
});

$(function() {
    return $(".side-menu .nav .dropdown").on('show.bs.collapse', function() {
        return $(".side-menu .nav .dropdown .collapse").collapse('hide');
    });
});

$(document).ready(function() {

    $(".shortenURL").click(function(e) {
        e.preventDefault();
        var btn = $(this),
            title = btn.data('title'),
            item = btn.data('item'),
            link_data = $('#link_data');

        btn.addClass('disabled');
        request.read({
            action: "publisher/shortenURL",
            data: {item: item},
            callback: function(data) {
                var uri = data.shortURL;
                btn.removeClass('disabled');
                btn.closest('div').find('.shorturl').val(data.shortURL);
                btn.closest('div').find('.shorturl').focus();
                link_data.val(title + "\n" + uri);
                link_data.data('uri', uri);
                link_data.data('link_id', data.link._id);
                
                $('#link_modal').modal('show');
                $('#link_modal_fb').attr('href', 'https://www.facebook.com/sharer/sharer.php?u='+data.shortURL);
                document.execCommand('SelectAll');
                document.execCommand("Copy", false, null);
            }
        });
    });

    $('#link_data').mouseup(function() {
        $(this)[0].select();
    });

    $(".linkstat").click(function(e) {
        e.preventDefault();
        var item = $(this),
            link = item.data('link');
        item.html('<i class="fa fa-spinner fa-pulse"></i>');
        request.read({
            action: "analytics/link",
            data: {link: link},
            callback: function(data) {
                item.html('RPM : <i class="fa fa-inr"></i> '+ data.rpm +', Sessions : '+ data.click +', Earning : <i class="fa fa-inr"></i> '+ data.earning);
            }
        });
    });

    $(".stats").click(function (e) {
        e.preventDefault();
        var self = $(this);
        self.addClass('disabled');
        stats();
        self.removeClass('disabled');
    });

    $(".fbshare").click(function (e) {
        e.preventDefault();
        var self = $(this);
        self.addClass('disabled');
        ouvre("https://www.facebook.com/sharer/sharer.php?u=" + self.attr('href'));
        self.removeClass('disabled');
    });

    $("#payout").click(function (e) {
        e.preventDefault();
        var self = $(this);
        self.addClass('disabled');
        self.prop('disabled', true);
        self.html('Processing ....<i class="fa fa-spinner fa-pulse"></i>');
        request.read({
            action: "finance/payout",
            callback: function(data) {
                self.html('Successfully Done!!!');
            }
        });
    });

    $(".fbLinkStat").on("click", function (e) {
        e.preventDefault();
        var el = $(this),
        processing = el.data('processing');
        el.html('<i class="fa fa-spinner fa-spin"></i> Fetching..');

        if (processing) return;
        el.data('processing', true);
        window.request.create({
            action: 'facebook/postStats',
            data: { link_id: el.data('link'), action: 'showStats' },
            callback: function (d) {
                if (d.success) {
                    el.html('Clicks: ' + d.clicks);
                    el.removeData('processing');
                } else {
                    alert("Invalid request!!");
                }
            }
        });
    });
});

function today () {
    var today = new Date();
    var dd = today.getDate();
    var mm = today.getMonth()+1; //January is 0!
    var yyyy = today.getFullYear();

    if(dd<10) {
        dd='0'+dd
    } 

    if(mm<10) {
        mm='0'+mm
    } 

    today = yyyy+'-'+mm+'-'+dd;
    return today;
}

function stats() {
    //loading visitors map
    request.read({
        action: "analytics/stats/" + today(),
        callback: function(data) {
            $('#today_click').html(data.stats.click);
            $('#today_rpm').html(data.stats.rpm);
            $('#today_earning').html(data.stats.earning);

            var gdpData = data.stats.analytics;
            $('#world-map').vectorMap({
                map: 'world_mill_en',
                series: {
                    regions: [{
                        values: gdpData,
                        scale: ['#C8EEFF', '#0071A4'],
                        normalizeFunction: 'polynomial'
                    }]
                },
                onRegionTipShow: function(e, el, code) {
                    if (gdpData.hasOwnProperty(code)) {
                        el.html(el.html() + ' (Sessions - ' + gdpData[code] + ')');
                    } else{
                        el.html(el.html() + ' (Sessions - 0)');
                    };
                }
            });
        }
    });

    //finance stats
    $('#finstats').html('<p class="text-center"><i class="fa fa-spinner fa-spin fa-5x"></i></p>');
    request.read({
        action: "finance/stats",
        callback: function (data) {
            $('#finstats').html('');
            if (data.data) {
                Morris.Bar({
                    element: 'finstats',
                    data: toArray(data.data),
                    xkey: 'y',
                    ykeys: ['a'],
                    labels: ['Total']
                });
            }
        }
    });
}

function ouvre(fichier) {
    ff=window.open(fichier,"popup","width=600px,height=300px,left=50%,top=50%");
}

function toArray(object) {
    var array = $.map(object, function (value, index) {
        return [value];
    });
    return array;
}

//Google Analytics
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create', 'UA-74080200-1', 'auto');
ga('send', 'pageview');