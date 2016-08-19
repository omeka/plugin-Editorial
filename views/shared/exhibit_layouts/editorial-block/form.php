<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
$usersForSelect = get_table_options('User');
?>

<div class="block-text">
    <h4><?php echo __('Text'); ?></h4>
    <?php echo $this->exhibitFormText($block); ?>
</div>


<div class='layout-options'>
    <div class="block-header">
        <h4><?php echo __('Options'); ?></h4>
        <div class="drawer"></div>
    </div>
    
    <div class='send-emails'>
        <?php
            echo $this->formLabel($formStem . '[options][send-emails]', __('Send Emails') );
            echo $this->formCheckbox($formStem . '[options][send-emails]');
        ?>
    </div>
    <div class='users-select'>
        
        <?php echo $this->formLabel($formStem . '[options][users]', __('Allowed Users')); ?>
        <?php echo $this->formSelect($formStem . '[options][users]',
                                     @$options['users'],
                                     array('multiple' => true, 'size' => 10),
                                     $usersForSelect
                ); ?>
    </div>
</div>