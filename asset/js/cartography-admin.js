/*
 * Cartography annotate
 */

$(document).ready( function() {

/**
 * Fetch images metadata of a resource.
 *
 * @todo Remove the sync request and use a callback.
 *
 * @param int resourceId
 * @param object data May contaiin the image type (type: "original"…).
 * @return array
 */
var fetchImages = function(resourceId, data) {
    var url = basePath + '/admin/cartography/' + resourceId + '/images';

    $.ajax({url: url, data: data, async: false})
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            images = data.images;
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the images.');
            alert(message);
        });
}

/**
 * Fetch default wms layers of a site.
 *
 * @todo Remove the sync request and use a callback.
 *
 * @param int resourceId
 * @param object data May contain the level of wms layers to fetch (upper or lower)
 * @return array
 */
var fetchWmsLayers = function(resourceId, data) {
    var url = basePath + '/admin/cartography/' + resourceId + '/wmsLayers';

    $.ajax({url: url, data: data, async: false})
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            wmsLayers = data.wmsLayers;
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the wms layers.');
            alert(message);
        });
}

/**
 * Fetch geometries for a resource.
 *
 * @todo Separate the fetch and the display.
 *
 * @param int resourceId
 * @param object data May contaiin the media id.
 */
var fetchGeometries = function(resourceId, data, drawnItems) {
    var url = basePath + '/admin/cartography/' + resourceId + '/geometries';

    $.get(url, data)
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            displayGeometries(data.geometries, drawnItems);
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
}

/**
 * Display geometries.
 */
var displayGeometries = function(geometries, drawnItems) {
    $.each(geometries, function(index, data) {
        var layer;
        var geojson = Terraformer.WKT.parse(data['wkt']);
        var options = data['options'] || {};
        options.annotationIdentifier = data['id'];

        // Prepare to set the content of the popup.
        if (layer && options.popupContent) {
            options.onEachFeature = function(feature, layer) {
                var popupContent = popupAnnotation(options);
                layer.bindPopup(popupContent);

                // To reserve the options from geoJson.
                layer.options = layer.options || {};
                // To prepare for style editor form-element initial value.
                layer.options = $.extend(options, layer.options);
            }
        }

        // Prepare the layer.
        if (geojson.type === 'Point' && typeof options.radius !== 'undefined') {
            // Warning: the coordinates are inversed on an image.
            layer = L.circle([geojson.coordinates[1], geojson.coordinates[0]], options);

            // Set the content of the popup.
            if (layer && options.popupContent) {
                var popupContent = popupAnnotation(options);
                layer.bindPopup(popupContent);
            }
        } else {
            layer = L.geoJson(geojson, options);
        }

        // Append the geometry to the map.
        addGeometry(layer, data['id'], drawnItems);

        layer.options.annotationIdentifier = data['id'];
    });
}

/**
 * Add a geometry to the map.
 *
 * @param layer
 * @param int identifier
 * @param drawnItems
 */
var addGeometry = function(layer, identifier, drawnItems) {
    // Don't save the geometry two times.
    if (identifier) {
        layer.options.annotationIdentifier = identifier;
        // drawnItems.addLayer(layer);
        addNonGroupLayers(layer, drawnItems);
        layer.options.annotationIdentifier = identifier;
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    // Options are saved only when updated: some people don't need styles so it
    // will be lighter in that case. Except for circle, that has the required
    // option radius.
    var options = typeof layer.getRadius === 'function' ? {'radius': layer.getRadius()} : {};

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        // Identifier is always empty when an annotation is created.
        id : identifier,
        resourceId: resourceId,
        // Media id is empty on locate.
        mediaId: currentMediaId(),
        wkt: wkt,
        options: options,
    };

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to save the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            identifier = data.result.id;
            layer.options.annotationIdentifier = identifier;
            drawnItems.addLayer(layer);
            console.log('Geometry added.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to save the geometry.');
            alert(message);
            // The deletion is automatic when not recorded.
        });
};

/**
 * Edit a geometry.
 *
 * @param layer
 */
