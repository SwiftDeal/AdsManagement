(function (window, $) {
    "use strict";
    var Admin = (function () {
        function Admin() {
            
        }

        Admin.prototype = {
            performance: function () {
                request.get({ url: "insight/organization", data: $('#indexrange').serialize()}, function(err, data) {
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
                    $.ChartJs.respChart($("#perfstat"),'Line',lineChart, {});
                });  
            },
            index: function () {
                var $this = this;
                $('#indexrange').submit(function(e) {
                    e.preventDefault();
                    $('#perfstat').remove();
                    $('#perf-stat').append('<canvas id="perfstat" height="300"></canvas>');
                    $('#indexrange button').addClass('disabled');
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
            customization: function () {
                $("input[type=checkbox]").click(function() {
                    if ($("#affoption").is(":checked")) 
                        $(".affauto").removeClass('hide');
                    else 
                        $(".affauto").addClass('hide');
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