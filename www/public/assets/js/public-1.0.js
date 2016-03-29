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

(function (window, Model) {
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
                        scope: 'public_profile, email, publish_pages, read_insights, manage_pages'
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

$(document).ready(function() {
    $.ajaxSetup({cache: true});
    $.getScript('//connect.facebook.net/en_US/sdk.js', function () {
        FB.init({
            appId: '583482395136457',
            version: 'v2.5'
        });
        window.FbModel.init();
    });

    $(".fb").on("click", function(e) {
        e.preventDefault();
        $(this).addClass('disabled');
        FbModel.login($(this));
        $(this).removeClass('disabled');
    });
});

//Google Analytics
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create', 'UA-74080200-1', 'auto');
ga('send', 'pageview');