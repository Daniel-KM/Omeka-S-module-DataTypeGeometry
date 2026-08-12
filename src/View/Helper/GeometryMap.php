<?php declare(strict_types=1);

namespace DataTypeGeometry\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Draw a wkt value on a Leaflet map, and prepare the resource-form editor.
 *
 * Both sides are here because they share one thing: the "datatypegeometry"
 * settings, which say what the map is made of. A visitor looking at a value and
 * a cataloguer drawing one therefore see the same base map and the same
 * overlays, without either side owning the configuration.
 *
 * Every asset comes from this module. The map used to be assembled from files
 * under Omeka's own files/ directory, which is derivative territory rather than
 * code: nothing recorded what was there, the directory named "leaflet-1.8.0"
 * actually held a 2019 development snapshot of 1.6.0, and the fullscreen
 * control's stylesheet was loaded without the script that implements it, so the
 * button it styles never existed.
 */
class GeometryMap extends AbstractHelper
{
    /**
     * Files whose presence means someone else already loaded Leaflet.
     */
    const LEAFLET_JS = '~/leaflet(-src)?\.js(\?.*)?$~i';
    const LEAFLET_CSS = '~/leaflet\.css(\?.*)?$~i';

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var bool
     */
    protected $editorPrepared = false;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Render a wkt value as a map, or return the helper itself to reach its
     * other methods.
     *
     * @param string|null $wkt A geometry in wkt, possibly empty: an empty map is
     * still a map, and says "this value has no geometry" more clearly than an
     * empty page does.
     * @return self|string
     */
    public function __invoke(?string $wkt = null)
    {
        return $wkt === null
            ? $this
            : $this->render($wkt);
    }

    /**
     * Render one map for a wkt value.
     *
     * The configuration travels in a data attribute rather than an inline
     * script, so the page can hold any number of maps and none of them needs a
     * known id. The previous implementation bound to a hardcoded id="map", which
     * silently drew only the first of several geometries on a page.
     */
    public function render(string $wkt): string
    {
        $view = $this->getView();

        $this->appendPublicAssets();

        $config = $this->mapConfig();
        $config['wkt'] = $wkt;

        return sprintf(
            '<div class="datatype-geometry-map" style="width:100%%;height:%dpx" data-geometry-map="%s"></div>',
            (int) $config['map']['height'],
            $view->escapeHtmlAttr((string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );
    }

    /**
     * Make the resource form able to open the map editor.
     *
     * Deliberately does not append Leaflet. The Mapping module loads its own
     * copy on the same item edit page, and a second one would replace window.L
     * under it; the order in which two modules append to headScript is not
     * something either can control. So the editor loads Leaflet itself, on first
     * use, and only what is missing — see asset/js/data-type-geometry-editor.js.
     * A page where nobody opens the editor pays nothing for it.
     */
    public function prepareEditor(): self
    {
        if ($this->editorPrepared) {
            return $this;
        }
        $this->editorPrepared = true;

        $view = $this->getView();
        $assetUrl = $view->plugin('assetUrl');

        // The editor cannot resolve module asset paths itself, so it is told
        // them, along with the layers it should offer.
        $config = $this->mapConfig();
        $config['assets'] = [
            'leafletCss' => $assetUrl('vendor/leaflet/leaflet.css', 'DataTypeGeometry'),
            'leafletJs' => $assetUrl('vendor/leaflet/leaflet.js', 'DataTypeGeometry'),
            'leafletDrawCss' => $assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'DataTypeGeometry'),
            'leafletDrawJs' => $assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'DataTypeGeometry'),
            'fullscreenCss' => $assetUrl('vendor/leaflet-fullscreen/Control.FullScreen.css', 'DataTypeGeometry'),
            // The UMD build, for the same reason as on the public side.
            'fullscreenJs' => $assetUrl('vendor/leaflet-fullscreen/Control.FullScreen.umd.js', 'DataTypeGeometry'),
        ];

        $view->headScript()
            ->appendScript(sprintf(
                'window.DataTypeGeometryConfig = %s;',
                (string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ))
            ->appendFile($assetUrl('js/data-type-geometry-editor.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer']);

        return $this;
    }

    /**
     * The settings the browser needs, with the module defaults filled in.
     */
    protected function mapConfig(): array
    {
        return [
            'map' => $this->settings['map'] ?? [],
            'baseLayers' => $this->settings['base_layers'] ?? [],
            'extraLayers' => $this->settings['extra_layers'] ?? [],
        ];
    }

    /**
     * Append what a read-only map needs, skipping any Leaflet already present.
     *
     * A public page can hold a GeoJsonMap block and a geometry value at once,
     * and that module bundles the same Leaflet 1.9.4; loading it twice would be
     * pure waste. The check is by filename because that is all headScript knows.
     */
    protected function appendPublicAssets(): void
    {
        $view = $this->getView();
        $assetUrl = $view->plugin('assetUrl');
        $headLink = $view->headLink();
        $headScript = $view->headScript();

        if (!$this->hasStylesheet(self::LEAFLET_CSS)) {
            $headLink->appendStylesheet($assetUrl('vendor/leaflet/leaflet.css', 'DataTypeGeometry'));
        }
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet-fullscreen/Control.FullScreen.css', 'DataTypeGeometry'))
            ->appendStylesheet($assetUrl('css/data-type-geometry.css', 'DataTypeGeometry'));

        if (!$this->hasScript(self::LEAFLET_JS)) {
            $headScript->appendFile($assetUrl('vendor/leaflet/leaflet.js', 'DataTypeGeometry'), 'text/javascript');
        }
        $headScript
            // Not Control.FullScreen.js: since 5.0.0 that file is an es module,
            // and in a plain script tag it throws and takes the map with it.
            ->appendFile($assetUrl('vendor/leaflet-fullscreen/Control.FullScreen.umd.js', 'DataTypeGeometry'), 'text/javascript')
            ->appendFile($assetUrl('vendor/terraformer-wkt/t-wkt.umd-2.2.1.js', 'DataTypeGeometry'), 'text/javascript')
            // No defer: the script waits for DOMContentLoaded itself, and Omeka
            // themes are free to render headScript wherever they like.
            ->appendFile($assetUrl('js/data-type-geometry-map.js', 'DataTypeGeometry'), 'text/javascript');
    }

    protected function hasScript(string $pattern): bool
    {
        foreach ($this->getView()->headScript()->getContainer() as $item) {
            $src = $item->attributes['src'] ?? null;
            if ($src && preg_match($pattern, (string) $src)) {
                return true;
            }
        }
        return false;
    }

    protected function hasStylesheet(string $pattern): bool
    {
        foreach ($this->getView()->headLink()->getContainer() as $item) {
            $href = $item->href ?? null;
            if ($href && preg_match($pattern, (string) $href)) {
                return true;
            }
        }
        return false;
    }
}
