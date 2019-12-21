/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */
// Define global variables
/* global Chart */
var timeLineChart, queryTypeChart, forwardDestinationChart;
var queryTypePieChart, forwardDestinationPieChart, clientsChart;

function padNumber(num) {
    return ("00" + num).substr(-2,2);
}

// Helper function needed for converting the Objects to Arrays

function objectToArray(p) {
    var keys = Object.keys(p);
    keys.sort(function(a, b) {
        return a - b;
    });

    var arr = [], idx = [];
    for (var i = 0; i < keys.length; i++)
    {
        arr.push(p[keys[i]]);
        idx.push(keys[i]);
    }
    return [idx,arr];
}

var lastTooltipTime = 0;

var customTooltips = function(tooltip) {
        // Tooltip Element
        var tooltipEl = document.getElementById("chartjs-tooltip");
        if (!tooltipEl)
        {
                tooltipEl = document.createElement("div");
                tooltipEl.id = "chartjs-tooltip";
                document.body.appendChild(tooltipEl);
                $(tooltipEl).html("<table></table>");
        }
        // Hide if no tooltip
        if (tooltip.opacity === 0)
        {
                tooltipEl.style.opacity = 0;
                return;
        }

        // Limit rendering to once every 50ms. This gives the DOM time to react,
        // and avoids "lag" caused by not giving the DOM time to reapply CSS.
        var now = Date.now();
        if(now - lastTooltipTime < 50)
        {
            return;
        }
        lastTooltipTime = now;

        // Set caret Position
        tooltipEl.classList.remove("above", "below", "no-transform");
        if (tooltip.yAlign)
        {
                tooltipEl.classList.add(tooltip.yAlign);
        } else {
                tooltipEl.classList.add("above");
        }
        function getBody(bodyItem) {
                return bodyItem.lines;
        }
        // Set Text
        if (tooltip.body)
        {
                var titleLines = tooltip.title || [];
                var bodyLines = tooltip.body.map(getBody);
                var innerHtml = "<table><thead>";
                titleLines.forEach(function(title) {
                        innerHtml += "<tr><th>" + title + "</th></tr>";
                });
                innerHtml += "</thead><tbody>";
                var printed = 0;
                bodyLines.forEach(function(body, i) {
                        var colors = tooltip.labelColors[i];
                        var style = "background:" + colors.backgroundColor;
                        style += "; border-color:" + colors.borderColor;
                        style += "; border-width: 2px";
                        var span = "<span class=\"chartjs-tooltip-key\" style=\"" + style + "\"></span>";
                        var num = body[0].split(": ");
                        // remove percent symbol from amount to allow numeric comparison
                        var number = num[1].replace(/%/i,"");
                        if(number > 0)
                        {
                            innerHtml += "<tr><td>" + span + body + "</td></tr>";
                            printed++;
                        }
                });
                if(printed < 1)
                {
                    innerHtml += "<tr><td>No activity recorded</td></tr>";
                }
                innerHtml += "</tbody></table>";
                $(tooltipEl).html(innerHtml);
        }

        // Display, position, and set styles for font
        var position = this._chart.canvas.getBoundingClientRect();
        var width = tooltip.caretX;
        // Prevent compression of the tooltip at the right edge of the screen
        if($(document).width() - tooltip.caretX < 400)
        {
                width = $(document).width()-400;
        }
        // Prevent tooltip disapearing behind the sidebar
        if(tooltip.caretX < 100)
        {
            width = 100;
        }
        tooltipEl.style.opacity = 1;
        tooltipEl.style.left = position.left + width + "px";
        tooltipEl.style.top = position.top + tooltip.caretY + window.scrollY + "px";
        tooltipEl.style.fontFamily = tooltip._bodyFontFamily;
        tooltipEl.style.fontSize = tooltip.bodyFontSize + "px";
        tooltipEl.style.fontStyle = tooltip._bodyFontStyle;
        tooltipEl.style.padding = tooltip.yPadding + "px " + tooltip.xPadding + "px";
};

// Functions to update data in page

