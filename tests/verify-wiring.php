<?php

/**
 * Checks that the map rendering and the map editor are wired into Omeka
 * correctly, and that the parts which have no other test still hold.
 *
 * It boots a real Omeka installation rather than mocking one: the point is to
 * catch the wiring mistakes a unit test cannot see — a factory renamed out of
 * the config, an asset referenced but never shipped, a site override that
 * silently replaced the module's defaults instead of merging with them.
 *
 * The path below is the installation to boot. Change it, or override it:
 *
 *   OMEKA_PATH=/path/to/omeka-s php test/verify-wiring.php
 *
 * Exits 0 when everything holds, 1 otherwise.
 *
 * The constant is deliberately not called OMEKA_PATH: bootstrap.php defines
 * that one itself, unconditionally, and would warn about the redefinition.
 */
define('DATATYPEGEOMETRY_OMEKA_PATH', '/home/http/goudatijdmachine.nl/omeka-s-4.2.1');

$omekaPath = getenv('OMEKA_PATH') ?: DATATYPEGEOMETRY_OMEKA_PATH;
$modulePath = dirname(__DIR__);

if (!is_file($omekaPath . '/bootstrap.php')) {
    fwrite(STDERR, sprintf("No Omeka S installation at %s.\n", $omekaPath));
    fwrite(STDERR, "Set OMEKA_PATH, or fix the define() in this file.\n");
    exit(1);
}

// ------------------------------------------------------------------ harness

$failures = 0;
$checks = 0;

/**
 * @param string $description
 * @param mixed $result True to pass; a string explains the failure
 */
function check($description, $result)
{
    global $failures, $checks;
    ++$checks;
    if (true === $result) {
        printf("    ok    %s\n", $description);
        return;
    }
    ++$failures;
    printf("    FAIL  %s\n", $description);
    if (is_string($result) && '' !== $result) {
        printf("          %s\n", $result);
    }
}

/**
 * Run a check that may throw, so one broken assumption does not hide the rest.
 */
function checking($description, callable $test)
{
    try {
        check($description, $test());
    } catch (\Throwable $e) {
        check($description, sprintf('%s: %s', get_class($e), $e->getMessage()));
    }
}

function section($title)
{
    printf("\n%s\n", $title);
}

// -------------------------------------------------------------------- boot

require $omekaPath . '/bootstrap.php';

$application = Omeka\Mvc\Application::init(
    require $omekaPath . '/application/config/application.config.php'
);
$services = $application->getServiceManager();

printf("DataTypeGeometry map wiring check\n    omeka  %s\n    module %s\n", $omekaPath, $modulePath);

// ------------------------------------------------------------------ config

section('Configuration');

$config = $services->get('Config')['datatypegeometry'] ?? null;

checking('the datatypegeometry config is merged in', function () use ($config) {
    return is_array($config) ?: 'no "datatypegeometry" key in the merged config';
});

checking('the module\'s own settings survived the site override', function () use ($config) {
    // A local.config.php that replaced this key instead of merging into it
    // would take the module's existing settings with it.
    return isset($config['config']['datatypegeometry_locate_srid'])
        ?: 'the pre-existing "config" sub-key is gone';
});

checking('a base layer ships, so a stock install draws something', function () use ($config) {
    return !empty($config['base_layers']) ?: 'base_layers is empty';
});

checking('the OpenStreetMap default survived the site override', function () use ($config) {
    return isset($config['base_layers']['osm']['url'])
        ?: 'base_layers.osm is gone: an override replaced the catalogue rather than adding to it';
});

checking('the map defaults are complete', function () use ($config) {
    $missing = array_diff(
        ['height', 'center', 'zoom', 'max_zoom', 'fit_max_zoom', 'style'],
        array_keys($config['map'] ?? [])
    );
    return $missing ? 'missing: ' . implode(', ', $missing) : true;
});

checking('every layer entry has a label and a url', function () use ($config) {
    $problems = [];
    foreach (['base_layers', 'extra_layers'] as $catalogue) {
        foreach ($config[$catalogue] ?? [] as $id => $entry) {
            if (empty($entry['label']) || empty($entry['url'])) {
                $problems[] = sprintf('%s.%s', $catalogue, $id);
            }
        }
    }
    return $problems ? implode(', ', $problems) . ' incomplete' : true;
});

checking('every layer type is one Leaflet can build', function () use ($config) {
    $problems = [];
    foreach (['base_layers', 'extra_layers'] as $catalogue) {
        foreach ($config[$catalogue] ?? [] as $id => $entry) {
            if (!in_array($entry['type'] ?? '', ['tile', 'wms'], true)) {
                $problems[] = sprintf('%s.%s is "%s"', $catalogue, $id, $entry['type'] ?? '');
            }
        }
    }
    return $problems ? implode(', ', $problems) . ', expected tile or wms' : true;
});

// ------------------------------------------------------------------ assets

section('Bundled assets');

$assets = [
    'vendor/leaflet/leaflet.js',
    'vendor/leaflet/leaflet.css',
    // Leaflet's css asks for these by relative path; a file-by-file copy of the
    // library leaves them behind and every marker turns into a broken image.
    'vendor/leaflet/images/marker-icon.png',
    'vendor/leaflet/images/marker-shadow.png',
    'vendor/leaflet/images/layers.png',
    'vendor/leaflet-draw/leaflet.draw.js',
    'vendor/leaflet-draw/leaflet.draw.css',
    'vendor/leaflet-draw/images/spritesheet.png',
    'vendor/leaflet-draw/images/spritesheet.svg',
    // Not Control.FullScreen.js: since 5.0.0 that one is an es module.
    'vendor/leaflet-fullscreen/Control.FullScreen.umd.js',
    'vendor/leaflet-fullscreen/Control.FullScreen.css',
    'vendor/terraformer-wkt/t-wkt.umd-2.2.1.js',
    'js/data-type-geometry-map.js',
    'js/data-type-geometry-editor.js',
    'css/data-type-geometry.css',
];

