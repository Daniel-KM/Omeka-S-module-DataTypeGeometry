(function($) {

    // Position from top left, so always positive.
    const regexPosition = /^\s*(?<x>\d+)\s*,\s*(?<y>\d+)\s*$/;
    const regexCoordinates = /^\s*(?<x>[+-]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+))\s*,\s*(?<y>[+-]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+))$/;
    const regexLatitudeLongitude = /^\s*(?<latitude>[+-]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?))\s*,\s*(?<longitude>[+-]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))\s*$/;

    /**
     * Check user input geometry.
     *
     * @param object element
     * @param string datatype
     * @return bool
     */
    var checkGeometry = function(element, datatype) {
        const val = element.value.trim().toUpperCase();
        var primitive = null;
        var message = null;
        if (datatype === 'geography') {
            if (val.includes('MULTIPOINT') || val.includes('MULTILINE') || val.includes('MULTIPOLYGON')) {
                message = Omeka.jsTranslate('"multipoint", "multiline" and "multipolygon" are not supported for now. Use collection instead.');
            } else {
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
            }
        } else if (datatype === 'geometry') {
            if (val.includes('MULTIPOINT') || val.includes('MULTILINE') || val.includes('MULTIPOLYGON')) {
                message = Omeka.jsTranslate('"multipoint", "multiline" and "multipolygon" are not supported for now. Use collection instead.');
            } else {
                try {
                    primitive = Terraformer.WKT.parse(val);
                } catch (err) {
                    message = invalidMessage(element);
                }
            }
        } else {
            return false;
        }

        if (val === '' || primitive || !message) {
            element.setCustomValidity('');
            return true;
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
            return false;
        }
    }

    /**
     * Check user input coordinates.
     *
     * @param object element
     * @param string datatype
     * @return bool
     */
    var checkCoordinates = function(element, datatype) {
        const val = element.value.trim().toUpperCase();
        var check = false;
        var message = null;
        if (datatype === 'geography:coordinates') {
            check = val.match(regexLatitudeLongitude);
        } else if (datatype === 'geometry:coordinates') {
            check = val.match(regexCoordinates);
        } else if (datatype === 'geometry:position') {
            check = val.match(regexPosition);
        } else {
            return false;
       }
        if (check) {
            element.classList.remove('invalid');
            element.setCustomValidity('');
            $(element).parent().find('input[type=number]')
                .removeClass('invalid')
                .get(0).setCustomValidity('');
            return true;
        } else {
            $(element).val('');
            message = invalidMessage(element);
            element.classList.add('invalid');
            element.setCustomValidity(Omeka.jsTranslate(message));
            $(element).parent().find('input[type=number]')
                .addClass('invalid')
                .get(0).setCustomValidity(message);
            return false;
        }
    }

    var invalidMessage = function(element) {
        let invalidValue = $(element).parent().closest('.value').find('.invalid-value');
        return invalidValue.length ? invalidValue.data('customValidity') : Omeka.jsTranslate('Error in input.');
    }

    /**
     * Check user input lat or long.
     *
     * @param object element
     * @param string datatype
     */
    var latlongCheck = function(element, datatype) {
        var element2;
        var message;
        const val = element.value.trim();
        const elementRadius = $('input.query-geo-around-radius')[0];
        const radius = elementRadius.value.trim();
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

        const val2 = element2.value.trim();
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
        var message = '';
        const val = element.value.trim();
        const radius = val;
        const latitude = $('input.query-geo-around-latitude')[0].value.trim();
        const longitude = $('input.query-geo-around-longitude')[0].value.trim();
        const unit = $('input.query-geo-around-unit[name="geo[around][unit]"]:checked').val();
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
        } else if (val === '' || message === '') {
            element.setCustomValidity('');
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
        }
    }

    var geometryOrGeographyFieldset = function() {
        const mode = $('input[name="geo[mode]"]:checked').val();
        if (mode === 'geography') {
            $('[data-geo-mode=geometry]').closest('.field-geo').hide();
            $('[data-geo-mode=geography]').closest('.field-geo').show();
        } else {
            $('[data-geo-mode=geometry]').closest('.field-geo').show();
            $('[data-geo-mode=geography]').closest('.field-geo').hide();
        }
    }

    $(document).ready(function() {

        // Batch edit form.

        $('#geometry_manage_coordinates_markers, #geometry_convert_literal_to_coordinates, #geometry_from_property, #geometry_to_property').closest('.field')
            .wrapAll('<fieldset id="geometry" class="field-container">');
        $('#geometry')
            .prepend('<legend>' + Omeka.jsTranslate('Geographic coordinates') + '</legend>');

        // Resource form.

        $(document).on('keyup change', '.geography-coordinates', function(e) {
            var message = null;
            const div = $(this).closest('.value');
            const latitude = div.find('.geography-coordinates-latitude').val().trim();
            const longitude = div.find('.geography-coordinates-longitude').val().trim();
            const element = div.find('.value.to-require');
            const val = latitude + ',' + longitude;
            element.val(val);
            checkCoordinates(element[0], 'geography:coordinates');
        });

        $(document).on('keyup change', '.geometry-coordinates', function(e) {
            const div = $(this).closest('.value');
            const x = div.find('.geometry-coordinates-x').val().trim();
            const y = div.find('.geometry-coordinates-y').val().trim();
            const element = div.find('.value.to-require');
            const val = x + ',' + y;
            element.val(val);
            checkCoordinates(element[0], 'geometry:coordinates')
        });

        $(document).on('keyup change', '.geometry-position', function(e) {
            const div = $(this).closest('.value');
            const x = div.find('.geometry-position-x').val().trim();
            const y = div.find('.geometry-position-y').val().trim();
            const element = div.find('.value.to-require');
            const val = x + ',' + y;
            element.val(val);
            checkCoordinates(element[0], 'geometry:position');
        });

        $(document).on('keyup change', 'textarea.value.geography', function(e) {
            checkGeometry(this, 'geography');
        });

        $(document).on('keyup change', 'textarea.value.geometry', function(e) {
            checkGeometry(this, 'geometry');
        });

        // Search form.

        $(document).on('click', 'input[name="geo[mode]"]', function(e) {
            geometryOrGeographyFieldset();
        });

        $(document).on('keyup change', 'input.query-geo-around-latitude', function(e) {
            latlongCheck(this, 'latitude');
        });
        $(document).on('keyup change', 'input.query-geo-around-longitude', function(e) {
            latlongCheck(this, 'longitude');
        });
        $(document).on('keyup change', 'input.query-geo-around-radius', function(e) {
            radiusCheck(this);
        });
        $(document).on('click', 'input.query-geo-around-unit', function(e) {
            radiusCheck($('input.query-geo-around-radius')[0]);
        });

        $(document).on('keyup change', 'textarea.query-geo-zone, textarea.query-geo-area', function(e) {
            checkGeometry(this, $(this).hasClass('query-geo-area') ? 'geography' : 'geometry');
        });

        geometryOrGeographyFieldset();

    });

    $(document).on('o:prepare-value o:prepare-value-annotation', function(e, dataType, value, valueObj) {
        if (dataType === 'geography:coordinates' && valueObj) {
            // The value is an object that cannot be set by resource-fom.js.
            $(value).find('.value.to-require').val('');
            var coordinates = valueObj['@value'];
            if (!coordinates) {
                return;
            }
            if (typeof coordinates === 'object') {
                coordinates = coordinates.latitude + ',' + coordinates.longitude;
            }
            const coords = coordinates.match(regexLatitudeLongitude);
            if (!coords) {
                return;
            }
            $(value).find('.geography-coordinates-latitude').val(coords.groups.latitude);
            $(value).find('.geography-coordinates-longitude').val(coords.groups.longitude);
            $(value).find('.value.to-require').val(coords.groups.latitude + ',' + coords.groups.longitude);
        } else if (dataType === 'geometry:coordinates' && valueObj) {
            // The value is an object that cannot be set by resource-fom.js.
            $(value).find('.value.to-require').val('');
            var coordinates = valueObj['@value'];
            if (!coordinates) {
                return;
            }
            if (typeof coordinates === 'object') {
                coordinates = coordinates.x + ',' + coordinates.y;
            }
            const coords = coordinates.match(regexCoordinates);
            if (!coords) {
                return;
            }
            $(value).find('.geometry-coordinates-x').val(coords.groups.x);
            $(value).find('.geometry-coordinates-y').val(coords.groups.y);
            $(value).find('.value.to-require').val(coords.groups.x + ',' + coords.groups.y);
        } else if (dataType === 'geometry:position' && valueObj) {
            // The value is an object that cannot be set by resource-fom.js.
            $(value).find('.value.to-require').val('');
            var position = valueObj['@value'];
            if (!position) {
                return;
            }
            if (typeof position === 'object') {
                position = position.x + ',' + position.y;
            }
            const pos = position.match(regexPosition);
            if (!pos) {
                return;
            }
            $(value).find('.geometry-position-x').val(pos.groups.x);
            $(value).find('.geometry-position-y').val(pos.groups.y);
            $(value).find('.value.to-require').val(pos.groups.x + ',' + pos.groups.y);
        }
    });

})(jQuery);
