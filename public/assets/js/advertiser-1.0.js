(function(window) {

    var Model = (function() {
        function Model(opts) {
            this.api = window.location.origin + '/';
            this.ext = '.json';
        }

        Model.prototype = {
            create: function(opts) {
                var self = this,
                    link = this._clean(this.api) + this._clean(opts.action) + this._clean(this.ext);
                $.ajax({
                    url: link,
                    type: 'POST',
                    data: opts.data,
                }).done(function(data) {
                    if (opts.callback) {
                        opts.callback.call(self, data);
                    }
                }).fail(function() {
                    console.log("error");
                }).always(function() {
                    //console.log("complete");
                });
            },
            read: function(opts) {
                var self = this,
                    link = this._clean(this.api) + this._clean(opts.action) + this._clean(this.ext);
                $.ajax({
                    url: link,
                    type: 'GET',
                    data: opts.data,
                }).done(function(data) {
                    if (opts.callback) {
                        opts.callback.call(self, data);
                    }
                }).fail(function() {
                    console.log("error");
                }).always(function() {
                    //console.log("complete");
                });

            },
            _clean: function(entity) {
                return entity || "";
            }
        };
        return Model;
    }());

    Model.initialize = function(opts) {
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

//zopim chat
window.$zopim||(function(d,s){var z=$zopim=function(c){z._.push(c)},$=z.s=
d.createElement(s),e=d.getElementsByTagName(s)[0];z.set=function(o){z.set.
_.push(o)};z._=[];z.set._=[];$.async=!0;$.setAttribute("charset","utf-8");
$.src="//v2.zopim.com/?3UYLJ4Bx85yteg0JLqupr1VkHpkSBm5L";z.t=+new Date;$.
type="text/javascript";e.parentNode.insertBefore($,e)})(document,"script");