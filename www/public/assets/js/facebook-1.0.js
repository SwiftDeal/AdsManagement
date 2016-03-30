/**** FbModel: Controls facebook login/authentication ******/
(function (window, $) {
    var FbModel = (function () {
        function FbModel() {
            this.loaded = false;
            this.loggedIn = false;
        }

        FbModel.prototype = {
            init: function() {
                this.loaded = true; var self = this;

                window.FB.getLoginStatus(function (response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                    }
                });
            },
            login: function(el) {
                var self = this;
                window.FB.login(function(response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                        console.log("Logging in");
                        self._access(el);
                    } else {
                        alert('Please allow access to your Facebook account, for us to enable direct login to Clicks99');
                    }
                }, {
                    scope: 'public_profile, email, publish_pages, read_insights, manage_pages'
                });
            },
            _access: function(el) {
                window.FB.api('/me?fields=name,email,gender', function(response) {
                    console.log(response);
                    window.request.create({
                        action: 'facebook/fblogin',
                        data: {
                            action: 'fblogin',
                            email: response.email,
                            name: response.name,
                            fbid: response.id,
                            gender: response.gender
                        },
                        callback: function(data) {
                            if (data.success == true) {
                                if (el.attr('data-target')) {
                                    window.location.href = el.attr('data-target');
                                }
                            }
                        }
                    });
                });
            },
            _authorize: function(el, callback) {
                var self = this;
                window.FB.login(function(response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                        window.FB.api('/me?fields=name,email,gender', function(response) {
                            window.request.create({
                                action: 'facebook/fbauthorize',
                                data: {
                                    action: 'fbauthorize',
                                    email: response.email,
                                    fbid: response.id
                                },
                                callback: callback
                            });
                        });
                    } else {
                        alert('Please allow access to your Facebook account, for us to enable direct login to Clicks99');
                    }
                }, {
                    scope: 'public_profile, email, publish_pages, read_insights, manage_pages'
                });
            },
            pages: function(el) {
                window.FB.api('/me/accounts', function(response) {
                    //add to db
                    console.log(response.data);
                    $.each(response.data, function(i, page) {
                        window.FB.api('/'+ page.id +'?fields=name,id,can_post,category,likes,website', function(r) {
                            //add to db
                            console.log(r);
                            window.request.create({
                                action: 'facebook/addpage',
                                data: r,
                                callback: function(data) {
                                    if (data.success == true) {
                                        console.log("Adding Page Done");
                                    } else {
                                        alert('Cannot Post on ' + r.name);
                                    }
                                }
                            });
                        });
                    });
                });
            },
            _router: function(el) {
                var self = this;
                if (!this.loggedIn) {
                    console.log('Not Logged In');
                    self._authorize(el, function (data) {
                        if (data.success == true) {
                            console.log("Authorized");
                            console.log(response);
                        }
                        console.log("Routing...");
                        var action = el.data('action') || '';
                        switch (action) {
                            case 'pages':
                               self.pages(el);
                                break;

                            case 'login':
                               self.login(el);
                                break;
                            
                            default:
                                // do something
                                self.pages(el);
                                break;
                        }
                    });
                }
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
        $(this).html('<i class="fa fa-spinner spin"></i> Processing');
        FbModel._router($(this));
        $(this).html('Done');
        $(this).removeClass('disabled');
    });
});