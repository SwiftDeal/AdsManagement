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
                    e.preventDefault(); var self = $(this);
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
            init: function () {
                this.createLink(),
                this.createLinkForm();
            }
        };
        return Publisher;
    }());

    window.publisher = new Publisher();
}(window, jQuery))

//initializing main publisher module
function($) {
    "use strict";
    publisher.init();
}(window.jQuery);