var failures = 0;
function updateQueriesOverTime() {
    $.getJSON("api.php?overTimeData10mins", function(data) {

        if("FTLnotrunning" in data)
        {
            return;
        }

        // convert received objects to arrays
        data.domains_over_time = objectToArray(data.domains_over_time);
        data.ads_over_time = objectToArray(data.ads_over_time);
        // remove last data point since it not representative
        data.ads_over_time[0].splice(-1,1);
        // Remove possibly already existing data
        timeLineChart.data.labels = [];
        timeLineChart.data.datasets[0].data = [];
        timeLineChart.data.datasets[1].data = [];

        // Add data for each hour that is available
        for (var hour in data.ads_over_time[0])
        {
            if ({}.hasOwnProperty.call(data.ads_over_time[0], hour))
            {
                var d,h;
                h = parseInt(data.domains_over_time[0][hour]);
                if(parseInt(data.ads_over_time[0][0]) < 1200)
                {
                    // Fallback - old style
                    d = new Date().setHours(Math.floor(h / 6), 10 * (h % 6), 0, 0);
                }
                else
                {
                    // New style: Get Unix timestamps
                    d = new Date(1000*h);
                }

                timeLineChart.data.labels.push(d);
                timeLineChart.data.datasets[0].data.push(data.domains_over_time[1][hour]);
                timeLineChart.data.datasets[1].data.push(data.ads_over_time[1][hour]);
            }
        }
        $("#queries-over-time .overlay").hide();
        timeLineChart.update();
    }).done(function() {
        // Reload graph after 10 minutes
        failures = 0;
        setTimeout(updateQueriesOverTime, 600000);
    }).fail(function() {
        failures++;
        if(failures < 5)
        {
            // Try again after 1 minute only if this has not failed more
            // than five times in a row
            setTimeout(updateQueriesOverTime, 60000);
        }
    });
}

function updateQueryTypesPie() {
    $.getJSON("api.php?getQueryTypes", function(data) {

        if("FTLnotrunning" in data)
        {
            return;
        }

        var colors = [];
        // Get colors from AdminLTE
        $.each($.AdminLTE.options.colors, function(key, value) { colors.push(value); });
        var v = [], c = [], k = [], iter;
        // Collect values and colors, and labels
        if(data.hasOwnProperty("querytypes"))
        {
            iter = data.querytypes;
        }
        else
        {
            iter = data;
        }
        $.each(iter, function(key , value) {
            v.push(value);
            c.push(colors.shift());
            k.push(key);
        });
        // Build a single dataset with the data to be pushed
        var dd = {data: v, backgroundColor: c};
        // and push it at once
        queryTypePieChart.data.datasets[0] = dd;
        queryTypePieChart.data.labels = k;
        $("#query-types-pie .overlay").hide();
        queryTypePieChart.update();
        queryTypePieChart.chart.config.options.cutoutPercentage=50;
        queryTypePieChart.update();
        // Don't use rotation animation for further updates
        queryTypePieChart.options.animation.duration=0;
        // Generate legend in separate div
        $("#query-types-legend").html(queryTypePieChart.generateLegend());
        $("#query-types-legend > ul > li").on("mousedown", function(e){
            if(e.which === 2) // which == 2 is middle mouse button
            {
                $(this).toggleClass("strike");
                var index = $(this).index();
                var ci = e.view.queryTypePieChart;
                var meta = ci.data.datasets[0]._meta;
                for(let i in meta)
                {
                    if ({}.hasOwnProperty.call(meta, i))
                    {
                        var curr = meta[i].data[index];
                        curr.hidden = !curr.hidden;
                    }
                }
                ci.update();
            }
            else if(e.which === 1) // which == 1 is left mouse button
            {
                window.open("queries.php?querytype="+($(this).index()+1), "_self");
            }
        });
    }).done(function() {
        // Reload graph after minute
        setTimeout(updateQueryTypesPie, 60000);
    });
}

