/**
 * Draw a geometry on a map instead of typing wkt into the field.
 *
 * Opened with Ctrl+Alt+M from inside a geometry or geography field, or with the
 * "Use geometry editor" button beside it. What is drawn is written back into that same
 * field as wkt, and the field's own validation runs on it as if it had been
 * typed: this editor is a way of writing into the input, not a second way of
 * storing a value. Omeka collects the value on submit by reading
 * data-value-key, so there is no form plumbing here at all.
 *
 * Leaflet is loaded on first use rather than with the page. The Mapping module
 * loads its own copy on the same item edit form, a second one would replace
 * window.L under it, and the order in which two modules append to headScript is
 * not something either can control. Loading late means we can see what is
 * already there and take it: Leaflet.draw 1.0.4 works against both the 1.9.3
 * Mapping ships and the 1.9.4 in this module's asset/vendor.
 */
(function ($) {
    'use strict';

    var config = window.DataTypeGeometryConfig || {};
    var settings = config.map || {};
    var assets = config.assets || {};

    var FIELDS = 'textarea.value.geometry, textarea.value.geography';

    var loading = null;
    var $sidebar = null;
    var map = null;
    var drawnFeatures = null;
    var $target = null;
    // Whether anything was drawn, edited or deleted since the editor opened. An
    // untouched editor must not write: reading a value in and writing it back
    // out is not a round trip for every geometry, and a cataloguer who opened
    // the map to look at a value should not have it rewritten underneath them.
    var dirty = false;

    function translate(string) {
        return window.Omeka && Omeka.jsTranslate ? Omeka.jsTranslate(string) : string;
    }

    function loadCss(url) {
        return new Promise(function (resolve, reject) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.onload = resolve;
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadJs(url) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Load Leaflet and its two plugins, but only the parts that are missing.
     *
     * Strictly ordered: a plugin registers itself on L, so Leaflet has to be
     * there first. The two plugins are independent of each other and load
     * together.
     */
    function ensureLeaflet() {
        if (loading) {
            return loading;
        }
        loading = Promise.resolve()
            .then(function () {
                if (window.L) {
                    return null;
                }
                return Promise.all([loadCss(assets.leafletCss), loadJs(assets.leafletJs)]);
            })
            .then(function () {
                var wanted = [];
                if (!(L.Control && L.Control.Draw)) {
                    wanted.push(loadCss(assets.leafletDrawCss), loadJs(assets.leafletDrawJs));
                }
                if (!(L.Control && L.Control.FullScreen)) {
                    wanted.push(loadCss(assets.fullscreenCss), loadJs(assets.fullscreenJs));
                }
                return wanted.length ? Promise.all(wanted) : null;
            });
        return loading;
    }

    function buildLayer(spec) {
        var options = spec.options || {};
        return spec.type === 'wms'
            ? L.tileLayer.wms(spec.url, options)
            : L.tileLayer(spec.url, options);
    }

    /** The same base layers and overlays the public map uses. */
    function addLayers(theMap) {
        var bases = {};
        var overlays = {};
        var first = true;

        Object.keys(config.baseLayers || {}).forEach(function (key) {
            var spec = config.baseLayers[key];
            var layer = buildLayer(spec);
            bases[spec.label || key] = layer;
            if (first) {
                layer.addTo(theMap);
                first = false;
            }
        });
        Object.keys(config.extraLayers || {}).forEach(function (key) {
            var spec = config.extraLayers[key];
            overlays[spec.label || key] = buildLayer(spec);
        });

        if (Object.keys(bases).length > 1 || Object.keys(overlays).length) {
            L.control.layers(bases, overlays).addTo(theMap);
        }
    }

    function buildSidebar() {
        if ($sidebar) {
            return $sidebar;
        }
        $sidebar = $(
            '<div id="geometry-map-sidebar" class="sidebar">'
            + '<a href="#" class="sidebar-close o-icon-close" title="' + translate('Cancel') + '"></a>'
            + '<div class="sidebar-content">'
            + '<h3 class="geometry-map-title"></h3>'
            + '<p class="geometry-map-notice error" hidden></p>'
            + '<div class="geometry-map-canvas"></div>'
            + '<div class="geometry-map-actions">'
            + '<button type="button" class="geometry-map-apply button"></button>'
            + '<button type="button" class="geometry-map-cancel button"></button>'
            + '</div>'
            + '</div></div>'
        );
        $sidebar.find('.geometry-map-title').text(translate('Geometry editor'));
        $sidebar.find('.geometry-map-apply').text(translate('Apply'));
        $sidebar.find('.geometry-map-cancel').text(translate('Cancel'));
        // Inside #content so that Omeka's own delegated handler closes it.
        $('#content').append($sidebar);
        return $sidebar;
    }

    function notice(message) {
        var $notice = $sidebar.find('.geometry-map-notice');
        if (message) {
            $notice.text(message).prop('hidden', false);
        } else {
            $notice.text('').prop('hidden', true);
        }
    }

    function buildMap() {
        if (map) {
            return map;
        }
        map = L.map($sidebar.find('.geometry-map-canvas')[0], {
            center: settings.center || [0, 0],
            zoom: settings.zoom || 16,
            maxZoom: settings.max_zoom || 21,
            // The sidebar is a narrow column, and drawing a large shape in it
            // means panning rather than seeing the shape. Same control as the
            // public map, registered by Control.FullScreen.umd.js.
            fullscreenControl: true
        });
        addLayers(map);

        drawnFeatures = new L.FeatureGroup();
        map.addLayer(drawnFeatures);

        map.addControl(new L.Control.Draw({
            draw: {
                marker: true,
                polyline: true,
                polygon: true,
                // A rectangle is a polygon, so it survives the round trip.
                rectangle: true,
                // Circles do not exist in wkt: they are a centre and a radius,
                // and nothing would carry the radius.
                circle: false,
                circlemarker: false
            },
            edit: {featureGroup: drawnFeatures}
        }));

        // One shape per field. The field holds a single value, and a second
        // shape would have to be written as MULTIPOINT, MULTILINESTRING or
        // MULTIPOLYGON, which this module's own validator rejects.
        map.on('draw:created', function (e) {
            drawnFeatures.clearLayers();
            drawnFeatures.addLayer(e.layer);
            dirty = true;
        });
        map.on('draw:edited', function () {
            dirty = true;
        });
        map.on('draw:deleted', function () {
            dirty = true;
        });

        return map;
    }

    /** Put the field's current value on the map, if it can be read. */
    function seed() {
        drawnFeatures.clearLayers();
        dirty = false;
        notice('');

        var wkt = $.trim($target.val());
        if (!wkt) {
            map.setView(settings.center || [0, 0], settings.zoom || 16);
            return;
        }

        var geometry;
        try {
            geometry = Terraformer.wktToGeoJSON(wkt);
        } catch (e) {
            notice(translate('The current value is not a geometry this editor can read. Drawing will replace it.'));
            return;
        }

        L.geoJSON(geometry, {style: settings.style || {}}).eachLayer(function (layer) {
            drawnFeatures.addLayer(layer);
        });

        if (!drawnFeatures.getLayers().length) {
            return;
        }
        // A collection comes in as several layers but can only go back out as
        // one, so say so rather than truncating it silently on apply.
        if (drawnFeatures.getLayers().length > 1) {
            notice(translate('The current value is not a geometry this editor can read. Drawing will replace it.'));
        }
        map.fitBounds(drawnFeatures.getBounds(), {maxZoom: settings.fit_max_zoom || 19});
    }

    function close() {
        Omeka.closeSidebar($sidebar);
    }

    function apply() {
        // Nothing was touched, so leave the value exactly as it was found.
        if (dirty) {
            var features = drawnFeatures.toGeoJSON().features;
            var wkt = features.length ? Terraformer.geojsonToWKT(features[0].geometry) : '';
            // The change is what re-runs the field's validation: setting a value
            // from script fires no event by itself.
            $target.val(wkt).trigger('change');
        }
        close();
    }

    function openEditor($field) {
        if (!$field || !$field.length) {
            return;
        }
        $target = $field.first();

        buildSidebar();
        ensureLeaflet()
            .then(function () {
                buildMap();
                Omeka.openSidebar($sidebar);
                // Leaflet measured a container that was still off-screen.
                map.invalidateSize();
                seed();
            })
            .catch(function (e) {
                console.error('DataTypeGeometry: could not load the map', e);
                window.alert(translate('The map could not be loaded.'));
            });
    }

    $(document).on('keydown', FIELDS, function (e) {
        if (!e.ctrlKey || !e.altKey || !e.key) {
            return;
        }
        if (e.key.toLowerCase() !== 'm') {
            return;
        }
        e.preventDefault();
        openEditor($(this));
    });

    $(document).on('click', '.geometry-map-open', function (e) {
        e.preventDefault();
        // Scoped to this value row, so the button edits its own field rather
        // than the first one on the page. Rows are cloned at runtime, which is
        // why every handler here is delegated.
        //
        // Anchored on .input-body, the wrapper Omeka puts around a data type's
        // own markup, rather than on .value: the field carries that class too
        // ("value to-require geometry"), so .value is ambiguous the moment
        // anything looks for it from inside the field rather than from the
        // button beside it.
        var $row = $(this).closest('.input-body');
        openEditor(($row.length ? $row : $(this).closest('.value')).find(FIELDS));
    });

    $(document).on('click', '.geometry-map-apply', apply);
    $(document).on('click', '.geometry-map-cancel', function () {
        close();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $sidebar && $sidebar.hasClass('active')) {
            close();
        }
    });
})(jQuery);
