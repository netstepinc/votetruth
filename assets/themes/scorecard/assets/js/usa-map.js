document.addEventListener('DOMContentLoaded', function () {

    const labels = {
        'us-al': 'AL','us-ak': 'AK','us-az': 'AZ','us-ar': 'AR','us-ca': 'CA',
        'us-co': 'CO','us-ct': 'CT','us-de': 'DE','us-dc': 'DC','us-fl': 'FL',
        'us-ga': 'GA','us-hi': 'HI','us-id': 'ID','us-il': 'IL','us-in': 'IN',
        'us-ia': 'IA','us-ks': 'KS','us-ky': 'KY','us-la': 'LA','us-me': 'ME',
        'us-md': 'MD','us-ma': 'MA','us-mi': 'MI','us-mn': 'MN','us-ms': 'MS',
        'us-mo': 'MO','us-mt': 'MT','us-ne': 'NE','us-nv': 'NV','us-nh': 'NH',
        'us-nj': 'NJ','us-nm': 'NM','us-ny': 'NY','us-nc': 'NC','us-nd': 'ND',
        'us-oh': 'OH','us-ok': 'OK','us-or': 'OR','us-pa': 'PA','us-ri': 'RI',
        'us-sc': 'SC','us-sd': 'SD','us-tn': 'TN','us-tx': 'TX','us-ut': 'UT',
        'us-vt': 'VT','us-va': 'VA','us-wa': 'WA','us-wv': 'WV','us-wi': 'WI',
        'us-wy': 'WY'
    };

    // These will use the pill stack, not on-map labels
    const tinyStates = ['RI','CT','DE','DC','MD','NJ','MA','VT','NH'];

    const geo = Highcharts.maps['countries/us/us-all'];

    const data = geo.features.map(function (f) {
        const key = f.properties['hc-key'];
        return {
            'hc-key': key,
            name: f.properties.name,
            value: 1,
            label: labels[key] || ''
        };
    });

    Highcharts.mapChart('fi-us-map', {

        chart: {
            map: 'countries/us/us-all'
        },

        title: { text: '' },
        legend: { enabled: false },

        tooltip: {
            pointFormatter: function () {
                return '<b>' + this.name + '</b>';
            }
        },

        plotOptions: {
            series: {
                color: '#c41425',
                borderColor: '#ffffff',
                borderWidth: 1.5,
                states: {
                    hover: {
                        color: '#cccccc'
                    }
                },
                point: {
                    events: {
                        click: function () {
                            const abbr = (this.label || '').toLowerCase();
                            if (abbr) {
                                window.location.href = '/' + abbr + '/';
                            }
                        }
                    }
                }
            }
        },

        series: [{
            data: data,
            name: 'States',
            dataLabels: {
                enabled: true,
                formatter: function () {
                    const abbr = this.point.label;
                    if (!abbr) return '';
                    // Hide labels for tiny states (handled by pills)
                    if (tinyStates.indexOf(abbr) !== -1) return '';
                    return abbr;
                },
                style: {
                    fontSize: '11px',
                    fontWeight: 'bold',
                    color: '#ffffff',
                    textOutline: 'none'
                }
            }
        }]
    });

    // Wire tiny-state pills reliably
    const tinyButtons = document.querySelectorAll('#fi-us-map-tiny button[data-state]');

    tinyButtons.forEach(btn => {

        // Make absolutely sure the clickable layer wins
        btn.style.pointerEvents = "auto";

        // Preferred — modern click
        btn.addEventListener('click', function (e) {
            e.stopPropagation(); // prevent SVG from swallowing it
            const abbr = this.dataset.state.toLowerCase();
            window.location.href = '/' + abbr + '/';
        });

        // Fallback — inline onclick assignment
        btn.onclick = function (e) {
            e.stopPropagation();
            const abbr = this.dataset.state.toLowerCase();
            window.location.href = '/' + abbr + '/';
        };
    });
});