function updateClientsOverTime() {
    $.getJSON("api.php?overTimeDataClients&getClientNames", function(data) {

        if("FTLnotrunning" in data)
        {
            return;
        }

        // Remove graph if there are no results (e.g. privacy mode enabled)
        if(jQuery.isEmptyObject(data.over_time))
        {
            $("#clients").parent().remove();
            return;
        }

        // convert received objects to arrays
        data.over_time = objectToArray(data.over_time);

        // remove last data point since it not representative
        data.over_time[0].splice(-1,1);
        var timestamps = data.over_time[0];
        var plotdata  = data.over_time[1];
        var labels = [];
        var key, i, j;
        for (key in data.clients)
        {
            if (!{}.hasOwnProperty.call(data.clients, key))
            {
                continue;
            }
            var clientname;
            if(data.clients[key].name.length > 0)
            {
                clientname = data.clients[key].name;
            }
            else
            {
                clientname = data.clients[key].ip;
            }
            labels.push(clientname);
        }
        // Get colors from AdminLTE
        var colors = [];
        $.each($.AdminLTE.options.colors, function(key, value) { colors.push(value); });
        var v = [], c = [], k = [];

        // Remove possibly already existing data
        clientsChart.data.labels = [];
        clientsChart.data.datasets[0].data = [];
        for (i = 1; i < clientsChart.data.datasets.length; i++)
        {
            clientsChart.data.datasets[i].data = [];
        }

        // Collect values and colors, and labels
        clientsChart.data.datasets[0].backgroundColor = colors[0];
        clientsChart.data.datasets[0].pointRadius = 0;
        clientsChart.data.datasets[0].pointHitRadius = 5;
        clientsChart.data.datasets[0].pointHoverRadius = 5;
        clientsChart.data.datasets[0].label = labels[0];

        for (i = clientsChart.data.datasets.length; plotdata.length && i < plotdata[0].length; i++)
        {
            clientsChart.data.datasets.push({
                data: [],
                // If we ran out of colors, make a random one
                backgroundColor: i < colors.length
                    ? colors[i]
                    : "#" + parseInt("" + Math.random() * 0xffffff, 10).toString(16).padStart(6, "0"),
                pointRadius: 0,
                pointHitRadius: 5,
                pointHoverRadius: 5,
                label: labels[i],
                cubicInterpolationMode: "monotone"
            });
        }

        // Add data for each dataset that is available
        for (j in timestamps)
        {
            if (!{}.hasOwnProperty.call(timestamps, j))
            {
                continue;
            }
            for (key in plotdata[j])
            {
                if (!{}.hasOwnProperty.call(plotdata[j], key))
            {
                continue;
            }
                clientsChart.data.datasets[key].data.push(plotdata[j][key]);
            }

            var d = new Date(1000*parseInt(timestamps[j]));
            clientsChart.data.labels.push(d);
        }
        $("#clients .overlay").hide();
        clientsChart.update();
    }).done(function() {
        // Reload graph after 10 minutes
        failures = 0;
        setTimeout(updateClientsOverTime, 600000);
    }).fail(function() {
        failures++;
        if(failures < 5)
        {
            // Try again after 1 minute only if this has not failed more
            // than five times in a row
            setTimeout(updateClientsOverTime, 60000);
        }
    });
}


// Credit: http://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript/4835406#4835406
function escapeHtml(text) {
  var map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    "\"": "&quot;",
    "\'": "&#039;"
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}


var FTLoffline = false;
function updateSummaryData(runOnce) {
    var setTimer = function(timeInSeconds) {
        if (!runOnce)
        {
            setTimeout(updateSummaryData, timeInSeconds * 1000);
        }
    };
    $.getJSON("api.php?summary", function LoadSummaryData(data) {

        updateSessionTimer();

        if("FTLnotrunning" in data)
        {
            data["dns_queries_today"] = "Lost";
            data["ads_blocked_today"] = "connection";
            data["ads_percentage_today"] = "to";
            data["domains_being_blocked"] = "API";
            // Adjust text
            $("#temperature").html("<i class=\"fa fa-circle\" style=\"color:#FF0000\"></i> FTL offline");
            // Show spinner
            $("#queries-over-time .overlay").show();
            $("#forward-destinations-pie .overlay").show();
            $("#query-types-pie .overlay").show();
            $("#client-frequency .overlay").show();
            $("#domain-frequency .overlay").show();
            $("#ad-frequency .overlay").show();

            FTLoffline = true;
        }
        else if(FTLoffline)
        {
            // FTL was previously offline
            FTLoffline = false;
            $("#temperature").text(" ");
            updateQueriesOverTime();
            updateTopClientsChart();
            updateTopLists();
        }

        ["ads_blocked_today", "dns_queries_today", "ads_percentage_today", "unique_clients"].forEach(function(today) {
            var todayElement = $("span#" + today);
            todayElement.text() !== data[today] &&
            todayElement.text() !== data[today] + "%" &&
            $("span#" + today).addClass("glow");
        });

        if(data.hasOwnProperty("dns_queries_all_types"))
        {
            $("#total_queries").prop("title", "only A + AAAA queries (" + data["dns_queries_all_types"] + " in total)");
        }

        window.setTimeout(function() {
            ["ads_blocked_today", "dns_queries_today", "domains_being_blocked", "ads_percentage_today", "unique_clients"].forEach(function(header, idx) {
                var textData = (idx === 3 && data[header] !== "to") ? data[header] + "%" : data[header];
                $("span#" + header).text(textData);
            });
            $("span.glow").removeClass("glow");
        }, 500);

    }).done(function() {
        if(!FTLoffline)
        {
          setTimer(1);
        }
        else
        {
          setTimer(10);
        }
    }).fail(function() {
        setTimer(300);
    });
}

