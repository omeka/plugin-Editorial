<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
$usersForSelect = get_table_options('User');
?>

<div class="block-text">
    <h4><?php echo __('Text'); ?></h4>
    <?php echo $this->exhibitFormText($block); ?>
</div>


<div class='options'>
    <h4><?php echo __('Options'); ?></h4>
    <div class='users-select'>
        
        <?php echo $this->formLabel($formStem . '[options][users]', __('Allowed Users')); ?>
        <?php echo $this->formSelect($formStem . '[options][users]',
                                     @$options['users'],
                                     array('multiple' => true, 'size' => 10),
                                     $usersForSelect
                ); ?>
    </div>
</div>