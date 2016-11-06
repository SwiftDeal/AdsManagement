(function (window, $) {
    "use strict";
    var Advertiser = (function () {
        function Advertiser() {
            
        }

        Advertiser.prototype = {
            insights: function () {
                request.get({ url: "insight/advertisers", data: $('#indexrange').serialize()}, function(err, data) {
                    var x = [], clicks = [], conversions = [], impressions = [], revenue = [];
                    $.each(data.stats, function(i, val) {
                        x.push(i);
                        clicks.push(val.clicks);
                        conversions.push(val.conversions);
                        impressions.push(val.impressions);
                        revenue.push($.Components.convertTo(val.revenue, data.user._currency));
                    });
                    var lineChart = {
                        labels: x,
                        datasets: [
                            $.ChartJs.lineChart({
                                label: "Clicks",
                                color: "#36A2EB",
                                data: clicks
                            }),
                            $.ChartJs.lineChart({
                                label: "Conversions",
                                color: "#FFCE56",
                                data: conversions
                            }),
                            $.ChartJs.lineChart({
                                label: "Impressions",
                                color: "#FAA43A",
                                data: impressions
                            }),
                            $.ChartJs.lineChart({
                                label: "Revenue",
                                color: "#4BC0C0",
                                data: revenue
                            })
                        ]
                    };
                    $.ChartJs.respChart($("#perfstats"),'Line',lineChart, {});
                });  
            },
            campaignDetails: function (adv, link, reqData) {
                $('#selectAdvert').on('change', function (e) {
                    e.preventDefault();

                    var el = $('#testingPixel');
                    window.currentAdvert = $(this).val();
                    $('#requestResult').html('');
                    el.text('<img src="' + link + '&advert_id=' + window.currentAdvert + '">');
                });

                $('#checkStatus').on('click', function (e) {
                    e.preventDefault();
                    request.get( {
                        url: 'advertiser/manage', data: reqData
                    }, function (err, d) {

                        var resultEl = $('#requestResult');
                        // check for pixel in meta
                        var meta = adv._meta || {};
                        var status = meta.pixel, found = false;

                        if (status === "working" && adv.__id == window.currentAdvert)
                            found = true;
                        else
                            resultEl.addClass('label-danger').removeClass('label-success').html('Pixel not added on website, Please add the pixel or contact account manager');

                        if (found)
                            resultEl.addClass('label-success').removeClass('label-danger').html('It is working!!');

                        if (found)
                            return false;
                    }
                    );
                });
            },
            index: function () {
                var $this = this;
                $('#indexrange').submit(function(e) {
                    e.preventDefault();
                    $('#perfstats').remove();
                    $('#graph-container').append('<canvas id="perfstats" height="300"></canvas>');
                    $('#indexrange button').addClass('disabled');
                    $this.insights();
                    $('#indexrange button').removeClass('disabled');
                });
            },

            init: function () {
                this.index();
            }
        };
        return Advertiser;
    }());

    window.advertiser = new Advertiser();
}(window, jQuery));

//initializing main advertiser module
(function($) {
    "use strict";
    advertiser.init();
}(window.jQuery));
