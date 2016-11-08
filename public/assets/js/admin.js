/**
Author: vNative Dev Team
Email: info@vnative.com
File: Admin Controller
*/
(function (window, $) {
    "use strict";
    var Admin = (function () {
        function Admin() {
            this.url = "insight/organization";
        }

        Admin.prototype = {
            performance: function () {
                var $this = this;
                request.get({ url: $this.url, data: $('#indexrange').serialize()}, function(err, data) {
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
                                label: "Ad Impressions",
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
                    $.ChartJs.respChart($("#perfstat"),'Line',lineChart, {});
                    
                    //Country wise visitor
                    $.VectorMap.init({
                        country: data.total.meta.country
                    });

                    //Referer
                    $.each(data.total.meta.referer, function(i, val) {
                        $('#topreferer').append('<tr><td>'+ i +'</td><td>'+ val +'</td></tr>');
                    });

                    //device
                    var device = data.total.meta.device,
                        pieChart = {
                        labels: [
                            "Desktops",
                            "Tablets",
                            "Mobiles"
                        ],
                        datasets: [{
                            data: [device['desktop'], device['tablet'], device['mobile']],
                            backgroundColor: [
                                "#228bdf",
                                "#00b19d",
                                "#7266ba"
                            ],
                            hoverBackgroundColor: [
                                "#228bdf",
                                "#00b19d",
                                "#7266ba"
                            ],
                            hoverBorderColor: "#fff"
                        }]
                    };
                    $.ChartJs.respChart($("#devicestat"),'Pie',pieChart);

                    //OS
                    $.each(data.total.meta.os, function(i, val) {
                        $('#topos').append('<tr><td>'+ i +'</td><td>'+ val +'</td></tr>');
                    });
                });
            },
            index: function () {
                var $this = this;
                $('#indexrange').submit(function(e) {
                    e.preventDefault();
                    $('#indexrange button').addClass('disabled');
                    //removing divs to update graphs
                    $('#country').remove();
                    $('#c-stat').append('<canvas id="perfstat" height="300"></canvas>');
                    
                    $('#perfstat').remove();
                    $('#perf-stat').append('<div id="country" style="height: 300px"></div>');

                    $('#devicestat').remove();
                    $('#device-stat').append('<canvas id="devicestat" height="300"></canvas>');

                    $('#topreferer').html('');
                    $('#topos').html('');
                    $this.performance();
                    $('#indexrange button').removeClass('disabled');
                });
            },
            update: function () {
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
            },
            delete: function () {
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
            },
            addDomain: function () {
                $('#addDomain').on('click', function (e) {
                    var $el = $('#trackingDomain').parent();

                    var $newEl = $el.clone();
                    $newEl.find('input').attr('id', '');
                    $newEl.find('input').attr('value', '');
                    $newEl.insertAfter($el);
                });
            },
            addCategory: function () {
                $('#addCategory').on('click', function (e) {
                    var $el = $('#categoryBox').parent();

                    var $newEl = $el.clone();
                    $newEl.find('input').attr('id', '');
                    $newEl.find('input').attr('value', '');
                    $newEl.insertAfter($el);
                });
            },
            removeThis: function () {
                $(document.body).on("click", ".removeThis", function (e) {
                    e.preventDefault();

                    var self = $(this);
                    var input = self.parent().parent().find('input');
                    input.remove();
                    self.remove();
                });
            },
            notification: function () {
                $('#targetSelect').on('change', function (e) {
                    e.preventDefault();

                    
                    var el = $('#listUsers');
                    var type = $(this).val();
                    el.html(' ');
                    el.append('<option value="' + def._id + '">' + def.name + '</option>');

                    request.get({url: 'admin/notification'}, function (err, d) {
                        var opts = d[type];
                        opts.forEach(function (opt) {
                            el.append('<option value="' + opt._id + '">' + opt.name + '</option>');
                        });
                        el.selectpicker('refresh');
                    });
                });
            },
            createpayment: function () {
                $("#addmore").click(function (e) {
                    e.preventDefault();
                    var item = $("#item").html();
                    $("#addmform").prepend("<tr>" + item + "</tr>");
                });
            },
            campaignCreate: function (advertisers, currency) {
                $(".comm").click(function (e) {
                    e.preventDefault();
                    var btn = $(this).closest("div");
                    btn.remove();
                });
                $("input[type=radio]").click(function() {
                    if($('#vpublic').is(':checked'))
                        $('#perm').show();
                    else
                        $('#perm').hide();
                });

                $('#advertiserSelect').on('change', function (e) {
                    var id = $(this).val();
                    var advert = advertisers[id];
                    var campaign = advert.meta.campaign || {};

                    var model = campaign.model || "cpc";
                    var revenue = campaign.rate || 0.25;
                    $('.model').val(model);
                    $('.advertiserRate').val($.Components.convertTo(revenue, currency));
                });
            },
            campaignDetails: function (advertisers, link, reqData) {
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
                        $.each(d.advertisers, function (i, el) {
                            // check for pixel in meta
                            var meta = el._meta || {};
                            var status = meta.pixel, found = false;

                            if (status === "working" && el.__id == window.currentAdvert)
                                found = true;
                            else
                                resultEl.addClass('label-danger').removeClass('label-success').html('Pixel not added on website, Please add the pixel or contact account manager');

                            if (found)
                                resultEl.addClass('label-success').removeClass('label-danger').html('It is working!!');

                            if (found)
                                return false;
                        });
                    }
                    );
                });
            },
            contest: function () {
                $('#contestType').on('change', function () {
                    var current = $(this).val();
                    var opts = $(this).find('option');

                    // hide all the other types dialog boxes
                    $.each(opts, function (i, el) {
                        var selector = $('#' + $(el).val());
                        if (!selector.hasClass('hide')) {
                            selector.addClass('hide');
                        }
                    });
                    $('#' + current).removeClass('hide');
                });
            },
            init: function () {
                this.index(),
                this.delete(),
                this.update(),
                this.addDomain(),
                this.addCategory(),
                this.removeThis();
            }
        };
        return Admin;
    }());

    window.admin = new Admin();
}(window, jQuery));

//initializing main admin module
(function($) {
    "use strict";
    admin.init();
}(window.jQuery));