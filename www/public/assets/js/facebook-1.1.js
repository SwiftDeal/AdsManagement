/**** FbModel: Controls facebook login/authentication ******/
(function (window, $) {
    var FbModel = (function () {
        function FbModel() {
            this.loggedIn = false;
        }

        FbModel.prototype = {
            init: function() {
                var self = this;

                window.FB.getLoginStatus(function (response) {
                    if (response.status === 'connected') {
                        self.loggedIn = true;
                    }
                });
            },
            _access: function(el, callback) {
                var self = this;
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
                            if (callback) {
                                callback.call(self, data);
                                return;
                            }
                            if (data.success == true) {
                                if (el.attr('data-target')) {
                                    window.location.href = el.attr('data-target');
                                } else {
                                    window.location.href = '/publisher';
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
                                callback: function (d) {
                                    callback.call(self, d);
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
            __post: function () {
                var pageid = $('#fb_page_id').val();
                window.FB.api('/' + pageid + '?fields=access_token', function (response) {
                    window.FB.api('/' + pageid + '/feed', 'post', {
                        message: $('#link_data').val(),
                        link: $('#link_data').data('uri'),
                        access_token: response.access_token
                    }, function (r) {
                        window.request.create({
                            action: 'facebook/pagePost',
                            data: { action: 'addPost', pageid: pageid, postid: r.id, short: $('#link_data').data('uri') },
                            callback: function (d) {
                                $('#fbpages_modal').modal('hide');
                                if (d.success) {
                                    alert('This was posted to facebook!!');
                                } else {
                                    alert('Unable to post on facebook :(');
                                }
                            }
                        });
                    });
                });
            },
            _postToPages: function () {
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
                });
            },
            _process: function (el) {
                // authorize the user and do what the action is given
                var self = this, action;
                self._authorize(el, function (d) {
                    action = el.data('action') || '';
                    switch (action) {
                        case 'pages':
                           self._pages(el);
                            break;

                        case 'login':
                           self._access(el); // login user in website
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
                if (!this.loggedIn) { // let the user log in the app
                    self._access(el, function (data) {
                        self._process(el);
                    })
                } else { // user logged into fb (and is returning user)
                    self._process(el);
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
        FbModel.router($(this));
        $(this).html('Done');
        $(this).removeClass('disabled');
    });

    $('#fbpages_post').on('submit', function (e) {
        e.preventDefault();
        FbModel.__post();
    });
});