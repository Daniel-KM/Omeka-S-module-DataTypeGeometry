Data type Geometry (module for Omeka S)
=======================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Data type Geometry] is a module for [Omeka S] that adds five new data types for
the values of properties: `geography`, `geometry`, `geography:coordinates`,
`geometry:coordinates`, `geometry:position`. It allows to manage points and
areas on images and maps.

The data types are managed with the standard [WKT] format.

The datatype for geographic coordinates uses the standard latitude/longitude,
that is called nowadays "GPS" or even "Google coordinates".

The geometric position and geometric coordinates are similar, but the first uses
the top left corner as base and allows only integer values, so it is adapted to
describe images by pixel, and the second use the arithmetic plane with the
bottom left corner as base and allows floats.

It is used by the module [Annotate Cartography], that allows to point markers
and to highlight images and maps. This module can be used independantly too.

It can be used with the module [Mapping]: a batch edit is added to convert
literal data into geographical coordinates and vice-versa, so you can store
markers as a standard rdf data in a property.

It can use an external database for performance.


Installation
------------

### Libraries

The module uses external libraries, so use the release zip to install it, or use
and init the source.

See general end user documentation for [installing a module].

The module [Common] must be installed first.

* From the zip

Download the last release [DataTypeGeometry.zip] from the list of releases
(the master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `DataTypeGeometry`, go to the root of the module, and run:

```sh
composer install --no-dev
```

### Unsupported geographic queries

MySql does not support all geographic queries, for example to [search a point in a polygon for a sphere],
so it is disabled by default in the config.

For MariaDB, because it [supports only flat geometric queries], geographic
search is disabled when it is used.

### Omeka database or external database [work in progress]

The geometries can be saved in Omeka database or in an external one. It’s useful
to share them with other GIS systems. It allows to do advanced search and to
browse quicker too.

By default, related to minimal database versions, Omeka support spatial search,
but not spatial indexing.

#### Before Omeka S v4.1

To support searches of geometries, (distance around a point, geometries
contained inside another geometry), the version of the database should be
minimum [mySql 5.6.1] (2011-02) or [mariaDB 5.3.3] (2011-12-21). Note that the
minimum Omeka version until version 4.0 are [mySql 5.6.4] (2011-11-20), and
[MariaDB 10.0.5] (2013-11-07)).

So you need to check if the database supports spatial indexing. The database
must be equal or greater than [MariaDB 10.2.2] (2016-09-27) or [mySql 5.7.5]
(2014-09-25), for the engine InnoDB. Prior database releases can support spatial
index too, but only with the engine MyIsam (that does not support referential
integrity and is not used by Omeka). The choice between InnoDB and MyIsam is
done automatically.

#### Since Omeka S v4.1

For Omeka S v4.1, the new minimum versions are [mySql 5.7.9], and [MariaDB 10.2.6],
so Omeka is now fine to support spatial indexing.

For a precise support of geometry, see the [spatial support matrix].

#### External database

You may prefer to use an external database. To config it, set the parameters in
the file `config/database-cartography.ini`, beside your main `config/database.ini`.
See the file `config/database-cartography.ini.dist` for more information. The
tables are named [`data_type_geometry`] and [`data_type_geography`].
Furthermore, if PostgreSql is used, the `config/local.config.php` should
indicate it in `[entity_manager][functions][numeric]`, by overriding keys of the
mysql functions.

The support for MariaDB, mySql and PostgreSql is provided though [longitude-one/doctrine-spatial],
an active fork of [creof/doctrine2-spatial].


Usage
-----

To use the new data types, select them for some template properties.

### Batch edit

To convert existing literal coordinates into geographic coordinates, use the
"batch edit" process and select the appropriate options.

The literal value must be formatted with a dot `.` as decimal separator and a
comma `,` to separate the latitude and the longitude. Spaces are ignored.

So the value must be formatted `latitude, longitude` with latitude between
-90 and +90 and the longitude between -180 and +180.

