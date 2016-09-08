<?php

class EditorialPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
            'install',
            'after_save_exhibit_page_block',
            'before_save_exhibit_page_block',
            'after_delete_exhibit_page_block',
            'admin_head',
            'public_head',
            'define_acl',
            );
    
    protected $_filters = array(
                'exhibit_layouts'
            );
    
    public function hookInstall()
    {
        $db = $this->_db;
        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialBlockResponse` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `text` text COLLATE utf8_unicode_ci NOT NULL,
              `parent_id` int(10) unsigned NOT NULL,
              `owner_id` int(10) unsigned NOT NULL,
              `added` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',
              PRIMARY KEY (`id`),
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);
        
        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialBlockOwner` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `block_id` int(10) unsigned NOT NULL,
              `owner_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`id`),
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);
    }
    
    public function hookPublicHead()
    {
        queue_css_file('editorial');
        queue_js_file('editorial');
    }
    public function hookAdminHead()
    {
        queue_css_file('editorial');
        queue_js_file('editorial');
    }
    
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        $acl->addRole('exhibit-contributor', 'contributor');
        $acl->allow('exhibit-contributor', 'ExhibitBuilder_Exhibits', array('edit'));
    }
    
    public function hookAfterDeleteExhibitPageBlock($args)
    {
        $block = $args['record'];
        $options = $block->getOptions();
        $responseTable = $this->_db->getTable('EditorialBlockResponse');
        $responses = $responseTable->findResponsesForBlock($block);
        foreach ($responses as $response) {
            $response->delete();
        }
        
        $ownerRecord = $this->_db->getTable('EditorialBlockOwner')->findByBlock($block);
        $ownerRecord->delete();
    }
    
    public function hookBeforeSaveExhibitPageBlock($args)
    {
        $block = $args['record'];
        
        if ($block->layout !== 'editorial-block') {
            return;
        }
        
        $responseTable = $this->_db->getTable('EditorialBlockResponse');
        $options = $block->getOptions();
        
        $responseIds = empty($options['response_ids']) ?  array() : $options['response_ids'];
        foreach ($options['responses'] as $responseData) {
            if (! empty ($responseData)) {
                $response = new EditorialBlockResponse;
                $response->text = $responseData;
                $response->save();
                $responseIds[] = $response->id;
            }
        }
        
        $options['response_ids'] = $responseIds;
        unset ($options['responses']);
        
        foreach ($options['edited_responses'] as $responseId => $responseData) {
            $response = $responseTable->find($responseId);
            $response->text = $responseData;
            $response->save();
        }
        
        unset ($options['edited_responses']);

        foreach ($options['child_responses'] as $parentResponseId => $responseData) {
            if (! empty($responseData)) {
                $response = new EditorialBlockResponse;
                $response->parent_id = $parentResponseId;
                $response->text = $responseData;
                $response->save();
            }
        }
        
        unset ($options['child_responses']);
        $block->setOptions($options);
    }

    public function hookAfterSaveExhibitPageBlock($args)
    {
        $block = $args['record'];
        
        $options = $block->getOptions();
        if ($block->layout !== 'editorial-block') {
            return;
        }
        
        if ($args['insert']) {
            $blockOwner = new EditorialBlockOwner;
            $blockOwner->block_id = $block->id;
            $owner = current_user();
            $blockOwner->owner_id = $owner->id;
            $blockOwner->save();
        }

        if ($options['send_emails']) {
            $this->sendEmails($block);
        }
    }

    public function filterExhibitLayouts($layouts)
    {
        $layouts['editorial-block'] = array(
                    'name' => __('Editorial Block'),
                    'description' => __('Provide commentary on pages being worked on')
                );
        return $layouts;
    }
    
    protected function sendEmails($block)
    {
        $options = $block->getOptions();
        $db = $this->_db;
        $restrictionTable = $db->getTable('EditorialBlockRestriction');
        $userTable = $db->getTable('User');

        $userSelect = $userTable->getSelect();
        $userSelect->where("id IN (?)", $options['email_recipients']);
        $users = $userTable->fetchObjects($userSelect);
        $userEmails = array();
        foreach($users as $user) {
            $userEmails[] = $user->email;
        }
        
        $mail = new Zend_Mail('UTF-8');
        $mail->addHeader('X-Mailer', 'PHP/' . phpversion());
        $mail->setFrom(get_option('administrator_email'), get_option('site_title'));
        $mail->addTo($userEmails);
        $subject = __("New content to review at %s ", "<a href='" . WEB_ROOT  . "'></a>" );
        
        $body = '';
        
        $body .= $options['email_text'];
        $body .= "<br/>";
        $body .= snippet($block->text, 0, 250);
        
        $body .= "<p>" . __("View the page at ") . record_url($block->getPage(), 'edit', true) . "</p>";
        $mail->setSubject($subject);
        $mail->setBodyHtml($body);
        try {
            $mail->send();
        } catch(Exception $e) {
            _log($e);
        }
    }
    
    static public function userHasAccess($block, $user = null)
    {
        if (! $block->exists()) {
            return true;
        }
        if (! $user) {
            $user = current_user();
        }
        if (! $user) {
            return false;
        }
        
        if ($user->role == 'super' || $user->role == 'admin') {
            return true;
        }
        
        $options = $block->getOptions();
        $ownerRecord = get_db()->getTable('EditorialBlockOwner')->findByBlock($block);
        
        if ($user->id == $ownerRecord->owner_id) {
            return true;
        }
        
        if (empty ($options['allowed_users'])) {
            return false;
        }
        
        if (in_array($user->id, $options['allowed_users'])) {
            return true;
        }
        return false;
    }
}
