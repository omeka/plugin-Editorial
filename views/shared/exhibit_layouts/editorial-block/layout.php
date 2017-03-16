<?php if (EditorialPlugin::userHasAccess($block)): ?>

<?php
    $topLevelResponses = get_db()->getTable('EditorialBlockResponse')->findResponsesForBlock($block);
    $infoRecord = get_db()->getTable('EditorialBlockInfo')->findByBlock($block);
?>
<div class='editorial-block public'>
    <div>
        <h3><?php echo __('Internal Comments'); ?></h3>
        <div class="drawer opened" role="button" title="<?php echo __('Expand/Collapse'); ?>"></div>
    </div>
    <div class='editorial-block-responses' style="display: block">
        <div class='editorial-block editorial-comment original'>
            <?php
            $ownerRecord = $infoRecord->getOwner();
            echo $this->partial('single-response.php', array(
                'owner' => $ownerRecord,
                'responseText' => $block->text,
                'date' => metadata($infoRecord, 'added'),
                )
            );
            ?>
        </div>

        <?php foreach ($topLevelResponses as $response): ?>
        <div class='editorial-block-response-container'>
            <?php
            echo $this->partial('single-response.php', array(
                'owner' => $response->getOwner(),
                'responseText' => $response->text,
                'date' => metadata($response, 'added'),
                )
            );
            ?>
            <div class='response-replies'>
                <?php $childResponses = $response->getChildResponses(); ?>
                <?php foreach ($childResponses as $childResponse): ?>
                <div class='editorial-block-response-container child'>
                    <?php
                    echo $this->partial('single-response.php', array(
                        'owner' => $childResponse->getOwner(),
                        'responseText' => $childResponse->text,
                        'date' => $childResponse->added
                        )
                    );
                    ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <a href="<?php echo url('admin/exhibits/edit-page/' . $block->page_id); ?>" class="button"><?php echo __('Reply in the admin area'); ?></a>
    </div>
</div>
<?php endif; ?>

