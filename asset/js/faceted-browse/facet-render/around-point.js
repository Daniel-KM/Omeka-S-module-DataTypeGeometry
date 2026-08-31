FacetedBrowse.registerFacetApplyStateHandler('around_point', function(facet, facetState) {
    const thisFacet = $(facet);
    thisFacet.find('select.around-point-value').val(facetState);
});

$(document).ready(function() {

const container = $('#container');

container.on('change', '.around-point-value', function(e) {
    const thisSelect = $(this);
    const facet = thisSelect.closest('.facet');
    const unit = thisSelect.data('unit') || 'km';
    let query = '';
    if (thisSelect.val()) {
        const parts = thisSelect.val().split(',');
        query = `geo[around][latitude]=${encodeURIComponent(parts[0])}`
            + `&geo[around][longitude]=${encodeURIComponent(parts[1])}`
            + `&geo[around][radius]=${encodeURIComponent(parts[2])}`
            + `&geo[around][unit]=${encodeURIComponent(unit)}`;
    }
    FacetedBrowse.setFacetState(facet.data('facetId'), thisSelect.val(), query);
    FacetedBrowse.triggerStateChange();
});

});