$(document).ready(function() {

    var isMobile = {
        Windows: function() {
            return /IEMobile/i.test(navigator.userAgent);
        },
        Android: function() {
            return /Android/i.test(navigator.userAgent);
        },
        BlackBerry: function() {
            return /BlackBerry/i.test(navigator.userAgent);
        },
        iOS: function() {
            return /iPhone|iPad|iPod/i.test(navigator.userAgent);
        },
        any: function() {
            return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Windows());
        }
    };

    // Pull in data via AJAX

    updateSummaryData();


    // Create / load "Forward Destinations over Time" only if authorized
    if(document.getElementById("forwardDestinationChart"))
    {
        ctx = document.getElementById("forwardDestinationChart").getContext("2d");
        forwardDestinationChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: [],
                datasets: [{ data: [] }]
            },
            options: {
                tooltips: {
                    enabled: true,
                    mode: "x-axis",
                    callbacks: {
                        title: function(tooltipItem, data) {
                            var label = tooltipItem[0].xLabel;
                            var time = label.match(/(\d?\d):?(\d?\d?)/);
                            var h = parseInt(time[1], 10);
                            var m = parseInt(time[2], 10) || 0;
                            var from = padNumber(h)+":"+padNumber(m-5)+":00";
                            var to = padNumber(h)+":"+padNumber(m+4)+":59";
                            return "Forward destinations from "+from+" to "+to;
                        },
                        label: function(tooltipItems, data) {
                            return data.datasets[tooltipItems.datasetIndex].label + ": " + (100.0*tooltipItems.yLabel).toFixed(1) + "%";
                        }
                    }
                },
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        type: "time",
                        time: {
                            unit: "hour",
                            displayFormats: {
                                hour: "HH:mm"
                            },
                            tooltipFormat: "HH:mm"
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            mix: 0.0,
                            max: 1.0,
                            beginAtZero: true,
                            callback: function(value, index, values) {
                                return Math.round(value*100) + " %";
                            }
                        },
                        stacked: true
                    }]
                },
                maintainAspectRatio: true
            }
        });

        // Pull in data via AJAX
        updateForwardedOverTime();
    }

    // Create / load "Top Clients over Time" only if authorized
    if(document.getElementById("clientsChart"))
    {
        ctx = document.getElementById("clientsChart").getContext("2d");
        clientsChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: [],
                datasets: [{ data: [] }]
            },
            options: {
                tooltips: {
                    enabled: false,
                    mode: "x-axis",
                    custom: customTooltips,
                    itemSort: function(a, b) {
                        return b.yLabel - a.yLabel;
                    },
                    callbacks: {
                        title: function(tooltipItem, data) {
                            var label = tooltipItem[0].xLabel;
                            var time = label.match(/(\d?\d):?(\d?\d?)/);
                            var h = parseInt(time[1], 10);
                            var m = parseInt(time[2], 10) || 0;
                            var from = padNumber(h)+":"+padNumber(m-5)+":00";
                            var to = padNumber(h)+":"+padNumber(m+4)+":59";
                            return "Client activity from "+from+" to "+to;
                        },
                        label: function(tooltipItems, data) {
                            return data.datasets[tooltipItems.datasetIndex].label + ": " + tooltipItems.yLabel;
                        }
                    }
                },
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        type: "time",
                        time: {
                            unit: "hour",
                            displayFormats: {
                                hour: "HH:mm"
                            },
                            tooltipFormat: "HH:mm"
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        },
                        stacked: true
                    }]
                },
                maintainAspectRatio: true
            }
        });

        // Pull in data via AJAX
        updateClientsOverTime();
    }

    // Create / load "Query Types over Time" only if authorized
    if(document.getElementById("queryTypeChart"))
    {
        ctx = document.getElementById("queryTypeChart").getContext("2d");
        queryTypeChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: [],
                datasets: [
                    {
                        label: "A: IPv4 queries",
                        pointRadius: 0,
                        pointHitRadius: 5,
                        pointHoverRadius: 5,
                        data: [],
                        cubicInterpolationMode: "monotone"
                    },
                    {
                        label: "AAAA: IPv6 queries",
                        pointRadius: 0,
                        pointHitRadius: 5,
                        pointHoverRadius: 5,
                        data: [],
                        cubicInterpolationMode: "monotone"
                    }
                ]
            },
            options: {
                tooltips: {
                    enabled: true,
                    mode: "x-axis",
                    callbacks: {
                        title: function(tooltipItem, data) {
                            var label = tooltipItem[0].xLabel;
                            var time = label.match(/(\d?\d):?(\d?\d?)/);
                            var h = parseInt(time[1], 10);
                            var m = parseInt(time[2], 10) || 0;
                            var from = padNumber(h)+":"+padNumber(m-5)+":00";
                            var to = padNumber(h)+":"+padNumber(m+4)+":59";
                            return "Query types from "+from+" to "+to;
                        },
                        label: function(tooltipItems, data) {
                            return data.datasets[tooltipItems.datasetIndex].label + ": " + (100.0*tooltipItems.yLabel).toFixed(1) + "%";
                        }
                    }
                },
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        type: "time",
                        time: {
                            unit: "hour",
                            displayFormats: {
                                hour: "HH:mm"
                            },
                            tooltipFormat: "HH:mm"
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            mix: 0.0,
                            max: 1.0,
                            beginAtZero: true,
                            callback: function(value, index, values) {
                                return Math.round(value*100) + " %";
                            }
                        },
                        stacked: true
                    }]
                },
                maintainAspectRatio: true
            }
        });

        // Pull in data via AJAX
        updateQueryTypesOverTime();
    }

    // Create / load "Top Domains" and "Top Advertisers" only if authorized
    if(document.getElementById("domain-frequency")
        && document.getElementById("ad-frequency"))
    {
        updateTopLists();
    }

    // Create / load "Top Clients" only if authorized
    if(document.getElementById("client-frequency"))
    {
        updateTopClientsChart();
    }

    $("#queryOverTimeChart").click(function(evt){
        var activePoints = timeLineChart.getElementAtEvent(evt);
        if(activePoints.length > 0)
        {
            //get the internal index of slice in pie chart
            var clickedElementindex = activePoints[0]["_index"];

            //get specific label by index
            var label = timeLineChart.data.labels[clickedElementindex];

            //get value by index
            var from = label/1000 - 300;
            var until = label/1000 + 300;
            window.location.href = "queries.php?from="+from+"&until="+until;
        }
        return false;
    });

    if(document.getElementById("queryTypePieChart"))
    {
        ctx = document.getElementById("queryTypePieChart").getContext("2d");
        queryTypePieChart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: [],
                datasets: [{ data: [] }]
            },
            options: {
                legend: {
                    display: false
                },
                tooltips: {
                    enabled: false,
                    custom: customTooltips,
                    callbacks: {
                        title: function(tooltipItem, data) {
                            return "Query types";
                        },
                        label: function(tooltipItems, data) {
                            var dataset = data.datasets[tooltipItems.datasetIndex];
                            var label = data.labels[tooltipItems.index];
                            return label + ": " + dataset.data[tooltipItems.index].toFixed(1) + "%";
                        }
                    }
                },
                animation: {
                    duration: 750
                },
                cutoutPercentage: 0
            }
        });

        // Pull in data via AJAX
        updateQueryTypesPie();
    }

    if(document.getElementById("forwardDestinationPieChart"))
    {
        ctx = document.getElementById("forwardDestinationPieChart").getContext("2d");
        forwardDestinationPieChart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: [],
                datasets: [{ data: [] }]
            },
            options: {
                legend: {
                    display: false
                },
                tooltips: {
                    enabled: false,
                    custom: customTooltips,
                    callbacks: {
                        title: function(tooltipItem, data) {
                            return "Forward destinations";
                        },
                        label: function(tooltipItems, data) {
                            var dataset = data.datasets[tooltipItems.datasetIndex];
                            var label = data.labels[tooltipItems.index];
                            return label + ": " + dataset.data[tooltipItems.index].toFixed(1) + "%";
                        }
                    }
                },
                animation: {
                    duration: 750
                },
                cutoutPercentage: 0
            }
        });

        // Pull in data via AJAX
        updateForwardDestinationsPie();
    }
});



