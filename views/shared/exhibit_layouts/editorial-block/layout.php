<?php

if (EditorialPlugin::userHasAccess($block)) :
$topLevelResponses = get_db()->getTable('EditorialBlockResponse')->findResponsesForBlock($block);
?>

<div class='editorial-block public'>
    <div>
        <div class="drawer closed" role="button" title="<?php echo __('Expand/Collapse'); ?>"></div>
        <h3><?php echo __('Internal Comments'); ?></h3>
    </div>
    <div class='editorial-block editorial-comment'>
        <div class='editorial-block-response-info'>
        <?php
            $ownerRecord = get_db()->getTable('EditorialBlockOwner')->findByBlock($block);
            $owner = $ownerRecord->getOwner();
            $hash = md5(strtolower(trim($owner->email)));
            $url = "//www.gravatar.com/avatar/$hash";
        ?>
            <img class='gravatar' src='<?php echo $url; ?>' />
            <div><?php echo $owner->username; ?></div>
        </div>
        <?php echo $block->text; ?>
    </div>
    <div class='editorial-block-responses'>
        <h4>Conversation</h5>
        <?php foreach ($topLevelResponses as $response): ?>

        <div class='editorial-block-response-container'>
            <div>
                <div class='editorial-block-response-info'>
                <?php
                    $owner = $response->getOwner();
                    $hash = md5(strtolower(trim($owner->email)));
                    $url = "//www.gravatar.com/avatar/$hash";
                ?>
                    <img class='gravatar' src='<?php echo $url; ?>' />
                    <div><?php echo $owner->username; ?></div>
                </div>
            </div>
            <div class='editorial-block-response'>
            <?php echo $response->text; ?>
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
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
