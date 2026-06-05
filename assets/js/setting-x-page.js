jQuery(document).ready(function($) {
    $('.lynxjournal-expansion-trigger').on('click', function() {
        $(this).closest('.lynxjournal-expansion-row').toggleClass('is-open');
    });
    $('.js-lynxjournal-expand-all').on('click', function() { $('.lynxjournal-expansion-row').addClass('is-open'); });
    $('.js-lynxjournal-collapse-all').on('click', function() { $('.lynxjournal-expansion-row').removeClass('is-open'); });
});