### Geometry

Geometries are WKT data: `POINT (2.294497 48.858252)`.

The geometries are saved as standard Omeka values and indexed in a specific
table with a spatial index for quick search.

**Warning**: The geometry "Circle" is not managed by WKT or by GeoJSON, but only
by the viewer. It is saved as a `POINT` in the database, without radius.

Only common sql spatial functions are enabled. Other ones can be enabled in the
config of the module (via `local.config.php`).

### Geography

Geography is a second data type to manage geographic data. It is the same than
the geometry above, but an additional spatial referentiel identifier (srid) is
saved, so the geometries are georeferenced. It allows to do precise searches on
the Earth, in particular when the distances are longer than some dozen of
kilometers.

Most of the time, the srid is [`4326`], because it’s the Mercator projection
used by default in many expert geographic systems, included GPS. It is not the
one used by OpenStreetMap and derivative web maps, that uses the code [`3857`].
The module [Annotate Cartography] uses `4326` too.

Another value can be set in the main settings, and each value can set a specific
one with the ewkt format: `SRID=4326; POINT (2.294497 48.858252)`. It is not
recommended to set it when it is the default one.

**Warning**: The data type `Geography` doesn’t support complex geometries
(multipoint, multiline and multipolygon). In that case, it’s recommended to use
collections.

### Geographic coordinates

A geographic point is the latitude and longitude coordinates: `48.858252,2.294497`.

In the user interface, this value is composed with two decimal values (`xsd:decimal`),
separated by a `,`. In the database, it is stored the same and as a geographic
WKT point. In the api, the value is a string compliant with [geo:kmlLiteral].

Furthermore, the geolocation position is appended to be compatible with the
[w3c geolocation api]. It avoids issues with json implementations of clients
when casting to float when extracting it.

***Important***: Unlike the data type Geography, the order of values is latitude
then longitude. WKT uses `Point(x y)`, so the representation of a geographic
point is `Point(longitude latitude)`. The geographic point data type uses
`latitude,longitude`, much more common for end users, in particular in
historical data, in "coordinates GPS", in OpenStreetMap coordinates or in
"Google map" as they are called now.

### Geometric coordinates

A geometric point is a `x,y` pair: `2.294497,48.858252`.

In the user interface, this value is composed with two decimal values (`xsd:decimal`),
separated by a `,`. In the database, it is stored the same and as a geometric
WKT point. In the api, the value is a simple string.

### Geometric position

A geometric position is a `x,y` pair with two positive integers values: `2,48`,
representing a point from the top left corner and usually describes the position
of a pixel in a image.

In the api, the value is a simple string. In the database, it is stored the same
in the table `value` but as a geometric  WKT point in the specific table `data_type_geometry`.

**Warning**: In database, the point is based on bottom left corner, according to
the standard x/y axis, so the y is negated.

### JSON-LD and GeoJSON

According to the [discussion] on the working group of JSON-LD and GeoJSON, the
GeoJson cannot be used in all cases inside a JSON-LD.

So the representation uses Omeka types and appends the data type `http://www.opengis.net/ont/geosparql#wktLiteral`
of the [OGC standard]. The deprecated datatype `http://geovocab.org/geometry#asWKT`
is no more used. For geometric coordinates and position, no "@type" is appended.
It appends the data type `http://www.opengis.net/ont/geosparql#kmlLiteral` too
for coordinates.

Furthemore, for compatibility with [w3c geolocation api], the position is
appended for geography coordinates.

```json
{
    "dcterms:spatial": [
        {
            "type": "geography",
            "@value": "POINT (2.294497 48.858252)",
            "@type": "http://www.opengis.net/ont/geosparql#wktLiteral"
        },
        {
            "type": "geography:coordinates",
            "@value": "48.858252,2.294497",
            "@type": "http://www.opengis.net/ont/geosparql#kmlLiteral",
            "position": {
                "coords": {
                    "latitude": 48.858252,
                    "longitude": 2.294497
                },
            },
        }
    ],
    "curation:data": [
        {
            "type": "geometry",
            "@value": "POINT (2.294497 48.858252)"
        },
        {
            "type": "geometry:coordinates",
            "@value": "48.858252,2.294497"
        },
        {
            "type": "geometry:position",
            "@value": "2,48"
        }
    ]
}
```


