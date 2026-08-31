FacetedBrowse.registerFacetApplyStateHandler('within_box', function(facet, facetState) {
    const thisFacet = $(facet);
    thisFacet.find('select.within-box-value').val(facetState);
});

$(document).ready(function() {

const container = $('#container');

container.on('change', '.within-box-value', function(e) {
    const thisSelect = $(this);
    const facet = thisSelect.closest('.facet');
    let query = '';
    if (thisSelect.val()) {
        const parts = thisSelect.val().split(',');
        query = `geo[mapbox][0]=${encodeURIComponent(parts[0])}`
            + `&geo[mapbox][1]=${encodeURIComponent(parts[1])}`
            + `&geo[mapbox][2]=${encodeURIComponent(parts[2])}`
            + `&geo[mapbox][3]=${encodeURIComponent(parts[3])}`;
    }
    FacetedBrowse.setFacetState(facet.data('facetId'), thisSelect.val(), query);
    FacetedBrowse.triggerStateChange();
});

});
