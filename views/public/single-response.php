<div class='editorial-block-response-info'>
<?php
    $hash = md5(strtolower(trim($owner->email)));
    $url = "//www.gravatar.com/avatar/$hash";
?>
    <img class='gravatar' src='<?php echo $url; ?>' />
    <span class='username'><?php echo $owner->username; ?></span>
    <?php if (isset($date)): ?>
    <span class="date"><?php echo date('F j, Y \a\t g:i a', strtotime($date)); ?></span>
    <?php else: ?>
    <span class="date"><?php echo __('Original response'); ?></span>
    <?php endif; ?>
</div>
<div class="response-text"><?php echo $responseText; ?></div>
