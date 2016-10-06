<div class='editorial-block-response-info'>
<?php
    $hash = md5(strtolower(trim($owner->email)));
    $url = "//www.gravatar.com/avatar/$hash";
?>
    <img class='gravatar' src='<?php echo $url; ?>' />
    <span class="username"><?php echo $owner->username; ?></span>
    <?php if (isset($original)): ?>
    <span class="original-label">Original response</span>
    <?php endif; ?>
    <?php if ($changeAllowed): ?>
    <a href="#" class="edit-response">(Edit)</a>
    <a href="#" class="cancel-response-edit">(Cancel)</a>
    <?php endif; ?>
</div>
<?php if ($changeAllowed): ?>
<div class="editorial-block-response">
    <?php echo $editableResponse; ?>
</div>
<?php endif; ?>
<div class="original-response preview">
    <div class="response-text"><?php echo $originalResponse; ?></div>
    <a href="#" class="expand-response">Read more&hellip;</a>
    <a href="#" class="collapse-response">Collapse</a>
    <?php if (isset($hiddenInput)): ?>
        <?php echo $hiddenInput; ?>
    <?php endif; ?>
</div>