//Settings


/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */
$(".confirm-vpnmode").confirm({
        text: "Are you sure you want to change VPN Mode?",
        title: "Confirmation required",
        confirm(button) {
                $("#changevpnmodeform").submit();
        },
        cancel(button) {
                // nothing to do
        },
	cancelButton: "No, go back",
        confirmButton: "Yes",
        cancelButton: "No, go back",
        post: true,
        confirmButtonClass: "btn-success",
        cancelButtonClass: "btn-danger",
        dialogClass: "modal-dialog modal-mg" // Bootstrap classes for mid-size modal
});

$(".confirm-dns").confirm({
        text: "Are you sure you want to change DNS-Crypt Mode?",
        title: "Confirmation required",
        confirm(button) {
                $("#changednsform").submit();
        },
        cancel(button) {
                // nothing to do
        },
        confirmButton: "Yes",
        cancelButton: "No, go back",
        post: true,
	confirmButtonClass: "btn-success",
        cancelButtonClass: "btn-danger",
        dialogClass: "modal-dialog modal-mg" // Bootstrap classes for mid-size modal
});

$(".confirm-pihole").confirm({
        text: "Are you sure you want to change Pihole Mode?",
        title: "Confirmation required",
        confirm(button) {
                $("#changepiholeform").submit();
        },
        cancel(button) {
                // nothing to do
        },
        confirmButton: "Yes",
        cancelButton: "No, go back",
        post: true,
	confirmButtonClass: "btn-success",
        cancelButtonClass: "btn-danger",
        dialogClass: "modal-dialog modal-mg"
});


