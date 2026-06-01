jQuery(document).ready(function($) {
    $('.linkdigest-expansion-trigger').on('click', function() {
        $(this).closest('.linkdigest-expansion-row').toggleClass('is-open');
    });
    $('.js-linkdigest-expand-all').on('click', function() { $('.linkdigest-expansion-row').addClass('is-open'); });
    $('.js-linkdigest-collapse-all').on('click', function() { $('.linkdigest-expansion-row').removeClass('is-open'); });
});