var editGeometry = function(layer) {
    var identifier = layer.options.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        alert(Omeka.jsTranslate('Unable to save the edited geometry: no identifier.'));
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    // Options radius should be set here, because it is updated automatically.
    if (typeof layer.getRadius === 'function') {
        layer.options.radius = layer.getRadius();
    }

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        id: identifier,
        // TODO Remove the media id, since it cannot change (currently needed in controller).
        mediaId: currentMediaId(),
        wkt: wkt,
        options: layer.options
    };

    // Clean the post data (this should not be needed).
    buildParams(data);

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to edit the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            console.log('Geometry updated.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to update the geometry.');
            alert(message);
        });
}

/**
 * Delete a geometry.
 *
 * @param layer
 */
var deleteGeometry = function(layer) {
    var url = basePath + '/admin/cartography/delete-annotation';
    var identifier = layer.options.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        console.log('Unable to delete the geometry: no identifier.');
        return;
    }
    var data = {id: identifier};

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to delete the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            console.log('Geometry deleted.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to delete the geometry.');
            alert(message);
        });
}

/**
 * Create the popup content from the options of the geometry.
 *
 * @todo Use a template file to display the popup (common/annotate-cartography-popup.phtml).
 *
 * @param options
 */
var popupAnnotation = function(options) {
    var html = '';

    var content = options.popupContent || '';
    var oaLinking = options.oaLinking || [];
    var annotationIdentifier = options.annotationIdentifier || null;
    var url = '';

    if (content.length) {
        html += '<div class="annotation-body-rdf-value">' + content + '</div>';
    }
    if (oaLinking.length) {
        html += '<div class="annotation-body-oa-linking" >';
        // html += '<label>' + (oaLinking.length === 1 ? Omeka.jsTranslate('Related item') : Omeka.jsTranslate('Related items')) + '</label>';
        $.each(oaLinking, function(index, valueObj) {
            html += '<div class="value">'
                + '<p class="resource-oa-linking">'
                // TODO Add ellipsis to display the title and to display the resource icon.
                // + '<span class="o-title ' + valueObj['value_resource_name'] + '">';
                + '<span class="o-title ' + valueObj['value_resource_name'] + '-no">'
                + (typeof valueObj['thumbnail_url'] !== 'undefined' ? '<img src="' + valueObj['thumbnail_url'] + '">' : '')
                + '<a href="' + valueObj['url'] + '">'
                + (typeof valueObj['display_title'] === 'undefined' ? Omeka.jsTranslate('[Untitled]') : valueObj['display_title'])
                + '</a>'
                + '</span>'
                + '</p>'
                + '</div>';
        });
        html += '</div>';
    }
    html += '<div class="annotation-target-cartography-uncertainty"><i>Uncertainty:</i> ' + options['cartographyUncertainty'] + '</div>';

    html += '<div class="annotation-metadata">';
    if (annotationIdentifier) {
        url = basePath + '/admin/annotation/' + annotationIdentifier;
        html += '<div class="annotation-caption">'
            + '<a class="resource-link" href="' + url + '">'
            + '<span class="resource-name">[#' + annotationIdentifier + ']</span>'
            + '</a>'
            + '<ul class="actions"><li><span>'
            + '<a class="o-icon-external" href="' + url + '" target="_blank" title="' + Omeka.jsTranslate('Show annotation') + '" aria-label="' + Omeka.jsTranslate('Show annotation') + '"></a>'
            + '</span></li></ul>'
            + '</div>';
    }
    html += '<div class="annotation-owner">' + options['owner']['name'] + '</div>';
    html += '<div class="annotation-created">' + options['date'] + '</div>';
    html += '</div>';

    return html;
}

/**
 * Recursively remove the fonctions of an object.
 *
 * This is a hack to fix the edition of markers via leaflet.draw.
 *
 * @todo Remove this hack used to allow markers to be edited.
 */
function buildParams(obj, key) {
    key = key || '';
    obj = obj || {};
    for (var prop in obj) {
        var element = obj[prop];
        if (typeof element === 'array') {
            element.map(function (ele, idx) {
                buildParams(ele, idx);
            });
        } else if (typeof element === 'object') {
            // Recursive looping.
            buildParams(element, prop);
        } else if (typeof element === 'function') {
            // Remove the fonction.
            obj[prop] = '';
        }
    }
}