TODO
----

- [x] Remove doctrine:lexer from composer vendor.
- [ ] Add a checkbox in resource form to append marker to map of module Mapping or a main option?
- [ ] Add a button "select on map" in resource form to specify coordinates directly.
- [ ] Add a js to convert wkt into svg icon (via geojson/d3 or directly).
- [ ] Upgrade terraformer to terraformer.js (need a precompiled js).
- [x] Rename api keys to "geometry", "geography", "geography:coordinates" for Omeka S v4.
- [ ] Support complex forms (multipoint, multiline, multipolygon)
- [x] Improve support of various srid for geography and enable search for geometry.
- [ ] Store the srid in geography directly.
- [ ] Fix parsing check for doctrine and terraformer, for example with an open polygon.
- [ ] Make the minimum version of Omeka 4.1 and remove checks of the database.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

### Module

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

### Libraries

This module uses many open source leaflet libraries. See `asset/vendor` for
details.


Copyright
---------

* See `asset/vendor/` and `vendor/` for the copyright of the libraries.
* Some portions were initially adapted from the modules [Numeric data types] and [Neatline].
* Copyright Daniel Berthereau, 2018-2024, (see [Daniel-KM] on GitLab)

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. The improvements were developed for the digital library of the
[Campus Condorcet].


[Data type Geometry]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry
[Omeka S]: https://omeka.org/s
[WKT]: https://wikipedia.org/wiki/Well-known_text
[Annotate Cartography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography
[Mapping]: https://github.com/Omeka-S-modules/Mapping
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[mySql 5.6.1]: https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-1.html
[MariaDB 5.3.3]: https://mariadb.com/kb/en/library/mariadb-533-release-notes/
[mySql 5.6.4]: https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-4.html
[MariaDB 10.2.20]: https://mariadb.com/kb/en/library/spatial-index/
[spatial support matrix]: https://mariadb.com/kb/en/library/mysqlmariadb-spatial-support-matrix/
[MariaDB 10.2.2]: https://mariadb.com/kb/en/library/spatial-index/
[mySql 5.7.5]: https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-5.html#mysqld-5-7-5-innodb
[mySql 5.7.9]: https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-9.html#mysqld-5-7-9-innodb
[MariaDB 10.2.6]: https://mariadb.com/kb/en/library/spatial-index/
[`data_type_geometry`]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/blob/master/data/install/schema.sql#L1-10
[`data_type_geography`]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/blob/master/data/install/schema.sql#L11-20
[DataTypeGeometry.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/releases
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[longitude-one/doctrine-spatial]: https://github.com/longitude-one/doctrine-spatial
[creof/doctrine2-spatial]: https://github.com/creof/doctrine2-spatial/blob/HEAD/doc/index.md
[search a point in a polygon for a sphere]: https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html
[supports only flat geometric queries]: https://mariadb.com/kb/en/st_srid
[`4326`]: https://epsg.io/4326
[`3857`]: https://epsg.io/3857
[discussion]: https://github.com/json-ld/json-ld.org/issues/397
[geo:kmlLiteral]: http://www.opengis.net/ont/geosparql#kmlLiteral
[OGC standard]: https://www.ogc.org/standards/geosparql
[w3c geolocation api]: https://www.w3.org/TR/geolocation/#position_interface
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Numeric data types]: https://github.com/omeka-s-modules/NumericDataTypes
[Neatline]: https://github.com/performant-software/neatline-omeka-s
[EHESS]: https://www.ehess.fr
[Campus Condorcet]: https://bibnum.campus-condorcet.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
