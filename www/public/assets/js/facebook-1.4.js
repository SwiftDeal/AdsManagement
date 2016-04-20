/**** FbModel: Controls facebook login/authentication ******/
(function (window, $) {
    var FbModel = (function () {
        function FbModel() {
            this.loggedIn = false;
            this.user = false;
        }

        FbModel.prototype = {
            init: function() {
                var self = this;

                window.FB.getLoginStatus(function (response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                    }
                });

                window.request.read({
                    action: 'publisher',
                    callback: function (d) {
                        if (d.user) {
                            self.user = true;
                        }
                    }
                });
            },
            _login: function (callback) {
                var self = this;
                window.FB.login(function(response) {
                    if (response.status === 'connected') {
                        callback.call(self);
                    } else {
                        alert('Please allow access to your Facebook account, for us to enable direct login to Clicks99');
                    }
                }, {
                    scope: 'public_profile, email, publish_pages, read_insights, manage_pages'
                });
            },
            __appLogin: function (opts) {
                var self = this;
                if (!self.user) return false;

                if (opts.callback) {
                    opts.callback.call(self, opts.data);
                } else if (opts.el && opts.el.attr('data-target')) {
                    window.location.href = opts.el.attr('data-target');
                } else {
                    window.location.href = '/publisher/index.html';
                }
            },
            _access: function(el, cb) {
                var self = this;
                self.__appLogin({ callback: cb })
                window.FB.api('/me?fields=name,email,gender', function(response) {
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
                            if (data.success) {
                                self.user = true;
                                self.__appLogin({ el: el, callback: cb, data: data });
                            }
                        }
                    });
                });
            },
            _authorize: function(el, callback) {
                var self = this;
                window.FB.api('/me?fields=name,email,gender', function(response) {
                    window.request.create({
                        action: 'facebook/fbauthorize',
                        data: {
                            action: 'fbauthorize',
                            email: response.email,
                            fbid: response.id,
                            access_token: window.FB.getAuthResponse()['accessToken']
                        },
                        callback: function (d) {
                            callback.call(self, d);
                        }
                    });
                });
            },
            _pages: function(el, callback) {
                var self = this, i, pages, len;
                window.FB.api('/me/accounts?fields=name,id,can_post,category,likes,website', function (response) {
                    pages = response.data;
                    for (i = 0, len = pages.length; i < len; ++i) {
                        window.request.create({
                            action: 'facebook/addpage',
                            data: pages[i],
                            callback: function (data) {
                                if (data.success == true) {
                                    console.log("Adding Page Done");
                                } else {
                                    alert('Cannot Post on ' + r.name);
                                }
                            }
                        });
                    }
                    callback.call(self, pages);
                });
            },
            __post: function (element) {
                var pageid = $('#fb_page_id').val(),
                    btn = element.find('button[type="submit"]');

                btn.html('<i class="fa fa-spinner fa-spin"></i> Please Wait..');
                window.FB.api('/' + pageid + '?fields=access_token', function (response) {
                    window.FB.api('/' + pageid + '/feed', 'post', {
                        message: $('#link_data').val(),
                        link: $('#link_data').data('uri'),
                        access_token: response.access_token
                    }, function (r) {
                        $('#fbpages_modal').modal('hide');
                        alert('This was posted to Facebook!!');
                        window.request.create({
                            action: 'facebook/pagePost',
                            data: { action: 'addPost', pageid: pageid, postid: r.id, short: $('#link_data').data('uri'), link_id: $('#link_data').data('link_id') },
                            callback: function (d) {
                                element.removeData('processing');
                                btn.html('<i class="fa fa-submit"></i> GO');
                            }
                        });
                    });
                });
            },
            _postToPages: function (cb) {
                var self = this, el = $('#fb_page_id');
                if (el.has('option').length > 0) {
                    $('#link_modal').modal('hide');
                    $('#fbpages_modal').modal('show');
                    return;
                }
                self._pages(el, function (pages) {
                    el.html('');
                    for (var i = 0, max = pages.length; i < max; ++i) {
                        el.append('<option value="' + pages[i].id + '">' + pages[i].name + '</option>');
                    }
                    $('#link_modal').modal('hide');
                    $('#fbpages_modal').modal('show');
                    cb.call(self);
                });
            },
            _process: function (el) {
                // authorize the user and do what the action is given
                var self = this, action;
                self._authorize(el, function (d) {
                    el.html('Done');
                    action = el.data('action') || '';
                    switch (action) {
                        case 'pages':
                            self._pages(el);
                            break;

                        case 'login': // login in the website
                            self._access(el);
                            break;

                        case 'postToPages':
                            self._postToPages();
                            break;
                        
                        default:
                            self._access(el);
                            break;
                    }  
                })
            },
            router: function(el) {
                var self = this;
                if (!self.loggedIn) { // let the user log in the app
                    self._login(function () {
                        self._process(el);
                    });
                } else { // user logged into fb (and is returning user)
                    if (!self.user) {
                        self._access(el, function () {
                            self._process(el);
                        });
                    } else {
                        self._process(el);
                    }
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
        var el = $(this),
            processing = el.data('processing');

        if (processing === "fb") return;
        el.data('processing', "fb");
        $(this).html('<i class="fa fa-spinner fa-spin"></i> Processing');
        FbModel.router($(this));
    });

    $('#fbpages_post').on('submit', function (e) {
        e.preventDefault();
        var el = $(this),
            processing = el.data('processing');
        
        if (processing === "postingToFB") return;
        el.data('processing', "postingToFB");
        FbModel.__post(el);
    });
});