/**
 * Allows to create editable layer from existing geometries.
 *
 * @see https://gis.stackexchange.com/questions/203540/how-to-edit-an-existing-layer-using-leaflet/203773#203773
 * @todo Fix: the existing groups are not draggable (but the newly created were).
 *
 * @todo Remove these specific layers, and use drawnItems only.
 * Note: there is a layer for "describe" and another one for "locate".
 */
var addNonGroupLayers = function(sourceLayer, targetGroup) {
    if (!targetGroup) {
        return;
    }
    if (sourceLayer instanceof L.LayerGroup) {
        sourceLayer.eachLayer(function (layer) {
            addNonGroupLayers(layer, targetGroup);
        });
    } else {
        targetGroup.addLayer(sourceLayer);
    }
}

/**
 * Get marker identifier.
 *
 * @todo Fix this process, too hacky: the identifier should be simple to save and find.
 *
 * @param layer
 * @return int
 */
var getMarkerIdentifier = function(layer) {
    var identifier = layer.options.annotationIdentifier;
    if (identifier) {
        return identifier;
    }
    var parents = Object.values(layer._eventParents);
    return parents[parents.length - 1].options.annotationIdentifier;
}

/**
 * Get current section (determine if the user uses the leaflet map "describe" or "locate").
 *
 * @todo Get the current section via a better way (or remove it: only needed to get media id).
 *
 * @return string "describe" or "locate"
 */
var currentMapElement = function() {
    // Quick hack to get the current map (but in public, the two maps are not hidden and there is no fragment).
    var section = window.location.hash.substr(1);
    var mapElement = 'annotate-' + section;
    return mapElement;
}

/**
 * Get the media id of the current image overlay.
 *
 * There is no media id in "locate", since anything is georeferenced and related
 * to the item.
 *
 * @todo Get id of the current ImageOverlay via Leaflet methods, not jQuery.
 *
 * @return int|null
 */
var currentMediaId = function() {
    var mapElement = currentMapElement();
    if (mapElement === 'annotate-locate') {
        return null;
    }
    var mediaId = $('#' + mapElement).find('.leaflet-control-layers input[name="leaflet-base-layers"]:checked').next('span').text();
    mediaId = mediaId.length < 1 ? 1 : mediaId.substring(mediaId.lastIndexOf('#') + 1).trim();
    mediaId = images[mediaId - 1].id;
    return mediaId;
}

/**
 * Fit bounds from the current image layer (describe) or from the geometries (locate).
 *
 * @todo Fit map bounds.
 */
var setView = function() {
//    if (defaultBounds) {
//       map.fitBounds(defaultBounds);
//    } else {
//        var bounds = markers.getBounds();
//        if (bounds.isValid()) {
//            map.fitBounds(bounds);
//        } else {
//            map.setView([20, 0], 2)
//        }
//    }
};

/**
 * Initialize the data for the describe section.
 */
