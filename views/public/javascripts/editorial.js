(function ($) {
    $(document).ready(function() {
        $('.editorial-block .drawer').click(function() {
            $(this).toggleClass('opened');
            $(this).toggleClass('closed');
            $(this).parent().siblings('.editorial-block-responses').toggle();
        });
    });
})(jQuery);