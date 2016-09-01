(function ($) {
    $(document).ready(function() {
        $('.block-form').each(function (index) {
            var noAccessEditorials = $(this).find('.block-text.editorial.no-access');
            if (noAccessEditorials.length != 0) {
               // $(this).remove();
            }
        });
    });
})(jQuery);