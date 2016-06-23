var a = "color:#F6782E;font-weight:bold;font-size:18px";
var f = "font-size:14px;font-weight:bold; font-style:italic;color:#2c67b3;";
var b = "CloudStuff";
var d= "\nHi there, Are you passionate about coding? So are we. Want to join us? Send email to faizan@cloudstuff.tech\n\n";
console.log("%c%s%c%s",a,b,f,d);

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

    $('select').select2();
    $('.noselect2').select2('destroy');

    //initialize beautiful date picker
    $.fn.datepicker.defaults.format = "yyyy-mm-dd";
    $("input[type=date]").datepicker({
        format: 'yyyy-mm-dd'
    });

    //initialize tooltip
    $('[data-toggle="tooltip"]').tooltip();

    $("#addmoney").submit(function(e) {
        e.preventDefault();
        var self = $(this);
        request.create({
            action: "finance/credit",
            data: self.serialize(),
            callback: function(data) {
                if (data.success == true) {
                    window.location.href = data.payurl;
                } else {
                    $("#addCredit").modal("hide");
                    alert(data.error);
                }
            }
        });
    });

    $('#link_data').mouseup(function() {
        $(this)[0].select();
    });

    $(".adunitstat").click(function(e) {
        e.preventDefault();
        var item = $(this),
            adunit = item.data('adunit');
        item.html('<i class="fa fa-spinner fa-pulse"></i>');
        request.read({
            action: "analytics/cunit",
            data: {id: adunit},
            callback: function(data) {
                item.html('Clicks : '+ data.clicks +', Impressions : '+ data.impressions);
            }
        });
    });

    $(".cstat").click(function(e) {
        e.preventDefault();
        var item = $(this),
            campaign = item.data('campaign');
        item.html('<i class="fa fa-spinner fa-pulse"></i>');
        request.read({
            action: "analytics/campaign",
            data: {id: campaign},
            callback: function(data) {
                item.html('Clicks : '+ data.clicks +', Impressions : '+ data.impressions);
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

    $(".code").click(function (e) {
        e.preventDefault();
        $('#code').html('');
        var btn = $(this),
            auid = btn.data('auid');
        request.read({
            action: "publisher/aucode",
            data: {auid: auid},
            callback: function (data) {
                $('#code').html(data.code);
                $("#getCode").modal("show");
            }
        });
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

function publisher() {
    request.read({
        action: "analytics/platforms",
        data: $('#range').serialize(),
        callback: function(data) {
            $('#impressions').html(data.impressions);
            $('#clicks').html(data.clicks);
            var ia = [];
            $.each(data.ianalytics, function(i, val) {
                ia.push({label: i, value: val})
            });
            Morris.Donut({
                element: 'itopcountry',
                data: ia
            });

            var igdpData = data.ianalytics;
            $('#imp-world-map').vectorMap({
                map: 'world_mill_en',
                series: {
                    regions: [{
                        values: igdpData,
                        scale: ['#C8EEFF', '#0071A4'],
                        normalizeFunction: 'polynomial'
                    }]
                },
                onRegionTipShow: function(e, el, code) {
                    if (igdpData.hasOwnProperty(code)) {
                        el.html(el.html() + ' (Impressions - ' + igdpData[code] + ')');
                    } else{
                        el.html(el.html() + ' (Impressions - 0)');
                    };
                }
            });

            var ca = [];
            $.each(data.canalytics, function(i, val) {
                ca.push({label: i, value: val})
            });
            Morris.Donut({
                element: 'ctopcountry',
                data: ca
            });
            
            var gdpData = data.canalytics;
            $('#clk-world-map').vectorMap({
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
                        el.html(el.html() + ' (Clicks - ' + gdpData[code] + ')');
                    } else{
                        el.html(el.html() + ' (Clicks - 0)');
                    };
                }
            });
        }
    });
}


function advertiser() {
    request.read({
        action: 'analytics/campaigns',
        data: $('#range').serialize(),
        callback: function(data) {
            $('#impressions').html(data.impressions);
            $('#clicks').html(data.clicks);
            $('#spent').html(data.spent);

            var ia = [];
            $.each(data.ianalytics, function(i, val) {
                ia.push({label: i, value: val})
            });
            Morris.Donut({
                element: 'itopcountry',
                data: ia
            });

            var igdpData = data.ianalytics;
            $('#imp-world-map').vectorMap({
                map: 'world_mill_en',
                series: {
                    regions: [{
                        values: igdpData,
                        scale: ['#C8EEFF', '#0071A4'],
                        normalizeFunction: 'polynomial'
                    }]
                },
                onRegionTipShow: function(e, el, code) {
                    if (igdpData.hasOwnProperty(code)) {
                        el.html(el.html() + ' (Impressions - ' + igdpData[code] + ')');
                    } else{
                        el.html(el.html() + ' (Impressions - 0)');
                    };
                }
            });

            var ca = [];
            $.each(data.canalytics, function(i, val) {
                ca.push({label: i, value: val})
            });
            Morris.Donut({
                element: 'ctopcountry',
                data: ca
            });

            var gdpData = data.canalytics;
            $('#clk-world-map').vectorMap({
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
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-78834277-1', 'auto');
ga('send', 'pageview');