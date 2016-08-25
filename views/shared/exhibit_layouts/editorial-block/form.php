<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
$usersForSelect = get_table_options('User');
$currentUser = current_user();

unset ($usersForSelect[$currentUser->id]);

// allow some users to change who's allowed access
// this could/should be done by a fancy ACL class thing,
// but it's quicker and cheaper this way. I'm ok with this technical debt for now
// see #7

$changeAllowed = false;

if (   $currentUser->role == 'admin'
    || $currentUser->role == 'super'
    || $currentUser->id == $block->owner_id
   ) {
    
    $changeAllowed = true;
}


?>

<div class="block-text">
    <h4><?php echo __('Comment'); ?></h4>
    <?php echo $this->exhibitFormText($block); ?>
    <?php if ($block->exists()): ?>
    <?php $responses = get_db()->getTable('EditorialBlockResponse')->findBy(array('block_id' => $block->id)); ?>
    <div class='editorial-block-responses'>
        <?php foreach ($responses as $response): ?>
        <div>
        
        </div>
        <?php endforeach; ?>
        <div class='editorial-block-response new' style='margin-left: 25px; '>
            <?php 
                echo $this->formLabel($formStem . '[options][response]', 'Leave new response');
                echo $this->formTextarea($formStem . '[options][response]');
            ?>
        </div>
    </div>

    
    <?php endif; ?>
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
        <?php 
            $selectAttrs = array('multiple' => true, 'size' => 10);
            if (!$changeAllowed) {
                $selectAttrs['disabled'] = 'disabled';
            }
        ?>
        <?php echo $this->formLabel($formStem . '[options][users]', __('Allowed Users')); ?>
        <?php echo $this->formSelect($formStem . '[options][users]',
                                     @$options['users'],
                                     $selectAttrs,
                                     $usersForSelect
                ); ?>
    </div>
</div>
