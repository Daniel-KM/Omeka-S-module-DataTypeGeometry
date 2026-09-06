# Bundled libraries

Nothing here is fetched from a CDN at runtime, so these copies are what the module
actually runs. Recorded because a file on disk cannot otherwise be matched against an
advisory: two of the four say nothing about their own version.

| Directory | Library | Version | Licence | Upstream |
|---|---|---|---|---|
| `leaflet/` | Leaflet | 1.9.4 | BSD-2-Clause | <https://unpkg.com/leaflet@1.9.4/dist/> |
| `leaflet-draw/` | Leaflet.draw | 1.0.4 | MIT | <https://unpkg.com/leaflet-draw@1.0.4/dist/> |
| `leaflet-fullscreen/` | leaflet.fullscreen | 5.3.3 | MIT | <https://unpkg.com/leaflet.fullscreen@5.3.3/dist/> |
| `terraformer-wkt/` | @terraformer/wkt | 2.2.1 | MIT | <https://unpkg.com/@terraformer/wkt@2.2.1/dist/t-wkt.umd.js> |

All four are at their current release, except @terraformer/wkt, which is one patch behind
(2.2.2). Leaflet 2.0.0 exists only as an alpha and is not a candidate.

**terraformer-wkt is the odd one out: it is not committed.** It is fetched at install time
by `sempia/external-assets`, from the pin in `composer.json` under
`extra.external-assets`, which is why it is the only entry here that `.gitignore` still
excludes. Change the version in `composer.json`, not here.

**leaflet.fullscreen ships the UMD build deliberately.** From 5.0.0 its
`dist/Control.FullScreen.js` is an ES module; loaded in a plain `<script>` tag it throws
and takes the map with it. The file here is `dist/Control.FullScreen.umd.js`, and
`GeometryMap::appendPublicAssets()` names it as `Control.FullScreen.umd.js` so the two
cannot drift apart silently. Do not "tidy" it back to the shorter name by copying the
other dist file.

**Leaflet.draw was trimmed, not copied whole.** The upstream `dist/` also carries
`leaflet.draw-src.{js,css,map}` (development duplicates) and five marker/layer PNGs that
are byte-identical to Leaflet's own `images/`. Only `leaflet.draw.{js,css}`, the three
`spritesheet.*` files its CSS actually references, and the licence are kept. If you
re-copy from a new release, re-check the `url()` list — `grep -o "url([^)]*)"
leaflet.draw.css` — before deleting anything.

**Leaflet's tile blending is overridden, as it is in GeoJsonMap.** Leaflet 1.9.0 added
`mix-blend-mode: plus-lighter` to `.leaflet-container img.leaflet-tile`, to hide the dark
seam a tile shows while it fades in. `asset/css/data-type-geometry.css` puts both of this
module's maps back to `mix-blend-mode: normal`.

This file used to claim the override was unnecessary here, on the reasoning that the maps
stay at integer zoom and so never scale their tiles. The reasoning was wrong, and it was
wrong in a way worth recording: Leaflet is not the only thing that scales a tile. At 110%
page zoom, or on a display with fractional scaling, a 256px tile is laid out across 281.6
device pixels, neighbouring tiles overlap by a fraction of a pixel, and plus-lighter adds
both contributions. On a base layer as light as OpenStreetMap the sum clips to white, so
every tile edge draws as a white line on a ~281px grid — which is exactly how the bug was
reported, and how it was recognised: the grid spacing in the screenshot was 281–282px,
not 256.

Measured on a flat OSM-coloured tile at scale 1.1, across the map interior: **1006
pure-white pixels with plus-lighter, none with normal.** Normal is never worse at any
scale factor tested — 1, 1.1, 1.25, 1.5 — and strictly better at 1.1 and 1.5. The trade
is that a tile may show its dark edge for the length of a fade-in.

`tests/browser/tile-seams.html` holds the measurement; append `?blend=1` to put
plus-lighter back and watch it fail. Re-check it against any Leaflet upgrade, and do not
delete the override on the reasoning that Leaflet ought to know best — that is the
reasoning that produced the bug.

## Checking a copy against upstream

The versions above were established by comparing bytes, not by reading banners. To
re-check one:

```sh
curl -sfL https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js \
    | cmp - leaflet-draw/leaflet.draw.js && echo identical
```

All four were verified this way when they were added.

## Upgrading

Replace the files, update the row above, and check the licence file is still the one
upstream ships. Note that `leaflet-draw`'s npm package has no licence file at all — the
`LICENSE` here came from `MIT-LICENSE.md` in the v1.0.4 tag on GitHub.

Check what a new major actually ships before swapping: leaflet.fullscreen's 2.3.0 → 5.3.3
jump moved the built files into `dist/`, made the default entry point an ES module,
renamed the icon class, and inlined the icon as a data URI, retiring
`icon-fullscreen.svg`. None of that is visible from a version number.

Anything touching drawing or the fullscreen control wants a browser, not just the PHP
tests. See the verification notes in `README.md`.
