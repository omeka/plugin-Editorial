<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
$usersForSelect = get_table_options('User');
$currentUser = current_user();
$owner = get_db()->getTable('EditorialBlockOwner')->findOwnerByBlock($block);

unset ($usersForSelect['']);

// allow some users to change who's allowed access

$changeAllowed = false;

if ($block->exists()) {
    if (   $currentUser->role == 'admin'
        || $currentUser->role == 'super'
        || $currentUser->id == $owner->id
    ) {
        $changeAllowed = true;
    }
} else {
    $changeAllowed = true;
}


?>
<?php if (EditorialPlugin::userHasAccess($block)) :?>
<div class="block-text editorial <?php echo $changeAllowed ? 'change-allowed' : 'no-edit'; ?>">
<?php else: ?>
<div class="block-text editorial no-access">
<?php endif; ?>
    <h4><?php echo __('Comment'); ?></h4>
    
    
    <div class='editorial-block-response-info'>
    <?php
        $owner = get_db()->getTable('EditorialBlockOwner')->findOwnerByBlock($block);
        $hash = md5(strtolower(trim($owner->email)));
        $url = "//www.gravatar.com/avatar/$hash";
    ?>
        <img class='gravatar' src='<?php echo $url; ?>' />
        <div><?php echo $owner->username; ?></div>
    </div>
    <?php if ($changeAllowed): ?>
    <?php echo $this->exhibitFormText($block); ?>
    <?php else: ?>
    <div>
    <?php echo $block->text; ?>
    <input type='hidden' name='<?php echo $formStem; ?>[text]' value='<?php echo $block->text; ?>' />
    </div>
    <?php endif; ?>
    
    <?php if ($block->exists()): ?>
    <?php $topLevelResponses = get_db()->getTable('EditorialBlockResponse')->findResponsesForBlock($block); ?>
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
    <?php
        // block options are all reset from the data in the form,
        // so spoof in the existing response_ids data
        foreach ($options['response_ids'] as $responseId):
    ?>
    <input type='hidden' name='<?php echo $formStem . "[options][response_ids][]"; ?>' value='<?php echo $responseId; ?>' />
    
    <?php endforeach; ?>
    
    <div class='editorial-block-responses'>
        <?php if (count($topLevelResponses) !=0 ): ?>
        <h5><?php __('Conversation'); ?></h5>
        <?php endif; ?>
        <?php foreach ($topLevelResponses as $response): ?>

        <div class='editorial-block-response-container'>
            <div>
                <div class="drawer closed" role="button" title="<?php echo __('Expand/Collapse'); ?>"></div>

                <div class='editorial-block-response-info'>
                <?php
                    $owner = $response->getOwner();
                    $hash = md5(strtolower(trim($owner->email)));
                    $url = "//www.gravatar.com/avatar/$hash";
                ?>
                    <img class='gravatar' src='<?php echo $url; ?>' />
                    <div><?php echo $owner->username; ?></div>
                </div>
                <div>
                    <?php echo snippet($response->text, 0, 100); ?>
                </div>
            </div>
            <div class='editorial-block-response'>
            <?php if ($currentUser->id == $response->owner_id) {
                      echo $this->formTextarea($block->getFormStem() . "[options][edited_responses][{$response->id}]",
                            $response->text, array('rows' => 8));
                  } else {
                      echo $response->text;
                  }
            ?>
                <?php $childResponses = $response->getChildResponses();
                    foreach ($childResponses as $childResponse) :
                ?>
                <div class='editorial-block-response-container child'>
                    <div class='editorial-block-response-info'>
                    <?php
                        $owner = $childResponse->getOwner();
                        $hash = md5(strtolower(trim($owner->email)));
                        $url = "//www.gravatar.com/avatar/$hash";
                    ?>
                        <img class='gravatar' src='<?php echo $url; ?>' />
                        <div><?php echo $owner->username; ?></div>
                    </div>
                    <div>
                    <?php echo $childResponse->text; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            
                <div>
                    <p class='editorial-block reply-button'>Reply</p>
                    <div class='editorial-block reply'>
                    <?php
                    echo $this->formTextarea($block->getFormStem() . "[options][child_responses][{$response->id}]",
                            '', array('rows' => 8));
                    ?>
                    </div>
                </div>
            </div>

        </div>
        <?php endforeach; ?>


    </div>
    <?php endif; ?>
</div>

<div class='layout-options'>
    <div class="block-header">
        <h4><?php echo __('Options'); ?></h4>
        <div class="drawer"></div>
    </div>
    
    <div class='send-emails'>
        <div>
        <?php
            echo $this->formLabel($formStem . '[options][send_emails]', __('Send Email Notifications?') );
            echo $this->formCheckbox($formStem . '[options][send_emails]');
            ?>
        </div>
        <div>
        <?php
            $recipientsArray = array();
            if ($owner->id == $currentUser->id) {
                $recipientsArray[$owner->id] = $owner->name . " " . __("(Original Commenter)") . " " . __("(You)");
            } else {
                $recipientsArray[$owner->id] = $owner->name . " " . __("(Original Commenter)");
            }
            
            if (isset ($options['allowed_users'])) {
                foreach ($usersForSelect as $userId => $name) {
                    if (in_array($userId, $options['allowed_users'])) {
                        if ($userId == $currentUser->id) {
                            $recipientsArray[$userId] = $name . " " . __("(You)");
                        } else {
                            $recipientsArray[$userId] = $name;
                        }
                        
                    }
                }
            }
            echo $this->formLabel($formStem . '[options][email_recipients]', __('Select Recipients'));
            if (empty ($recipientsArray)) {
                echo "<p>" . __("All the users you give access to below will receive an email.") . "</p>";
                echo "<p>" . __("After users have been given access, you can select them here.") . "</p>";
            } else {
                echo $this->formSelect($formStem . '[options][email_recipients]',
                                     array(),
                                     array('multiple' => true, 'size' => count($recipientsArray)),
                                     $recipientsArray
                );
            }
            ?>
        </div>
        <div>
        <?php
            echo $this->formLabel($formStem . '[options][email_text]', __('Additional Email Text (Optional)'));
            echo $this->formTextarea($formStem . '[options][email_text]');
        ?>
        </div>
    </div>
    <div class='users-select'>
        <?php 
            if ($changeAllowed) {
                unset ($usersForSelect[$owner->id]);
                echo $this->formLabel($formStem . '[options][allowed_users]', __('Grant Access To:'));
                echo $this->formSelect($formStem . '[options][allowed_users]',
                                     @$options['allowed_users'],
                                     array('multiple' => true, 'size' => 10),
                                     $usersForSelect
                );
            } else {
                foreach ($options['allowed_users'] as $allowedUserId) {
                    echo "<input type='hidden' name='{$formStem}[options][allowed_users][]' value='$allowedUserId' />";
                }
            }
        ?>

    </div>
</div>
