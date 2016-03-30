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
            _authorize: function(el) {
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
                                callback: function(data) {
                                    if (data.success == true) {
                                        console.log("Authorizing");
                                        console.log(response);
                                    } else {
                                        alert('Some thing went Wrong');
                                    }
                                }
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
                    self._authorize(el);
                }
                if (this.loggedIn) {
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
                }
            }
        };
        return FbModel;
    }());

    window.FbModel = new FbModel();
}(window, jQuery));