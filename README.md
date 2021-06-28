Data type Geometry (module for Omeka S)
=======================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Data type Geometry] is a module for [Omeka S] that adds three new data types for
the properties: `coordinate`, `geometry` and `geography`. It allows to manage
points and areas on images and maps.

The datatype "Coordinates" uses the standard latitude/longitude, that is called
nowadays "GPS" or even "Google coofdinates". The two other data types are
managed with the standard [WKT] format.

It is used by the module [Annotate Cartography], that allows to point markers
and to highlight images and maps. This module can be used independantly too.

It can be used with the module [Mapping]: a batch edit is added to convert
literal data into geographical coordinates and vice-versa, so you can store
markers as standard rdf data.

It can use an external database for performance.


Installation
------------

### Libraries

The module uses external libraries, so use the release zip to install it, or use
and init the source.

See general end user documentation for [installing a module].

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

### Omeka database or external database [work in progress]

The geometries can be saved in Omeka database or in an external one. It’s useful
to share them with other GIS systems. It allows to do advanced search and to
browse quicker too.

So the database must support spatial indexing. It must be equal or greater than
[MariaDB 10.2.2] (2016-09-27) or [mySql 5.7.5] (2014-09-25), for the engine
InnoDB. Prior database releases can support spatial index too, but only with the
engine MyIsam (that does not support referential integrity and is not used by
Omeka). The choice between InnoDB and MyIsam is done automatically.

To support searches of geometres, (distance around a point, geometries contained
inside another geometry), the version of should be minimum [mySql 5.6.1] (2011-02)
or [mariaDB 5.3.3] (2011-12-21). Note that the minimum Omeka version is [mySql 5.5.3]
(2010-03-24, equivalent to [MariaDB 5.5.20] (2012-02-25)), so some releases of
mySql are below the minimum requirement of this module. For a precise support of
geometry, see the [spatial support matrix].

You may prefer to use an external database. To config it, set the parameters in
the file `config/database-cartography.ini`, beside your main `config/database.ini`.
See the file `config/database-cartography.ini.dist` for more information. The
tables are named [`data_type_geometry`] and [`data_type_geography`].
Furthermore, if PostgreSql is used, the `config/local.config.php` should
indicate it in `[entity_manager][functions][numeric]`, by overriding keys of the
mysql functions.

The support for MariaDB, mySql and PostgreSql is provided though [doctrine2-spatial].


Usage
-----

To use the new data types, select them for some template properties.
To convert existing literal coordinates into geographic coordinates, use the
"batch edit" process and select the appropriate options.

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
used by default in many geographic systems and by OpenStreetMap, the most truely
open layer used for the web geolocation and by the module [Annotate Cartography].
Another value can be set in the main settings, and each value can set a specific
one with the ewkt format: `SRID=4326; POINT (2.294497 48.858252)`. It is not
recommended to set it when it is the default one.

**Warning**: The data type `Geography` doesn’t support complex geometries (
multipoint, multiline and multipolygon). In that case, it’s recommended to use
collections.

### Geographic coordinates

A geographic point is the latitude and longitude coordinates: `48.858252,2.294497`.
In the user interface, this value is composed with two decimal values (`xsd:decimal`),
separated by a `,`. In the database, it is stored the same and as a geographic
WKT point. In the api, the value is an object with the two keys (see below),
that can be used with the w3c geolocation api. The values are presented as
strings to avoid issues with json implementations of clients.

***Important***: Unlike the data type Geography, the order of values is latitude
then longitude. WKT uses `Point(x y)`, so the representation of a geographic
point is `Point(longitude latitude)`. The geographic point data type uses
`latitude, longitude`, much more common for end users, in particular in
historical data, in "coordinates GPS", in OpenStreetMap coordinates or in "Google
map".

### JSON-LD and GeoJSON

According to the [discussion] on the working group of JSON-LD and GeoJSON, the
GeoJson cannot be used in all cases inside a JSON-LD. So the representation uses
the data type `http://www.opengis.net/ont/geosparql#wktLiteral` of the [OGC standard].
The deprecated datatype `http://geovocab.org/geometry#asWKT` is no more used.

```json
{
    "dcterms:spatial": [
        {
            "@type": "http://www.opengis.net/ont/geosparql#wktLiteral",
            "@value": "POINT (2.294497 48.858252)",

        },
        {
            "@type": "geometry:geography:coordinates",
            "@value": {
                "latitude": "48.858252",
                "longitude": "2.294497"
            }
        }
    ]
}
```


TODO
----

- [ ] Remove doctrine:lexer from composer vendor.
- [ ] Add a checkbox in resource form to append marker to map of module Mapping or a main option?
- [ ] Add a button "select on map" in resource form to specify coordinates directly.
- [ ] Add a js to convert wkt into svg icon (via geojson/d3 or directly).


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
* Some portions are adapted from the modules [Numeric data types] and [Neatline].
* Copyright Daniel Berthereau, 2018-2021, (see [Daniel-KM] on GitLab)

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. The improvements were developed for the future digital library
of the [Campus Condorcet].


[Data type Geometry]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry
[Omeka S]: https://omeka.org/s
[WKT]: https://wikipedia.org/wiki/Well-known_text
[Annotate Cartography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[MariaDB 10.2.2]: https://mariadb.com/kb/en/library/spatial-index/
[mySql 5.7.5]: https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-5.html#mysqld-5-7-5-innodb
[mySql 5.5.3]: https://dev.mysql.com/doc/relnotes/mysql/5.5/en/news-5-5-3.html
[MariaDB 5.5.20]: https://mariadb.com/kb/en/library/mariadb-5521-release-notes/
[mySql 5.6.1]: https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-1.html
[MariaDB 5.3.3]: https://mariadb.com/kb/en/library/mariadb-533-release-notes/
[spatial support matrix]: https://mariadb.com/kb/en/library/mysqlmariadb-spatial-support-matrix/
[`data_type_geometry`]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/blob/master/data/install/schema.sql#L1-10
[`data_type_geography`]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/blob/master/data/install/schema.sql#L11-20
[DataTypeGeometry.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/releases
[doctrine2-spatial]: https://github.com/creof/doctrine2-spatial/blob/HEAD/doc/index.md
[`4326`]: https://epsg.io/4326
[discussion]: https://github.com/json-ld/json-ld.org/issues/397
[OGC standard]: https://www.ogc.org/standards/geosparql
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Numeric data types]: https://github.com/omeka-s-modules/NumericDataTypes
[Neatline]: https://github.com/performant-software/neatline-omeka-s
[EHESS]: https://www.ehess.fr
[Campus Condorcet]: https://campus-condorcet.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
