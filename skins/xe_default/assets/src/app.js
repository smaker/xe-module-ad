var $ = jQuery

$(function() {
    $('#ad_color, #ad_bgcolor').change(function() {
        var color = $(this).find(':selected').val()
        $(this).prev('.colorOverlay').css('backgroundColor', color == -1 ? "" : color )
    });
})