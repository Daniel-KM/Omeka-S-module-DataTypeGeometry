'use strict';

const del = require('del');
const gulp = require('gulp');
const gulpif = require('gulp-if');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');

const bundle = [
    {
        'source': 'node_modules/terraformer/terraformer.js',
        'dest': 'asset/vendor/terraformer',
        'rename': true,
        'uglify': true,
    },
    {
        'source': 'node_modules/terraformer-arcgis-parser/terraformer-arcgis-parser.js',
        'dest': 'asset/vendor/terraformer-arcgis-parser',
        'rename': true,
        'uglify': true,
    },
    {
        'source': 'node_modules/terraformer-wkt-parser/terraformer-wkt-parser.min.js',
        'dest': 'asset/vendor/terraformer-wkt-parser',
    },
];

gulp.task('clean', function(done) {
    bundle.forEach(function (module) {
        return del.sync(module.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundle.forEach(function (module) {
        gulp.src(module.source)
            .pipe(gulpif(module.rename, rename({suffix:'.min'})))
            .pipe(gulpif(module.uglify, uglify()))
            .pipe(gulp.dest(module.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
