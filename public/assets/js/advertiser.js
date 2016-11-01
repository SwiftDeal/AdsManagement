(function (window, $) {
    "use strict";
    var Advertiser = (function () {
        function Advertiser() {
            
        }

        Advertiser.prototype = {
            home: function () {
                request.get({ url: "insight/advertisers", data: $('#range').serialize()}, function(err, data) {
                    console.log(data);
                    //creating lineChart
                    var lineChart = {
                        labels: ["January", "February", "March", "April", "May", "June", "July", "August", "September"],
                        datasets: [
                            {
                                label: "Sales Analytics",
                                fill: false,
                                lineTension: 0.1,
                                backgroundColor: "#228bdf",
                                borderColor: "#228bdf",
                                borderCapStyle: 'butt',
                                borderDash: [],
                                borderDashOffset: 0.0,
                                borderJoinStyle: 'miter',
                                pointBorderColor: "#228bdf",
                                pointBackgroundColor: "#fff",
                                pointBorderWidth: 1,
                                pointHoverRadius: 5,
                                pointHoverBackgroundColor: "#228bdf",
                                pointHoverBorderColor: "#eef0f2",
                                pointHoverBorderWidth: 2,
                                pointRadius: 1,
                                pointHitRadius: 10,
                                data: [65, 59, 80, 81, 56, 55, 40, 35, 30]
                            }
                        ]
                    };

                    var lineOpts = {
                        scales: {
                            yAxes: [{
                                ticks: {
                                    max: 100,
                                    min: 20,
                                    stepSize: 10
                                }
                            }]
                        }
                    };

                    $.ChartJs.respChart($("#perfstats"),'Line',lineChart, lineOpts);
                });  
            },
            init: function () {
                this.home();
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
