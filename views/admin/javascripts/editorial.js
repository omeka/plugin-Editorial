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
            $(this).siblings('.reply').toggle();
        });
        
        $('#exhibit-page-form').on('change', '.users-select select', function() {
            var target = $(this);
            var emailSelect = target.parents('.layout-options').find('.email-select');
            emailSelect.find('option').each(function(index) {
                if (index !== 0) {
                    $(this).remove();
                }
            });
            
            var selectedUsers = target.find('option:selected');
            selectedUsers.each(function() {
                var userOption = $(this).clone();
                emailSelect.append(userOption);
            });
            emailSelect.attr('size', selectedUsers.length + 2);
        });
        
        $('#exhibit-page-form').on('click', '.email-checkbox', function() {
            var emailSelect = $(this).parents('.layout-options').find('.email-select');
            if (this.checked) {
                emailSelect.find('option').prop('selected', true);
            } else {
                emailSelect.find('option').prop('selected', false);
            }
        });
        
        $('#exhibit-page-form').on('change', '.email-select', function() {
            var emailCheckbox = $(this).parents('.layout-options').find('.email-checkbox');
            var hasSelected = false;
            $(this).find('option').each(function() {
                if ($(this).prop('selected')) {
                    hasSelected = true;
                    return false;
                }
            });
            emailCheckbox.prop('checked', hasSelected);
        });
    });
})(jQuery);