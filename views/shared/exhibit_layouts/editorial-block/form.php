<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
$usersForSelect = get_table_options('User');
$currentUser = current_user();

unset ($usersForSelect[$currentUser->id]);

// allow some users to change who's allowed access

$changeAllowed = false;

if ($block->exists()
    && $currentUser->role == 'admin'
    || $currentUser->role == 'super'
    || $currentUser->id == $options['owner_id']
   ) {
    $changeAllowed = true;
}


?>
<?php if (EditorialPlugin::userHasAccess($block)) :?>
<div class="block-text editorial">
<?php else: ?>
<div class="block-text editorial no-access">
<?php endif; ?>
    <h4><?php echo __('Comment'); ?></h4>
    
    <?php
        if ($changeAllowed) {
             echo $this->exhibitFormText($block); 
        } else {
            $html = "<div>";
            $html .= $block->text;
            $html .= "</div>";
            echo $html;
        }
    
    ?>
    
    <?php if ($block->exists()): ?>
    <?php $responses = get_db()->getTable('EditorialBlockResponse')->findResponsesForBlock($block); ?>
    
    <?php
        // block options are all reset from the data in the form,
        // so spoof in the existing response_ids data
        foreach ($options['response_ids'] as $responseId):
    ?>
    <input type='hidden' name='<?php echo $formStem . "[options][response_ids][]"; ?>' value='<?php echo $responseId; ?>' />
    
    <?php endforeach; ?>
    
    <div class='editorial-block-responses'>
        <?php foreach ($responses as $response): ?>
        <div class='editorial-block-response'>
        <?php if ($currentUser->id == $response->owner_id) {
                  echo $this->formTextarea($block->getFormStem() . "[options][edited_responses][{$response->id}]",
                        $response->text, array('rows' => 8));
              } else {
                  echo $response->text;
              }
        ?>
        </div>
        <?php endforeach; ?>

        <div class='editorial-block-response-new'>
            <?php

                echo $this->formLabel($formStem . "[options][responses][]", 'Leave new response');
                echo $this->formTextarea(
                        $formStem . "[options][responses][]",
                        '',
                        array('rows' => 7)
                    );
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
        <input type ='hidden' name='<?php echo $formStem . "[options][owner_id]"; ?>' value='<?php echo $currentUser->id; ?>' />
        <?php echo $this->formLabel($formStem . '[options][allowed_users]', __('Allowed Users')); ?>
        <?php echo $this->formSelect($formStem . '[options][allowed_users]',
                                     @$options['users'],
                                     $selectAttrs,
                                     $usersForSelect
                ); ?>
    </div>
</div>
