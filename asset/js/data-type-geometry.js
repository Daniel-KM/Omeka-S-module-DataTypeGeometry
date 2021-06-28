(function($) {

    const regexLatitudeLongitude = /^\s*(?<latitude>[+-]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?))\s*,\s*(?<longitude>[+-]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))\s*$/;

    /**
     * Check user input geometry.
     *
     * @param object element
     * @param string datatype
     * @return bool
     */
    var geometryCheck = function(element, datatype) {
        var primitive, message;
        var val = element.value.trim().toUpperCase();
        if (datatype === 'geometry:geography:coordinates') {
            if (val.match(regexLatitudeLongitude)) {
                primitive = true;
            } else {
                var invalidValue = $(element).closest('.input-body').find('.invalid-value');
                message = invalidValue.data('customValidity');
            }
        } else if (datatype === 'geometry:geography') {
            try {
                primitive = Terraformer.WKT.parse(val);
            } catch (err) {
                var error = true;
                // Check ewkt.
                if (/^srid\s*=\s*\d{1,5}\s*;\s*.+/i.test(val)) {
                    try {
                        primitive = Terraformer.WKT.parse(val.slice(val.indexOf(';')+ 1));
                        error = false;
                    } catch (err) {
                    }
                }
                if (error) {
                    message = 'Please enter a valid wkt for the geography.';
                }
            }
            // TODO Check all x and y, that should be below 180 and 90.
        } else if (datatype === 'geometry:geometry') {
            try {
                primitive = Terraformer.WKT.parse(val);
            } catch (err) {
                message = 'Please enter a valid wkt for the geometry.';
            }
        }

        if (val === '' || primitive) {
            element.setCustomValidity('');
            return true;
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
            return false;
        }
    }

    /**
     * Check user input lat or long.
     *
     * @param object element
     * @param string datatype
     */
    var latlongCheck = function(element, datatype) {
        var message;
        var val = element.value.trim();
        var element2;
        var elementRadius = $('input.query-geo-around-radius')[0];
        var radius = elementRadius.value.trim();
        if (datatype === 'latitude') {
            element2 = $('input.query-geo-around-longitude')[0];
            if (val < -90 || val > 90) {
                message = 'Please enter a valid latitude.';
            }
        } else if (datatype === 'longitude') {
            element2 = $('input.query-geo-around-latitude')[0];
            if (val < -180 || val > 180) {
                message = 'Please enter a valid longitude.';
            }
        }

        var val2 = element2.value.trim();

        if (val === '' && val2 === '') {
            element.setCustomValidity('');
            element2.setCustomValidity('');
            elementRadius.setCustomValidity('');
        } else if (val === '' && val2 !== '') {
            message = 'Please enter a latitude or longitude.';
            element.setCustomValidity(Omeka.jsTranslate(message));
        } else if (val !== '' && val2 === '') {
            message = 'Please enter a latitude or longitude.';
            element2.setCustomValidity(Omeka.jsTranslate(message));
        } else if (message) {
            element.setCustomValidity(Omeka.jsTranslate(message));
        } else {
            element.setCustomValidity('');
        }

        if ((val !== '' || val2 !== '') && radius === '') {
            message = 'Please enter a radius.';
            elementRadius.setCustomValidity(Omeka.jsTranslate(message));
        }
    }

    /**
     * Check user input radius, according to unit and required when a latitude
     * and longitude are set.
     *
     * @param object element
     */
    var radiusCheck = function(element) {
        var message;
        var val = element.value.trim();
        var radius = val;
        var latitude = $('input.query-geo-around-latitude')[0].value.trim();
        var longitude = $('input.query-geo-around-longitude')[0].value.trim();
        var unit = $('input.query-geo-around-unit[name="geo[around][unit]"]:checked').val();
        if (latitude.length || longitude.length) {
            if (radius <= 0) {
                message = 'Please enter a valid radius.';
            } else if (unit === 'm') {
                if (radius > 20038000) {
                    message = 'Please enter a valid radius in m.';
                }
            } else if (radius > 20038) {
                message = 'Please enter a valid radius in km.';
            }
        }

        if ((latitude.length || longitude.length) && val === '') {
            message = 'Please enter a radius.';
            element.setCustomValidity(Omeka.jsTranslate(message));
        } else if (val === '' || !message) {
            element.setCustomValidity('');
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
        }
    }

    $(document).ready(function() {

        // Resource form.

        $('.geography-coordinates').on('keyup, change', function(e) {
            var div = $(this).closest('.input-body');
            var latitude = div.find('.geography-coordinates-latitude').val().trim();
            var longitude = div.find('.geography-coordinates-longitude').val().trim();
            var element = div.find('.value.to-require');
            element.val(latitude + ',' + longitude);
            if (!geometryCheck(element[0], 'geometry:geography:coordinates')) {
                element.val('');
                // TODO Display error on the invalid part.
            }
        });

        $('textarea.value.geography').on('keyup, change', function(e) {
            geometryCheck(this, 'geometry:geography');
        });

        $('textarea.value.geometry').on('keyup, change', function(e) {
            geometryCheck(this, 'geometry:geometry');
        });

        // Search form.

        // The form uses geography only, because to query non-georeferenced
        // geometries has no meaning.
        $('textarea.query-geo-area').on('keyup', function(e) {
            geometryCheck(this, 'geometry:geography');
        });

        $('input.query-geo-around-latitude').on('keyup', function(e) {
            latlongCheck(this, 'latitude');
        });
        $('input.query-geo-around-longitude').on('keyup', function(e) {
            latlongCheck(this, 'longitude');
        });
        $('input.query-geo-around-radius').on('keyup', function(e) {
            radiusCheck(this);
        });
        $('input.query-geo-around-unit').on('click', function(e) {
            radiusCheck($('input.query-geo-around-radius')[0]);
        });

    });

    $(document).on('o:prepare-value', function(e, dataType, value, valueObj) {
        if (dataType === 'geometry:geography:coordinates' && valueObj) {
            // The value is an object that cannot be set by resource-fom.js.
            $(value).find('.value.to-require').val('');
            var coordinates = valueObj['@value'];
            if (!coordinates) {
                return;
            }
            if (typeof coordinates === 'object') {
                coordinates = coordinates.latitude + ',' + coordinates.longitude;
            }
            var coords = coordinates.match(regexLatitudeLongitude);
            if (!coords) {
                return;
            }
            $(value).find('.geography-coordinates-latitude').val(coords.groups.latitude);
            $(value).find('.geography-coordinates-longitude').val(coords.groups.longitude);
            $(value).find('.value.to-require').val(coords.groups.latitude + ',' + coords.groups.longitude);
        }
    });

})(jQuery);
