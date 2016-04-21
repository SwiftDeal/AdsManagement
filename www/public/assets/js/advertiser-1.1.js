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

/**** FbModel: Controls facebook login/authentication ******/
(function (window, $) {
    var FbModel = (function () {
        function FbModel() {
            this.loaded = false;
            this.loggedIn = false;
        }

        FbModel.prototype = {
            init: function(FB) {
                this.loaded = true; var self = this;
                window.FB.getLoginStatus(function (response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                    }
                })

            },
            login: function(el) {
                var self = this;
                if (!this.loaded) {
                    self.init(window.FB);
                }
                if (!this.loggedIn) {
                    window.FB.login(function(response) {
                        if (response.status === 'connected') {
                            self._info(el);
                        } else {
                            alert('Please allow access to your Facebook account, for us to enable direct login to the  DinchakApps');
                        }
                    }, {
                        scope: 'public_profile, email'
                    });
                } else {
                    self._info(el);
                }
            },
            _info: function(el) {
                var loginType = el.data('action'), extra;

                if (typeof loginType === "undefined") {
                    extra = '';
                } else {
                    switch (loginType) {
                        case 'campaign':
                            extra = 'game/authorize/'+ el.data('campaign');
                            break;

                        default:
                            extra = '';
                            break;
                    }
                }
                window.FB.api('/me?fields=name,email,gender', function(response) {
                    window.request.create({
                        action: 'auth/fbLogin',
                        data: {
                            action: 'fbLogin',
                            loc: extra,
                            email: response.email,
                            name: response.name,
                            fbid: response.id,
                            gender: response.gender
                        },
                        callback: function(data) {
                            if (data.success == true && data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                alert('Something went wrong');
                            }
                        }
                    });
                });
            }
        };
        return FbModel;
    }());

    window.FbModel = new FbModel();
}(window, jQuery));

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
    
    $.ajaxSetup({cache: true});
    $.getScript('//connect.facebook.net/en_US/sdk.js', function () {
        FB.init({
            appId: '583482395136457',
            version: 'v2.5'
        });
        window.FbModel.init();
    });

    $(".fbLogin").on("click", function(e) {
        e.preventDefault();
        $(this).addClass('disabled');
        FbModel.login($(this));
        $(this).removeClass('disabled');
    });

    $(".campaignstat").click(function(e) {
        e.preventDefault();
        var item = $(this),
            campaign = item.data('campaign');
        item.html('<i class="fa fa-spinner fa-pulse"></i>');
        request.read({
            action: "analytics/campaign/0/" + campaign,
            callback: function(data) {
                item.html('RPM : <i class="fa fa-inr"></i> '+ data.stats.cpc +', Sessions : '+ data.stats.click +', Spent : <i class="fa fa-inr"></i> '+ data.stats.spent);
            }
        });
    });

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

    $(".fetch").submit(function(e) {
        e.preventDefault();
        var self = $(this);
        $(".fetch button").html('Fetching ...<i class="fa fa-spinner fa-pulse"></i>');
        request.create({
            action: "campaign/fetch",
            data: self.serialize(),
            callback: function(data) {
                $(".fetch button").html('Done');console.log(data);

                $('.meta_img').attr('src', data.meta.image);
                $('#meta_title').html(data.meta.title);
                $('#meta_url').html(data.meta.url);
                $('textarea').val(data.meta.description);
                $('#meta_description').html(data.meta.description);
                $('input[name="title"]').val(data.meta.title);
                $('input[name="url"]').val(data.meta.url);
                $('input[name="image_url"]').val(data.meta.image);

                $('#myTabs li:eq(0)').removeClass('active');
                $('#myTabs li:eq(1)').removeClass('hidden');
                $('#myTabs li:eq(1)').addClass('step-success');
                $('#myTabs li:eq(1) a').tab('show');
            }
        });
    });

    $("#finalize").click(function(e) {
        $('#myTabs li:eq(1)').removeClass('active');
        $('#myTabs li:eq(2)').removeClass('hidden');
        $('#myTabs li:eq(2)').addClass('step-success');
        $('#myTabs li:eq(2) a').tab('show');
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

function campaigns() {
    request.read({
        action: "analytics/campaign/" + today(),
        callback: function(data) {
            $('#today_click').html(data.stats.click);
            $('#today_rpm').html('<i class="fa fa-inr"></i> '+ data.stats.rpm);
            $('#today_earning').html('<i class="fa fa-inr"></i> '+ data.stats.earning);

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
}

//Google Analytics
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create', 'UA-74080200-1', 'auto');
ga('send', 'pageview');