foreach ($assets as $asset) {
    checking(sprintf('asset/%s is shipped', $asset), function () use ($modulePath, $asset) {
        return is_file($modulePath . '/asset/' . $asset) ?: 'not on disk';
    });
}

checking('the vendored libraries are recorded', function () use ($modulePath) {
    return is_file($modulePath . '/asset/vendor/VERSIONS.md')
        ?: 'asset/vendor/VERSIONS.md is missing: a file on disk cannot be matched against an advisory without it';
});

checking('nothing reaches into Omeka\'s files directory any more', function () use ($modulePath) {
    // The maps used to be assembled from /omeka/files/js/, which is derivative
    // territory rather than code, and is shared with pages this module cannot
    // see. Those files are still in use elsewhere and must stay; the point is
    // that the module no longer depends on them.
    $found = [];
    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulePath . '/src'));
    foreach ($directory as $file) {
        if ($file->isFile() && 'php' === $file->getExtension()
            && false !== strpos((string) file_get_contents($file->getPathname()), '/files/js/')
        ) {
            $found[] = $file->getPathname();
        }
    }
    return $found ? implode(', ', $found) : true;
});

// ------------------------------------------------------------------- helper

section('View helper');

$helpers = $services->get('ViewHelperManager');

checking('geometryMap resolves from the helper manager', function () use ($helpers) {
    $helper = $helpers->get('geometryMap');
    return $helper instanceof \DataTypeGeometry\View\Helper\GeometryMap
        ?: sprintf('got %s', get_class($helper));
});

checking('it renders a value as a map element carrying its own configuration', function () use ($helpers) {
    $markup = $helpers->get('geometryMap')->__invoke('POINT (4.7027444 52.0097589)');
    if (false === strpos($markup, 'data-geometry-map=')) {
        return 'no data-geometry-map attribute: ' . $markup;
    }
    // No id: a resource with two geometries has to draw two maps, which is
    // exactly what the previous id="map" implementation could not do.
    if (false !== strpos($markup, 'id=')) {
        return 'the element has an id, so a second geometry on the page would collide';
    }
    return true;
});

checking('the rendered configuration carries the layers and the value', function () use ($helpers) {
    $markup = $helpers->get('geometryMap')->__invoke('POINT (4.7027444 52.0097589)');
    if (!preg_match('~data-geometry-map="([^"]*)"~', $markup, $matches)) {
        return 'could not read the attribute back';
    }
    $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
    if (!is_array($decoded)) {
        return 'the attribute is not valid json: ' . json_last_error_msg();
    }
    foreach (['map', 'baseLayers', 'wkt'] as $key) {
        if (!isset($decoded[$key])) {
            return sprintf('no "%s" in the rendered configuration', $key);
        }
    }
    return 'POINT (4.7027444 52.0097589)' === $decoded['wkt']
        ?: 'the wkt did not survive: ' . var_export($decoded['wkt'], true);
});

checking('a value containing a quote cannot break out of the attribute', function () use ($helpers) {
    $markup = $helpers->get('geometryMap')->__invoke('POINT (1 1)" onload="alert(1)');
    return false === strpos($markup, 'onload="alert(1)"')
        ?: 'the value escaped its attribute: ' . $markup;
});

// --------------------------------------------------------------- data types

section('Data types');

$dataTypes = $services->get('Omeka\DataTypeManager');

checking('geometry renders as a map', function () use ($dataTypes) {
    $method = new ReflectionMethod($dataTypes->get('geometry'), 'render');
    return \DataTypeGeometry\DataType\Geometry::class === $method->getDeclaringClass()->getName()
        ?: 'render() comes from ' . $method->getDeclaringClass()->getName();
});

checking('geometric coordinates render as a map, not as an unreadable string', function () use ($dataTypes) {
    // This type stores "x,y" rather than wkt, so it needs its own render() to
    // convert; the one inherited from Geometry would hand the map a string no
    // wkt parser accepts.
    $method = new ReflectionMethod($dataTypes->get('geometry:coordinates'), 'render');
    return \DataTypeGeometry\DataType\GeometryCoordinates::class === $method->getDeclaringClass()->getName()
        ?: 'render() comes from ' . $method->getDeclaringClass()->getName();
});

checking('geometric position does NOT inherit the map', function () use ($dataTypes) {
    // Its origin is the top left corner of an image, so "4,52" means four
    // pixels across and fifty-two down. Drawn on a world map it lands in the
    // Gulf of Guinea.
    $method = new ReflectionMethod($dataTypes->get('geometry:position'), 'render');
    return \DataTypeGeometry\DataType\GeometryPosition::class === $method->getDeclaringClass()->getName()
        ?: 'render() comes from ' . $method->getDeclaringClass()->getName() . ', which draws a map';
});

// ------------------------------------------------------------- translations

section('Translatable strings');

checking('the editor\'s strings are in js_translate_strings', function () use ($services) {
    $strings = $services->get('Config')['js_translate_strings'] ?? [];
    $missing = array_diff(
        ['Use geometry editor', 'Geometry editor', 'Apply', 'Cancel', 'The map could not be loaded.'],
        $strings
    );
    return $missing ? 'missing: ' . implode(', ', $missing) : true;
});

// ------------------------------------------------------------------ verdict

printf("\n%d checks, %d failures\n", $checks, $failures);
exit($failures ? 1 : 0);