var initDescribe = function() {
    var section = 'describe';

    // TODO Convert the fetch of images into a callback.
    fetchImages(resourceId, {type: 'original'});
    if (!images.length) {
        $('#annotate-' + section).html(Omeka.jsTranslate('There is no image attached to this resource.'));
        return;
    }

    // Initialize the map and set default view.
    var map = L.map('annotate-' + section, {
        // TODO Compute the min/max zoom according to images?
        minZoom: -4,
        maxZoom: 8,
        zoom: 0,
        center: [0, 0],
        maxBoundsViscosity: 1,
        crs: L.CRS.Simple,
        pasteControl: true,
    });
    map.setView([0, 0], 0);
    var mapMoved = false;

    // Geometries are displayed and edited on the drawnItems layer.
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    // Add controls to the map.
    var baseMaps = {};
    var bounds;
    $.each(images, function(index, mainImage) {
        // Compute image edges as positive coordinates.
        // TODO Choose top left as 0.0 for still images?
        var southWest = L.latLng(0, 0);
        var northEast = L.latLng(mainImage.size[1], mainImage.size[0]);
        var bounds = L.latLngBounds(southWest, northEast);
        var image = L.imageOverlay(mainImage.url, bounds);
        if (index === 0) {
            bounds = image.getBounds();
            image.addTo(map);
            map.panTo([bounds.getNorthEast().lat / 2, bounds.getNorthEast().lng / 2]);
            // FIXME Fit bounds first image overlay.
            // map.fitBounds(bounds);
            fetchGeometries(resourceId, {mediaId: mainImage.id}, drawnItems);
        }
        baseMaps[Omeka.jsTranslate('Image #') + (index + 1)] = image;
    });
    if (Object.keys(baseMaps).length > 1) {
        var layerControl = L.control.layers(baseMaps);
        map.addControl(new L.Control.Layers(baseMaps));
    }

    var drawControl = new L.Control.Draw({
        draw: {
            polyline: true,
            polygon: true,
            rectangle: true,
            circle: true,
            marker: true,
            circlemarker: false,
        },
        edit: {
            featureGroup: drawnItems,
            remove: true,
        }
    });
    // Don't display the button "clear all".
    L.EditToolbar.Delete.include({
        removeAllLayers: false,
    });
    map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
    map.addControl(drawControl);

    /* Style Editor (https://github.com/dwilhelm89/Leaflet.StyleEditor) */
    // Initialize the StyleEditor
    var styleEditor = L.control.styleEditor({
        // position: 'topleft',
        // colorRamp: ['#1abc9c', '#2ecc71', '#3498db'],
        // markers: ['circle-stroked', 'circle', 'square-stroked', 'square'],
        strings: {
            save: Omeka.jsTranslate('Save'),
            saveTitle: Omeka.jsTranslate('Save Styling'),
            cancel: Omeka.jsTranslate('Cancel'),
            cancelTitle: Omeka.jsTranslate('Cancel Styling'),
            tooltip: Omeka.jsTranslate('Click on the element you want to style'),
            tooltipNext: Omeka.jsTranslate('Choose another element you want to style'),
        },
        useGrouping: false,
    });
    map.addControl(styleEditor);

    setView();

    manageGeometries(map, section, drawnItems);
}

/**
 * Initialize the data for the locate section.
 */
