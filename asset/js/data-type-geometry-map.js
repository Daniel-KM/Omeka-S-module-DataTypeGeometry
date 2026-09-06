/**
 * Draws the geometry described by each [data-geometry-map] element.
 *
 * Everything is read from the element's data attribute, so nothing here is tied
 * to a particular element id and any number of maps can share a page. The
 * previous implementation bound to a hardcoded id="map" and ran at parse time,
 * which meant a resource with two geometries drew only the first.
 */
(function () {
    'use strict';

    /** Build a Leaflet layer from a configured entry. */
    function buildLayer(spec) {
        var options = spec.options || {};
        return spec.type === 'wms'
            ? L.tileLayer.wms(spec.url, options)
            : L.tileLayer(spec.url, options);
    }

    /**
     * Add the base layers and overlays, and a switcher if there is a choice.
     *
     * The first base layer is the active one. Overlays all start off: they are
     * historical maps and aerial photography, and the point of the map is the
     * geometry, not what happens to be underneath it.
     */
    function addLayers(map, config) {
        var bases = {};
        var overlays = {};
        var first = true;

        Object.keys(config.baseLayers || {}).forEach(function (key) {
            var spec = config.baseLayers[key];
            var layer = buildLayer(spec);
            bases[spec.label || key] = layer;
            if (first) {
                layer.addTo(map);
                first = false;
            }
        });

        Object.keys(config.extraLayers || {}).forEach(function (key) {
            var spec = config.extraLayers[key];
            overlays[spec.label || key] = buildLayer(spec);
        });

        if (Object.keys(bases).length > 1 || Object.keys(overlays).length) {
            L.control.layers(bases, overlays).addTo(map);
        }
    }

    function drawMap(element) {
        var config;
        try {
            config = JSON.parse(element.getAttribute('data-geometry-map'));
        } catch (e) {
            console.error('DataTypeGeometry: unreadable map configuration', e);
            return;
        }

        var settings = config.map || {};
        var map = L.map(element, {
            center: settings.center || [0, 0],
            zoom: settings.zoom || 16,
            maxZoom: settings.max_zoom || 21,
            // Registered by Control.FullScreen.umd.js. Loading only the plugin's
            // stylesheet, as this module used to, styles a button that the map
            // never creates, and this option is then silently ignored.
            fullscreenControl: true
        });

        addLayers(map, config);

        if (!config.wkt) {
            return;
        }

        try {
            var layer = L.geoJSON({
                type: 'Feature',
                geometry: Terraformer.wktToGeoJSON(config.wkt)
            }, {
                style: settings.style || {}
            }).addTo(map);
            // Overrides the configured centre and zoom whenever there is a
            // geometry, which is why those are only a fallback.
            map.fitBounds(layer.getBounds(), {maxZoom: settings.fit_max_zoom || 19});
        } catch (e) {
            console.error('DataTypeGeometry: unreadable wkt "' + config.wkt + '"', e);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-geometry-map]').forEach(drawMap);
    });
})();
