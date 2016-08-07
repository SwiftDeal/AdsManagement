$(function () {
    $('select[value]').each(function () {
        $(this).val(this.getAttribute("value"));
    });
});
$(document).ready(function() {
    //plugins initialized
    $('select').select2();
    $('.noselect2').select2('destroy');

    //initialize beautiful date picker
    $.fn.datepicker.defaults.format = "yyyy-mm-dd";
    $("input[type=date]").datepicker({
        format: 'yyyy-mm-dd'
    });

    // creates a new Resource by sending a POST request to the server
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

    // This is for adding 'selected="true"' on <option> of <select> tag
    var selectTags = $('.selectVal');
    if (selectTags.length > 0) {
        $.each(selectTags, function (i, el) {
            var $el = $(el);

            var optValue = $el.data('value');   // This will contain all the values of select tag
            optValue.forEach(function (val) {
                $el.find('option[value="' + val + '"]').attr('selected', true);
            });
        });
    }

    $('.createLink').on('click', function (e) {
        e.preventDefault();
        $('#campaignId').val($(this).data('adid'));
        $('#createLink').modal('show');
    });

    $('#createLinkForm').on('submit', function (e) {
        e.preventDefault();

        var self = $(this);
        request.post({ url: self.attr('action'), data: self.serialize() }, function (err, d) {
            $('#createLink').modal('hide');
            if (err) {
                return bootbox.alert('Internal Server Error');
            }

            bootbox.alert(d.message);
        });
    });
});

function pub_home() {
    //loading stats
    $('#click').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>');
    $('#payout').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>');

    request.get({ url: "publisher/performance", data: $('#range').serialize()}, function(err, data) {
        //stats loaded
        var arr = morrisData(data, 'date', 'click', 'payout', 'impression');
        $('#click').html(data.total_clicks);
        $('#payout').html(data.total_payouts);

        new Morris.Line({
            element: 'clickstats',
            data: arr,
            parseTime: false,
            xkey: 'date',
            ykeys: ['click', 'payout', 'impression'],
            labels: ['Click', 'Payout', 'Impression']
        });
    });
}

function morrisData(object, x, y1, y2, y3) {
    var result = [];
    for(var key in object.clicks) {
        var obj = {};
        obj[x] = key;
        obj[y3] = object.impressions[key];
        obj[y2] = object.payouts[key];
        obj[y1] = object.clicks[key];
        result.push(obj);
    }
    return result;
}