var initLocate = function() {
    var section = 'locate';

    // TODO Convert the fetch of wms layers into a callback.
    fetchWmsLayers(resourceId, {upper: 1, lower: 1});

    // Initialize the map and set default view.
    var map = L.map('annotate-' + section, {
        pasteControl: true,
    });
    map.setView([20, 0], 2);
    var mapMoved = false;

    // TODO Create automatically the bounds from geometries.
    var defaultBounds = null;
    // defaultBounds = [southWest, northEast];

    // Add layers and controls to the map.
    if (typeof baseMaps === 'undefined') {
        let baseMaps = {};
    }
    if (typeof baseMaps !== 'object' || $.isEmptyObject(baseMaps)) {
        baseMaps = {
            'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
            'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
            'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
            'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief'),
        };
    }

    if (!wmsLayers.length) {
        var layerControl = L.control.layers(baseMaps);
        map.addControl(new L.Control.Layers(baseMaps));
    } else {
        // Adapted from mapping-block.js (module Mapping).
        var noOverlayLayer = new L.GridLayer();
        var groupedOverlays = {
            'Overlays': {
                // 'No overlay': noOverlayLayer,
            },
        };

        // Set and prepare opacity control, if there is an overlay layer.
        var openWmsLayer, openWmsLabel;
        var opacityControl;
        var handleOpacityControl = function(overlay, label) {
            if (opacityControl) {
                // Only one control at a time.
                map.removeControl(opacityControl);
                opacityControl = null;
            }
            if (overlay !== noOverlayLayer) {
                // The "No overlay" overlay gets no control.
                opacityControl =  new L.Control.Opacity(overlay, label);
                map.addControl(opacityControl);
            }
        };

        // Add grouped WMS overlay layers.
        map.addLayer(noOverlayLayer);
        $.each(wmsLayers, function(index, data) {
            var wmsLabel = data.label.length ? data.label : (Omeka.jsTranslate('Layer') + ' ' + (index + 1));
            // Leaflet requires the layers and the styles separated.
            // Require a recent browser (@url https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams#Browser_compatibility#Browser_compatibility).
            // TODO Add a check and pure js to bypass missing URL interface.
            var url =  new URL(data.url);
            var searchParams = url.searchParams;
            var wmsLayers = '';
            wmsLayers = searchParams.get('LAYERS') || searchParams.get('Layers') || searchParams.get('layers') || wmsLayers;
            searchParams.delete('LAYERS'); searchParams.delete('Layers'); searchParams.delete('layers');
            var wmsStyles = '';
            wmsStyles = searchParams.get('STYLES') || searchParams.get('Styles') || searchParams.get('styles') || wmsStyles;
            searchParams.delete('STYLES'); searchParams.delete('Styles'); searchParams.delete('styles');
            url.search = searchParams;
            var wmsUrl = url.toString();
            if (wmsUrl.indexOf('?') === -1) {
                wmsUrl += '?';
            }
            wmsLayer = L.tileLayer.wms(wmsUrl, {
                layers: wmsLayers,
                styles: wmsStyles,
                format: 'image/png',
                transparent: true,
            });
            // Open the first wms overlay by default.
            if (index === 0) {
                openWmsLayer = wmsLayer;
                openWmsLabel = wmsLabel;
            }
            groupedOverlays['Overlays'][wmsLabel] = wmsLayer;
        });
        L.control.groupedLayers(baseMaps, groupedOverlays, {
            // exclusiveGroups: ['Overlays'],
        }).addTo(map);
    }
    map.addLayer(baseMaps['Satellite']);

    // Geometries are displayed and edited on the drawnItems layer.
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    //TODO Fix and add the fit bound control with geometries, not markers.
    fetchGeometries(resourceId, {mediaId: 0}, drawnItems);

    var drawControl = new L.Control.Draw({
        draw: {
            polyline: true,
            polygon: true,
            rectangle: true,
            circle: true,
            marker: true,
            circlemarker: false,
        },
        edit: {
            featureGroup: drawnItems,
            remove: true,
        }
    });
    // Don't display the button "clear all".
    L.EditToolbar.Delete.include({
        removeAllLayers: false,
    });
    var geoSearchControl = new window.GeoSearch.GeoSearchControl({
        provider: new window.GeoSearch.OpenStreetMapProvider,
        showMarker: false,
        retainZoomLevel: true,
    });
    map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
    map.addControl(drawControl);
    map.addControl(geoSearchControl);
    map.addControl(new L.control.scale({'position':'bottomleft','metric':true,'imperial':false}));

    /* Style Editor (https://github.com/dwilhelm89/Leaflet.StyleEditor) */
    // Initialize the StyleEditor
    var styleEditor = L.control.styleEditor({
        // position: 'topleft',
        // colorRamp: ['#1abc9c', '#2ecc71', '#3498db'],
        // markers: ['circle-stroked', 'circle', 'square-stroked', 'square'],
        strings: {
            save: Omeka.jsTranslate('Save'),
            saveTitle: Omeka.jsTranslate('Save Styling'),
            cancel: Omeka.jsTranslate('Cancel'),
            cancelTitle: Omeka.jsTranslate('Cancel Styling'),
            tooltip: Omeka.jsTranslate('Click on the element you want to style'),
            tooltipNext: Omeka.jsTranslate('Choose another element you want to style'),
        },
        useGrouping: false,
    });
    map.addControl(styleEditor);

    // Append the opacity control at the end of the toolbar for better ux.
    if (typeof openWmsLayer !== 'undefined' && openWmsLayer) {
        map.removeLayer(noOverlayLayer);
        map.addLayer(openWmsLayer);
        handleOpacityControl(openWmsLayer, openWmsLabel);

        // Handle the overlay opacity control.
        map.on('overlayadd', function(e) {
            handleOpacityControl(e.layer, e.name);
        });
    }

    setView();

    manageGeometries(map, section, drawnItems);
}

/* Manage geometries. */