$(".confirm-poweroff").confirm({
	text: "Are you sure you want to send a poweroff command to your Pi-Hole?",
	title: "Confirmation required",
	confirm(button) {
		$("#poweroffform").submit();
	},
	cancel(button) {
		// nothing to do
	},
	confirmButton: "Yes, poweroff",
	cancelButton: "No, go back",
	post: true,
	confirmButtonClass: "btn-danger",
	cancelButtonClass: "btn-success",
	dialogClass: "modal-dialog modal-mg" // Bootstrap classes for mid-size modal
});
$(".confirm-reboot").confirm({
	text: "Are you sure you want to send a reboot command?",
	title: "Confirmation required",
	confirm(button) {
		$("#rebootform").submit();
	},
	cancel(button) {
		// nothing to do
	},
	confirmButton: "Yes, reboot",
	cancelButton: "No, go back",
	post: true,
	confirmButtonClass: "btn-danger",
	cancelButtonClass: "btn-success",
	dialogClass: "modal-dialog modal-mg" // Bootstrap classes for mid-size modal
});


$(".api-token").confirm({
	text: "Make sure that nobody else can scan this code around you. They will have full access to the API without having to know the password. Note that the generation of the QR code will take some time.",
	title: "Confirmation required",
	confirm(button) {
		window.open("scripts/pi-hole/php/api_token.php");
	},
	cancel(button) {
		// nothing to do
	},
	confirmButton: "Yes, show API token",
	cancelButton: "No, go back",
	post: true,
	confirmButtonClass: "btn-danger",
	cancelButtonClass: "btn-success",
	dialogClass: "modal-dialog modal-mg"
});

$("#DHCPchk").click(function() {
	$("input.DHCPgroup").prop("disabled", !this.checked);
	$("#dhcpnotice").prop("hidden", !this.checked).addClass("lookatme");
});



