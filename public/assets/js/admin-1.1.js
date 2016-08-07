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
    
	$('.update').on('click', function (e) {
		e.preventDefault();
		var self = $(this);

		var link = self.attr('href') || self.data('href');
		request.post({ url: link, data: self.data('send') }, function (err, d) {
			if (err) {
				return bootbox.alert('Something went wrong!!');
			}

			bootbox.alert(d.message, function () {
				window.location.href = window.location.href;
			});
		});
	});

	$('.delete').on('click', function (e) {
        e.preventDefault();
        var self = $(this), message = "",
        	link = self.attr('href') || self.data('href');
        
        if (!link) return false;

        if (self.data('message')) {
            message += self.data('message');
        } else {
            message += 'Are you sure, you want to proceed with the action?!';
        }

        bootbox.confirm(message, function (ans) {
            if (!ans) return;

            request.delete({url: link}, function (err, data) {
                if (err) {
                    return bootbox.alert(err);
                }

                bootbox.alert(data.message, function () {
                    window.location.href = window.location.href;
                });
            });
        }); 
    });

    $('#addDomain').on('click', function (e) {
        var $el = $('#trackingDomain').parent();

        var $newEl = $el.clone();
        $newEl.find('input').attr('id', '');
        $newEl.find('input').attr('value', '');
        $newEl.insertAfter($el);
    });

    $('#addCategory').on('click', function (e) {
        var $el = $('#categoryBox').parent();

        var $newEl = $el.clone();
        $newEl.find('input').attr('id', '');
        $newEl.find('input').attr('value', '');
        $newEl.insertAfter($el);
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

    $(document.body).on("click", ".removeThis", function (e) {
        e.preventDefault();

        var self = $(this);
        var input = self.parent().parent().find('input');
        input.remove();
        self.remove();
    });
});

function admin_home() {
    //loading visitors map
    request.get({ url: "admin/performance", data: $('#range').serialize()}, function(err, data) {
        //stats loaded
        var arr = morrisData(data, 'date', 'click', 'payout', 'impression');
        $('#click').html(data.total_clicks);
        $('#payout').html(data.total_payouts);
        $('#impression').html(data.total_impressions);

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