function manageGeometries(map, section, drawnItems) {
    // Handle adding new geometries.
    map.on(L.Draw.Event.CREATED, function (element) {
        addGeometry(element.layer, null, drawnItems);
    });

    // // Handle editing geometries (when the edit button "save" is clicked).
    // map.on(L.Draw.Event.EDITED, function(element) {
    //     // TODO Check if options changed to avoid to save default ones.
    //     // FIXME It doesn't work when a marker is moved or style edited.
    //     element.layers.eachLayer(function(layer) {
    //         editGeometry(layer);
    //     });
    // });
    handleDrawEditSave();
    // Do the save work after the edit stop, keeping previous style.
    function handleDrawEditSave() {
        var editedLayers = {};

        catchEditEvents();

        // Catch the events.
        function catchEditEvents() {
            map.on(L.Draw.Event.EDITED, function(element) {
                editedLayers = element.layers;
            });
            map.on(L.Draw.Event.EDITSTOP, function(data) {
                saveLayerAtDrawStop();
            });
        }

        // Save it when edit stop.
        function saveLayerAtDrawStop() {
            if (editedLayers && editedLayers instanceof  L.LayerGroup) {
                editedLayers.eachLayer(function(layer) {
                    editGeometry(layer);
                });
            }
        }
    }

    // Handle deleting geometries (when the delete button "save" is clicked).
    map.on(L.Draw.Event.DELETED, function(element) {
        // TODO Don't delete geometry if issue on server.
        element.layers.eachLayer(function(layer) {
            deleteGeometry(layer);
        });
    });

    // Handle styling of a geometry.
    // Handle styling of a geometry in real time.
    // map.on('styleeditor:changed', function(element){
    //     editGeometry(element);
    // });
    // Use a final save button instead of saving in real time.
    handleStyleEditSave();
    function handleStyleEditSave() {
        var styleIsChanged = false;
        catchEditEvents();

        // Catch the events.
        function catchEditEvents() {
            map.on('styleeditor:changed', function(element) {
                if (element) {
                    styleIsChanged = true;
                }
            });

            map.on('styleeditor:editSave', function(element) {
                doSave(element);
            });
        }

        // Process the save.
        function doSave(element) {
            if (styleIsChanged === true && element ) {
                editGeometry(element);
                styleIsChanged = false;
            }
        }
    }

    // Handle paste wkt/geojson.
    map.on('paste:layer-created', function(element){
        addGeometry(element.layer, null, drawnItems);
    });

    map.on('paste:layer-created', function(element) {
        map.addLayer(element.layer);
    });

    // Handle the image change (only for describe).
    if (section === 'describe') {
        map.on('baselayerchange', function(element){
            // TODO Keep the layers in a invisible feature group layer by image? Check memory size.
            drawnItems.clearLayers();
            fetchGeometries(resourceId, {mediaId: currentMediaId()}, drawnItems);
        });
    }

    /* Various methods. */

    // Manage the relations side bar.
    // TODO Move specific code from StyleEditor here and use events.

    /**
     * Store the current annotation identifier for easier processing after resource selection.
     *
     * @todo To be removed: get the current annotation from leaflet (draw edit or style editor).
     */
    map.on('styleeditor:editing', function(element){
        // The annotation identifier is enough, but currently, the full layer/marker/geometry is saved.
        currentAnnotation = element;
    });
    /**
     * Reset the current annotation.
     *
     * @todo To be removed when the current annotation will be get from leaflet.
     */
    map.on('styleeditor:hidden', function(element){
        // TODO Revert change too when it is not in real time.
        currentAnnotation = null;
    });

    /**
     * Add a new linked resource from the sidebar for the style editor.
     *
     * @see application/asset/js/resource-form.js
     * type: "resource"; value: empty; valueObj: data of one selected item; nameprefix: empty.
     */
    $(document).on('o:prepare-value', function(e, type, value, valueObj, namePrefix) {
        if (!valueObj || typeof valueObj['value_resource_id'] === 'undefined') {
            return;
        }
        // Check if the current section is open.
        if ($('.section.active').prop('id') !== section || $('#' + section + ' .leaflet-styleeditor.editor-enabled').length !== 1) {
            return;
        }
        var identifier = currentAnnotation.options.annotationIdentifier || null;
        if (!identifier) {
            alert(Omeka.jsTranslate('Unable to find the geometry.'));
            return;
        }

        // Check if the selected resource is already linked.
        var url = basePath + '/admin/cartography/' + resourceId + '/geometries';
        var partIdentifier = currentMediaId();
        var data = {
            mediaId: partIdentifier === null ? '0' : (partIdentifier || '-1'),
            annotationId: identifier,
        }

        $.get(url, data)
            .done(function(data) {
                if (data.status === 'error') {
                    alert(data.message);
                    return;
                }

                if (typeof data.geometries[identifier] !== 'undefined') {
                    var oaLinking = data.geometries[identifier].options.oaLinking || [];
                    var arrayLength = oaLinking.length;
                    for (var i = 0; i < arrayLength; i++) {
                        if (oaLinking[i]['value_resource_id'] === valueObj['value_resource_id']) {
                            alert(Omeka.jsTranslate('The resource is already linked to the current annotation.'));
                            return;
                        }
                    }
                }

                var resourceDataTypes = [
                    'resource',
                    'resource:item',
                    'resource:itemset',
                    'resource:media',
                ];
                if (!valueObj || resourceDataTypes.indexOf(type) === -1) {
                    return;
                }

                addLinkedResource(identifier, valueObj);
                appendLinkedResource(valueObj);
            })
            .fail(function(jqxhr) {
                var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                    ? JSON.parse(jqxhr.responseText).message
                    : Omeka.jsTranslate('Unable to fetch the geometries.');
                alert(message);
            });
    });
    var addLinkedResource = function(identifier, valueObj) {
        if (typeof currentAnnotation.options.oaLinking === 'undefined') {
            currentAnnotation.options.oaLinking = [];
        }
        currentAnnotation.options.oaLinking.push(valueObj);
        // Real time saving deferred.
        // editGeometry(currentAnnotation);
    };
    var appendLinkedResource = function(valueObj) {
        // Prepare the markup for the resource data types.
        var html = '<div class="value selecting-resource">'
            + '<p class="selected-resource">'
            // TODO Add ellipsis to display the title and to display the resource icon.
            // + '<span class="o-title ' + valueObj['value_resource_name'] + '">';
            + '<span class="o-title ' + valueObj['value_resource_name'] + '-no">'
            + (typeof valueObj['thumbnail_url'] !== 'undefined' ? '<img src="' + valueObj['thumbnail_url'] + '">' : '')
            + '<a href="' + valueObj['url'] + '">'
            + (typeof valueObj['display_title'] === 'undefined' ? Omeka.jsTranslate('[Untitled]') : valueObj['display_title'])
            + '</a>'
            + '</span>'
            + '</p>'
            + '<ul class="actions">'
            + '<li><a class="o-icon-delete remove-value" title="' + Omeka.jsTranslate('Remove value') + '" href="#" aria-label="' + Omeka.jsTranslate('Remove value') + '" data-value-resource-id="' + valueObj['value_resource_id'] + '"></a></li>'
            + '</ul>'
            + '</div>';
        var oaLinkingDiv = $('.leaflet-styleeditor-oalinking.value.selecting-resource:visible');
        oaLinkingDiv.append(html);
    };

    /**
     * Remove a linked resource (directly via jQuery).
     */
    $('#' + section).on('click', '.leaflet-styleeditor-interior .actions .remove-value', function (element) {
        if (!currentAnnotation || !currentAnnotation.options.oaLinking || currentAnnotation.options.oaLinking.length === 0) {
            return;
        }

        // Remove the linked resource from the list of linked resources.
        var oaLinking = currentAnnotation.options.oaLinking || [];
        var valueResourceId = $(this).data('value-resource-id');
        var exists = false;
        for (var i = 0; i < oaLinking.length; i++) {
            if (oaLinking[i]['value_resource_id'] == valueResourceId) {
                oaLinking.splice(i, 1);
                exists = true;
                break;
            }
        }
        if (!exists) {
            return;
        }

        currentAnnotation.options.oaLinking = oaLinking;
        // Real time deletion deferred.
        // FIXME Real time delteion should be deferred (add event inside StyleEditor).
        editGeometry(currentAnnotation);

        // Remove the element from the style editor.
        $(this).closest('.value.selecting-resource').remove();
    });

    // Switching sections changes map dimensions, so make the necessary adjustments.
    $('#' + section).one('o:section-opened', function(e) {
        map.invalidateSize();
        setView();
    });

    // Close the sidebar when switching sections to avoid possible issues between describe/locate.
    $('#' + section).on('o:section-closed', function(e) {
        var sidebar = $('#select-resource');
        Omeka.closeSidebar(sidebar);
    });
}

/*
 * Initialization
 */

// TODO Remove global/closure variables.
var images = [];
var wmsLayers = [];
// TODO Find the way to get the current annotation after the resource selection.
var currentAnnotation;

// Disable the core resource-form.js bind for the sidebar selector.
$(document).off('o:prepare-value');

if (cartographySections.indexOf('describe') > -1) {
    initDescribe();
}
if (cartographySections.indexOf('locate') > -1) {
    initLocate();
}

});