function loadCacheInfo()
{
    $.getJSON("api.php?getCacheInfo", function(data) {
        if("FTLnotrunning" in data)
        {
            return;
        }

        // Fill table with obtained values
        $("#cache-size").text(parseInt(data["cacheinfo"]["cache-size"]));
        $("#cache-inserted").text(parseInt(data["cacheinfo"]["cache-inserted"]));

        // Highlight early cache removals when present
        var cachelivefreed = parseInt(data["cacheinfo"]["cache-live-freed"]);
        $("#cache-live-freed").text(cachelivefreed);
        if(cachelivefreed > 0)
        {
            $("#cache-live-freed").parent("tr").addClass("lookatme");
        }
        else
        {
            $("#cache-live-freed").parent("tr").removeClass("lookatme");
        }

        // Update cache info every 10 seconds
        setTimeout(loadCacheInfo, 10000);
    });
}

var leasetable, staticleasetable;
$(document).ready(function() {
	if(document.getElementById("DHCPLeasesTable"))
	{
		leasetable = $("#DHCPLeasesTable").DataTable({
			dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-6'i><'col-sm-6'f>>",
			"columnDefs": [ { "bSortable": false, "orderable": false, targets: -1} ],
			"paging": false,
			"scrollCollapse": true,
			"scrollY": "200px",
			"scrollX" : true
		});
	}
	if(document.getElementById("DHCPStaticLeasesTable"))
	{
		staticleasetable = $("#DHCPStaticLeasesTable").DataTable({
			dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-12'i>>",
			"columnDefs": [ { "bSortable": false, "orderable": false, targets: -1} ],
			"paging": false,
			"scrollCollapse": true,
			"scrollY": "200px",
			"scrollX" : true
		});
	}
    //call draw() on each table... they don't render properly with scrollX and scrollY set... ¯\_(ツ)_/¯
    $("a[data-toggle=\"tab\"]").on("shown.bs.tab", function (e) {
        leasetable.draw();
        staticleasetable.draw();
    });

    loadCacheInfo();

} );

// Handle hiding of alerts
$(function(){
    $("[data-hide]").on("click", function(){
        $(this).closest("." + $(this).attr("data-hide")).hide();
    });
});

// DHCP leases tooltips
$(document).ready(function(){
    $("[data-toggle=\"tooltip\"]").tooltip({"html": true, container : "body"});
});

// Handle list deletion
$("button[id^='adlist-btn-']").on("click", function (e) {
	var id = parseInt($(this).context.id.replace(/[^0-9\.]/g, ""), 10);
	e.preventDefault();

	var status = $("input[name=\"adlist-del-"+id+"\"]").is(":checked");
	var textType = status ? "none" : "line-through";

	// Check hidden delete box (or reset)
	$("input[name=\"adlist-del-"+id+"\"]").prop("checked", !status);
	// Untick and disable check box (or reset)
	$("input[name=\"adlist-enable-"+id+"\"]").prop("checked", status).prop("disabled", !status);
	// Strink through text (or reset)
	$("a[id=\"adlist-text-"+id+"\"]").css("text-decoration", textType);
	// Highlight that the button has to be clicked in order to make the change live
	$("button[id=\"blockinglistsaveupdate\"]").addClass("btn-danger").css("font-weight", "bold");

});

// Change "?tab=" parameter in URL for save and reload
$(".nav-tabs a").on("shown.bs.tab", function (e) {
    window.history.pushState("", "", "?tab=" + e.target.hash.substring(1));
    window.scrollTo(0, 0);
});

// Auto dismissal for info notifications
$(document).ready(function(){
    var alInfo = $("#alInfo");
    if(alInfo.length)
    {
        alInfo.delay(3000).fadeOut(2000, function() { alInfo.hide(); });
    }
});

$(document).ready(function(){
    var alError = $("#alError");
    if(alError.length)
    {
        alError.delay(10000).fadeOut(2000, function() { alError.hide(); });
    }
});


var $select1 = $( '#select1' ),
		$select2 = $( '#select2' ),
    $options = $select2.find( 'option' );

$select1.on( 'change', function() {
 $select2.html( $options.filter( '[value^="' + (this.value).split("|")[0] + '"]' ) );
} ).trigger( 'change' );

var $select3 = $( '#select3' ),
                $select4 = $( '#select4' ),
    $options1 = $select4.find( 'option' );

$select3.on( 'change', function() {
 $select4.html( $options1.filter( '[value^="' + (this.value).split("|")[0] + '"]' ) );
} ).trigger( 'change' );

