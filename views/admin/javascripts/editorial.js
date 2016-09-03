(function ($) {
    $(document).ready(function() {
        $('.block-form').each(function (index) {
            var currentBlockForm = $(this);
            var noAccessEditorials = currentBlockForm.find('.block-text.editorial.no-access');
            if (noAccessEditorials.length != 0) {
               $(this).remove();
            }
            // I'm ashamed of this variable name
            var noEditEditorials = currentBlockForm.find('.block-text.editorial.no-edit');
            if (noEditEditorials.length !=0) {
                var deleteButton = currentBlockForm.find('.delete-element');
                deleteButton.remove();
            }
        });
        
        $('.editorial-block.reply-button').click(function() {
            //$(this).siblings('.reply').toggle();
        });
    });
})(jQuery);