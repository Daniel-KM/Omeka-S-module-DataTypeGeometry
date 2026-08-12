# Browser checks

`tests/verify-wiring.php` proves the module is wired into Omeka. It cannot prove
a map draws: that needs a browser, a real Leaflet, and a real Leaflet.draw.

These two pages drive `asset/js/data-type-geometry-editor.js` — the actual file,
loaded over http from the running site — against a stand-in for the Omeka admin
resource form. They exist because the editor only appears on an authenticated
item edit page, which a headless run cannot reach.

| Page | What it proves |
|---|---|
| `editor.html` | Ctrl+Alt+M and the button open the editor, Leaflet and Leaflet.draw load lazily on first use, the map gets a real size inside the sidebar, an existing value is seeded onto it, drawing writes a single non-`MULTI*` wkt back into the field with a `change` event, an untouched editor writes nothing, and each button edits its own row |
| `collision.html` | The Mapping module's Leaflet 1.9.3 is left alone: the editor adds no second Leaflet, does not replace `window.L`, reuses the Leaflet.draw already there, and Mapping's own map keeps working |
| `sidebar-layout.html` | The panel and the map inside it stay within the viewport, and the layer switcher at the map's top right corner is painted, unclipped and reachable by `elementFromPoint` |

`sidebar-layout.html` is the only one that loads Omeka's own `style.css`, and it
has to: the sidebar has no geometry without it, so a map overflowing the panel
looks perfectly fine in the other two. It exists because that is precisely what
shipped — the panel was widened to 40% while core's `.sidebar.active` kept it at
`left:75%`, so 15% of it hung off the right edge and `overflow-x: hidden` cropped
the layer switcher away. Append **`?bug=1`** to restore that mistake and watch the
assertions catch it; three of them fail.

Measure, do not eyeball, and assert against the viewport as well as the parent:
the switcher was correctly positioned *relative to the panel* the whole time.

Both point at `https://www.goudatijdmachine.nl/omeka/`. Change the `BASE`
constant, and the `<script src>` at the top, to test a different installation.

## Running them

They must be served over http, not opened as files:

```sh
cd tests/browser && php -S 127.0.0.1:8899 &
chromium --headless --disable-gpu --no-sandbox --virtual-time-budget=25000 \
    --dump-dom http://127.0.0.1:8899/editor.html \
    | sed -n '/<pre id="results">/,/<\/pre>/p'
```

Every line should read `ok`, and the last line `0 failures`. The assertions run
on timers, so a slow network shows up as a failure to load Leaflet rather than
as a hang — raise the `setTimeout` values before believing such a result.

Two traps met while writing these, worth knowing before editing them:

- Leaflet puts `leaflet-container` **on** the element it is given, not on a
  child, so a map inside `.geometry-map-canvas` is not found by
  `.find('.leaflet-container')`.
- `map.setZoom()` animates, and `getZoom()` still reports the old value on the
  next line. Assert with `setZoom(n, {animate: false})`.
