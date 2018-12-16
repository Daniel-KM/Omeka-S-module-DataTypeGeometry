(function($) {

    $(document).ready( function() {

        $('textarea.value.geometry, textarea.query-geo-wkt').on('keyup', function(e) {
            geometryCheck(this, 'geometry');
        });

        $('input.query-geo-latlong-lat').on('keyup', function(e) {
            latlongCheck(this, 'latitude');
        });
        $('input.query-geo-latlong-long').on('keyup', function(e) {
            latlongCheck(this, 'longitude');
        });
        $('input.query-geo-radius').on('keyup', function(e) {
            radiusCheck(this);
        });
        $('input.query-geo-unit').on('click', function(e) {
            radiusCheck($('input.query-geo-radius')[0]);
        });

        // Initial load.
        initGeometryDatatypes();

    });

    /**
     * Prepare the geometry datatypes for the main resource template.
     *
     * There is no event in resource-form.js and common/resource-form-templates.phtml,
     * except the generic view.add.after and view.edit.after, so the default
     * form is completed dynamically during the initial load.
     */
    var initGeometryDatatypes = function() {
        var defaultSelectorAndFields = $('.resource-values.field.template .add-values.default-selector, #properties .resource-values div.default-selector');
        appendGeometryDatatypes(defaultSelectorAndFields);
    }

    /**
     * Append the configured datatypes to a list of element.
     */
    var appendGeometryDatatypes = function(selector) {
        if (geometryDatatypes.indexOf('geometry') !== -1) {
            $('<a>', {'class': 'add-value button o-icon-geometry', 'href': '#', 'data-type': 'geometry'})
                .text(Omeka.jsTranslate('Geometry'))
                .appendTo(selector);
            selector.append("\n");
        }
    };

    /**
     * Check user input geometry.
     *
     * @param object element
     * @param string datatype
     */
    var geometryCheck = function(element, datatype) {
        var primitive, message;
        var val = element.value.trim();
        if (datatype === 'geometry') {
            try {
                primitive = Terraformer.WKT.parse(val);
            } catch (err) {
                message = 'Please enter a valid wkt for the geometry.';
            }
        }

        if (val === '' || primitive) {
            element.setCustomValidity('');
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
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
        var elementRadius = $('input.query-geo-radius')[0];
        var radius = elementRadius.value.trim();
        if (datatype === 'latitude') {
            element2 = $('input.query-geo-latlong-long')[0];
            if (val < -90 || val > 90) {
                message = 'Please enter a valid latitude.';
            }
        } else if (datatype === 'longitude') {
            element2 = $('input.query-geo-latlong-lat')[0];
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
        var latitude = $('input.query-geo-latlong-lat')[0].value.trim();
        var longitude = $('input.query-geo-latlong-long')[0].value.trim();
        var unit = $('input.query-geo-unit[name="geo[unit]"]:checked').val();
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

})(jQuery);
