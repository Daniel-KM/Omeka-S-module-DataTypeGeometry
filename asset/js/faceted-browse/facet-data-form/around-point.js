FacetedBrowse.registerFacetSetHandler('around_point', function() {
    return {
        unit: $('#around-point-unit').val(),
        values: $('#around-point-values').val()
    };
});
