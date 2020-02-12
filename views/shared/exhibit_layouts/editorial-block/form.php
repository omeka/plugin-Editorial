<?php
$db = get_db();
$formStem = $block->getFormStem();
$options = $block->getOptions();
$allowedRoles = array('super', 'admin', 'contributor');

$userSelect = $db->getTable('User')->getSelectForFindBy();
$userSelect->reset(Zend_Db_Select::COLUMNS);
$userSelect->from(array(), array('users.id', 'users.name'));
$userSelect->where("role IN ('super', 'admin', 'contributor')");
$usersForSelect = $db->fetchPairs($userSelect);

$currentUser = current_user();


// allow some users to change who's allowed access

$changeAllowed = false;

if ($block->exists()) {
    $blockInfoTable = $db->getTable('EditorialBlockInfo');

    $infoRecord = $blockInfoTable->findByBlock($block);
    $blockOwner = $infoRecord->getOwner();
    
    if ($currentUser->role == 'admin'
        || $currentUser->role == 'super'
        || $currentUser->id == $blockOwner->id
    ) {
        $changeAllowed = true;
    }
    
} else {
    $blockOwner = $currentUser;
    $changeAllowed = true;
}

?>
<?php if (EditorialPlugin::userHasAccess($block)) :?>
<div class="block-text editorial <?php echo $changeAllowed ? 'change-allowed' : 'no-edit'; ?>">
<?php else: ?>
<div class="block-text editorial no-access">
<?php endif; ?>


    <?php if ($block->exists()): ?>
        <div class='editorial-block-response-container original'>
        <?php
            $partialOptions =  array(
                    'original' => true,
                    'originalResponse' => $block->text,
                    'owner' => $blockOwner,
                    'changeAllowed' => $changeAllowed,
                    'infoRecord' => $infoRecord,
                    'editableResponse' => $this->exhibitFormText($block),
                    'block' => $block,
            );
            if (! $changeAllowed) {
                $hiddenInput = "<input type='hidden' name='" . $formStem . "[text]' value='" . $block->text . "' />";
                $partialOptions['hiddenInput'] = $hiddenInput;
            }
            echo $this->partial('single-response.php', $partialOptions);
        ?>
        </div>
        <?php $topLevelResponses = get_db()->getTable('EditorialBlockResponse')->findResponsesForBlock($block); ?>
        <div class='editorial-block-responses'>
            <?php foreach ($topLevelResponses as $response): ?>
            <div class='editorial-block-response-container'>
                <?php
                echo $this->partial('single-response.php', array(
                        'originalResponse' => $response->text,
                        'response' => $response,
                        'owner' => $response->getOwner(),
                        'changeAllowed' => EditorialPlugin::userHasAccess($response),
                        'editableResponse' => $this->formTextarea($formStem."[options][edited_responses][{$response->id}]",
                            $response->text, array('rows' => 8)),
                    )
                );
                ?>
                <div class="response-replies">
                    <?php
                        $childResponses = $response->getChildResponses();
                        foreach ($childResponses as $childResponse) :
                    ?>
                    <div class='editorial-block-response-container child'>
                    <?php
                        echo $this->partial('single-response.php', array(
                                'originalResponse' => $childResponse->text,
                                'response' => $childResponse,
                                'owner' => $childResponse->getOwner(),
                                'changeAllowed' => EditorialPlugin::userHasAccess($childResponse),
                                'editableResponse' => $this->formTextarea($formStem."[options][edited_responses][{$childResponse->id}]", $childResponse->text, array('rows' => 8) )
                            )
                        );
                    ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <p class='editorial-block reply-button'>Reply</p>
                    <div class='editorial-block reply'>
                    <?php
                    echo $this->formTextarea($formStem."[options][child_responses][{$response->id}]",
                            '', array('rows' => 8));
                    ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class='editorial-block-response-new'>
            <?php

                echo $this->formLabel($formStem.'[options][responses][]', 'Leave new response');
                echo $this->formTextarea(
                        $formStem.'[options][responses][]',
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
        <input type='hidden' name='<?php echo $formStem.'[options][response_ids][]'; ?>' value='<?php echo $responseId; ?>' />

        <?php endforeach; ?>
    <?php else: ?>
        <h4>Start a conversation</h4>
        <div class='editorial-block-response-info'>
        <?php
            $hash = md5(strtolower(trim($blockOwner->email)));
            $url = "//www.gravatar.com/avatar/$hash";
        ?>
            <img class='gravatar' src='<?php echo $url; ?>' />
            <span class="username"><?php echo $blockOwner->username; ?></span>
        </div>
        <?php if ($changeAllowed): ?>
            <?php echo $this->exhibitFormText($block); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class='editorial layout-options'>
    <div class="block-header">
        <h4><?php echo __('Options'); ?></h4>
        <div class="drawer"></div>
    </div>

    <?php if ($block->exists()): ?>
    <input type='hidden' class='old-id' name='<?php echo $formStem; ?>[options][old_id]' value='<?php echo $block->id; ?>' />
    <?php endif; ?>

    <?php if (! empty($usersForSelect) && $changeAllowed): ?>
    <div class='users-select'>
        <?php
            $usersForAccessSelect = $usersForSelect;
            unset($usersForAccessSelect[$blockOwner->id]);
            unset($usersForAccessSelect[$currentUser->id]);
            echo $this->formLabel($formStem.'[options][allowed_users]', __('Grant Access To:'));
            echo $this->formSelect($formStem.'[options][allowed_users]',
                                 @$options['allowed_users'],
                                 array('multiple' => true, 'size' => 10),
                                 $usersForAccessSelect
            );
        ?>

    </div>
    <?php else: ?>
        <?php foreach ($options['allowed_users'] as $allowedUserId): ?>
            <input type='hidden' name='<?php echo $formStem; ?>[options][allowed_users][]' value='<?php echo $allowedUserId; ?>' />
        <?php endforeach; ?>
    <?php endif; ?>

    <div class='send-emails'>
        <div>
        <?php
            echo $this->formLabel($formStem.'[options][send_emails]', __('Send Email Notifications?'));
            echo $this->formCheckbox($formStem.'[options][send_emails]',
                                     null,
                                     array('class' => 'email-checkbox')
                    );
            ?>
        </div>
        <div>
        <?php
            $recipientsArray = array();
            if ($blockOwner->id == $currentUser->id) {
                $recipientsArray[$blockOwner->id] = $blockOwner->name.' '.__('(Original Commenter)').' '.__('(You)');
            } else {
                $recipientsArray[$blockOwner->id] = $blockOwner->name.' '.__('(Original Commenter)');
            }

            if (isset($options['allowed_users'])) {
                foreach ($usersForSelect as $userId => $name) {
                    if (in_array($userId, $options['allowed_users'])) {
                        if ($userId == $currentUser->id) {
                            $recipientsArray[$userId] = $name. ' ' . __('(You)');
                        } else {
                            $recipientsArray[$userId] = $name;
                        }
                    }
                }
            }
            echo $this->formLabel($formStem.'[options][email_recipients]', __('Select Recipients'));
            echo $this->formSelect($formStem.'[options][email_recipients]',
                                 array(),
                                 array('class' => 'email-select',  'multiple' => true, 'size' => count($recipientsArray)),
                                 $recipientsArray
            );
            ?>
        </div>
        <div>
        <?php
            echo $this->formLabel($formStem.'[options][email_text]', __('Additional Email Text (Optional)'));
            echo $this->formTextarea($formStem.'[options][email_text]');
        ?>
        </div>
    </div>

</div>
