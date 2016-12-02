/**
 * Publisher Controller, requires - request.js, chart.js, components.js, browser.js
 */
(function (window, $) {
    "use strict";
    var Publisher = (function () {
        function Publisher() {
            
        }

        Publisher.prototype = {
            createLink: function () {
                $('.createLink').on('click', function (e) {
                    e.preventDefault();
                    $('#campaignId').val($(this).data('adid'));
                    $('#createLink').modal('show');
                });
            },
            createLinkForm: function () {
                $('#createLinkForm').on('submit', function (e) {
                    e.preventDefault();

                    var self = $(this);
                    request.post({ url: self.attr('action'), data: self.serialize() }, function (err, d) {
                        $('#createLink').modal('hide');
                        if (err) {
                            return bootbox.alert('Internal Server Error');
                        }

                        $('#showLinkBox').modal('show');
                        $('#showLinkMessage').html(d.message);
                        $('#showLinkUrl').val(d.message+'\n'+d.link);
                        var clipboard = new Clipboard('.btn');
                    });
                });
            },
            fetchClickStats: function () {
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
            },
            newResource: function () {
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
            },
            init: function () {
                $('.izoom').click(function (e) {
                    e.preventDefault();
                    $('#ibody').html('<img src="'+ $(this).attr('src') +'" class="img-responsive">');
                    $('#zoomImage').modal('show');
                });
                this.createLink(),
                this.createLinkForm(),
                this.fetchClickStats(),
                this.newResource();
            }
        };
        return Publisher;
    }());

    window.publisher = new Publisher();
}(window, jQuery));

//initializing main publisher module
(function($) {
    "use strict";
    publisher.init();
}(window.jQuery));