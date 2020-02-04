<?php

class EditorialPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'after_save_exhibit_page_block',
        'after_save_exhibit_page',
        'before_save_exhibit_page_block',
        'before_delete_exhibit_page',
        'admin_head',
        'public_head',
        'define_acl',
        'upgrade',
    );

    protected $_filters = array(
        'exhibit_layouts',
    );

    public function hookInstall()
    {
        $db = $this->_db;
        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialBlockResponse` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `text` text COLLATE utf8_unicode_ci NOT NULL,
              `parent_id` int(10) unsigned NULL,
              `block_id` int(10) unsigned NULL,
              `owner_id` int(10) unsigned NOT NULL,
              `added` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',
              `modified` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        // only other way to keep track of ownership is to 
        // make it an input on the form, which creates the
        // possibility to inspect the element and change ownership

        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialBlockInfo` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `block_id` int(10) unsigned NULL,
              `page_id` int(10) unsigned NOT NULL,
              `owner_id` int(10) unsigned NOT NULL,
              `added` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',
              `modified` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialExhibitAccess` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `exhibit_id` int(10) unsigned NOT NULL,
              `block_id` int(10) unsigned NOT NULL,
              `user_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);
    }

    public function hookUninstall()
    {
        $db = $this->_db;

        $editorialBlocks = $db->getTable('ExhibitPageBlock')->findBy(array('layout' => 'editorial-block'));
        foreach ($editorialBlocks as $block) {
            $block->delete();
        }

        $sql = "DROP TABLE IF EXISTS `$db->EditorialBlockInfo`";
        $db->query($sql);

        $sql = "DROP TABLE IF EXISTS `$db->EditorialBlockResponse`";
        $db->query($sql);

        $sql = "DROP TABLE IF EXISTS `$db->EditorialExhibitAccess`";
        $db->query($sql);
    }
    
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $db = $this->_db;
        
        if (version_compare($oldVersion, '1.0.1', '<')) {
            $sql = "ALTER TABLE `$db->EditorialBlockInfo` CHANGE `block_id` `block_id` INT UNSIGNED NULL;";
            $db->query($sql);
            $sql = "ALTER TABLE `$db->EditorialBlockResponse` ADD `block_id` INT UNSIGNED NULL;";
            $db->query($sql);
        }

        if (version_compare($oldVersion, '1.1.0', '<')) {
            $exhibitContributors = $db->getTable('User')->findBy(array('role' => 'exhibit-contributor'));
            foreach ($exhibitContributors as $user) {
                $user->role = 'contributor';
                $user->save();
            }
        }
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
        $acl->allow('contributor', 'ExhibitBuilder_Exhibits', 'showNotPublic');
        $acl->allow('contributor', 'ExhibitBuilder_Exhibits', 'edit',
            new EditorialExhibitAccessAclAssertion);
    }

    /**
     * Save responses to a block
     * 
     * IDs are stored in the block's options
     * @param array $args
     */
    public function hookBeforeSaveExhibitPageBlock($args)
    {
        $block = $args['record'];

        if ($block->layout !== 'editorial-block') {
            return;
        }
        
        if ($block->text == '') {
            $block->addError(__('Editorial Block Text'), __('Text cannot be empty'));
            return;
        }
        
        $responseTable = $this->_db->getTable('EditorialBlockResponse');
        $options = $block->getOptions();
        $responseIds = empty($options['response_ids']) ? array() : $options['response_ids'];
        if (isset($options['responses'])) {
            foreach ($options['responses'] as $responseData) {
                if (!empty($responseData)) {
                    $response = new EditorialBlockResponse();
                    $response->text = $responseData;
                    if (! $response->owner_id) {
                        $response->owner_id = current_user()->id;
                    }
                    $response->save();
                    $responseIds[] = $response->id;
                }
            }
        }
        $options['response_ids'] = $responseIds;
        unset($options['responses']);

        if (isset($options['edited_responses'])) {
            foreach ($options['edited_responses'] as $responseId => $responseData) {
                $response = $responseTable->find($responseId);
                if ($response) {
                    $response->text = $responseData;
                    $response->save();
                }
            }
        }

        unset($options['edited_responses']);

        if (isset($options['child_responses'])) {
            foreach ($options['child_responses'] as $parentResponseId => $responseData) {
                if (!empty($responseData)) {
                    $response = new EditorialBlockResponse();
                    $response->parent_id = $parentResponseId;
                    $response->text = $responseData;
                    $response->save();
                }
            }
        }

        unset($options['child_responses']);
        $block->setOptions($options);
    }

    
    public function hookBeforeDeleteExhibitPage($args)
    {
        $page = $args['record'];
        $blocks = $page->getPageBlocks();
        $blockInfoTable = $this->_db->getTable('EditorialBlockInfo');
        $accessesTable = $this->_db->getTable('EditorialExhibitAccess');
        $responseTable = $this->_db->getTable('EditorialBlockResponse');
        // responses are just stored in the options
        foreach ($blocks as $block) {
            $options = $block->getOptions();
            // @todo: this can probably switch to the default findBy, now that there's a block id
            $responses = $responseTable->findResponsesForBlock($block);
            foreach ($responses as $response) {
                //sad voodoo for response somehow sometimes being null
                if ($response) {
                    $response->delete();
                }
            }
            
            $info = $blockInfoTable->findByBlock($block);
            if ($info) {
                $info->delete();
            }
            
            $accesses = $accessesTable->findBy(array('block_id', $block->id));
            foreach ($accesses as $access) {
                $access->delete();
            }
        }
    }
    
    
    public function hookAfterSaveExhibitPage($args)
    {
        $page = $args['record'];
        $blocks = $page->getPageBlocks();
        $editorialBlockInfoTable = $this->_db->getTable('EditorialBlockInfo');
        $editorialAccessesTable = $this->_db->getTable('EditorialExhibitAccess');
        $editorialResponsesTable = $this->_db->getTable('EditorialBlockResponse');
        $exhibitPageBlockTable = $this->_db->getTable('ExhibitPageBlock');
        // responses are just stored in the options
        $oldBlockInfos = array();
        $oldBlockAccesses = array();
        $newBlockIdMap = array();

        foreach ($blocks as $block) {
            if ($block->layout == 'editorial-block') {
                $options = $block->getOptions();
                if (isset($options['old_id'])) {
                    $oldId = $options['old_id'];
                    $blockInfo = $editorialBlockInfoTable->findByBlock($oldId);
                    if ($blockInfo) {
                        $oldBlockInfos[$oldId] = $blockInfo;
                        $oldBlockAccesses[$oldId] = $editorialAccessesTable->findBy(array('block_id' => $oldId));
                        $oldBlockResponses[$oldId] = $editorialResponsesTable->findBy(array('block_id' => $oldId));
                        $newBlockIdMap[$oldId] = $block->id;
                    }
                }
            }
        }
        
        foreach ($oldBlockInfos as $oldId=>$blockInfo) {
            $blockInfo->block_id = $newBlockIdMap[$oldId];
            $blockInfo->save();
        }

        foreach ($oldBlockAccesses as $oldId => $accessRecords) {
            foreach ($accessRecords as $accessRecord) {
                $accessRecord->block_id = $newBlockIdMap[$oldId];
                $accessRecord->save();
            }
        }
            
        
        // now that all the ids have been updated, change up permissions as needed
        foreach ($blocks as $block) {
            $this->adjustPermissions($block);
            $this->adjustResponseBlockIds($block);
            
        }

        // and clear out block infos for deleted blocks

        $currentPageBlockInfos = $editorialBlockInfoTable->findBy(array('page_id' => $page->id));
        foreach ($currentPageBlockInfos as $currentPageBlockInfo) {
            $extantBlocks = $exhibitPageBlockTable->findBy(array(
                'layout'  => 'editorial-block',
                'page_id' => $page->id,
                'id'      => $currentPageBlockInfo->block_id
            ));
            if (empty($extantBlocks)) {
                $currentPageBlockInfo->delete();
                // and delete the responses
                $responsesToDelete = $editorialResponsesTable->findBy(array('block_id' => $currentPageBlockInfo->block_id));
                foreach($responsesToDelete as $responseToDelete) {
                    $responseToDelete->delete();
                }
            }
        }
    }

    public function hookAfterSaveExhibitPageBlock($args)
    {
        $block = $args['record'];

        if ($block->layout !== 'editorial-block') {
            return;
        }
        $insert = $args['insert'];
        $options = $block->getOptions();

        if ($insert) {
            $blockInfoRecord = new EditorialBlockInfo();
            $blockInfoRecord->block_id = $block->id;
            $blockInfoRecord->page_id = $block->page_id;
            $owner = current_user();
            $blockInfoRecord->owner_id = $owner->id;
            $blockInfoRecord->save();
            
            // insert seems to fire this hook twice, so 
            // send the emails then unset sending emails
            $this->sendEmails($block);
            $options = $block->getOptions();
            $options['send_emails'] = false;
            $block->setOptions($options);
        } else {
            $this->sendEmails($block);
        }
    }

    public function filterExhibitLayouts($layouts)
    {
        $layouts['editorial-block'] = array(
            'name' => __('Editorial Block'),
            'description' => __('Provide commentary on content drafts'),
        );

        return $layouts;
    }

    protected function sendEmails($block)
    {
        $options = $block->getOptions();
        if (! $options['send_emails']) {
            return;
        }

        if (empty($options['email_recipients'])) {
            return;
        }
        $db = $this->_db;
        $userTable = $db->getTable('User');
        $userSelect = $userTable->getSelect();
        $userSelect->where('users.id IN (?)', $options['email_recipients']);
        $users = $userTable->fetchObjects($userSelect);
        $userEmails = array();
        foreach ($users as $user) {
            $userEmails[] = $user->email;
        }

        $mail = new Zend_Mail('UTF-8');
        $mail->addHeader('X-Mailer', 'PHP/'.phpversion());
        $mail->setFrom(get_option('administrator_email'), get_option('site_title'));
        $mail->addTo($userEmails);
        $subject = __('New content to review at %s ', get_option('site_title'));

        $body = '';

        $body .= $options['email_text'];
        $body .= '<br/>';
        $body .= snippet($block->text, 0, 250);

        $body .= "<p><a href='".record_url($block->getPage(), 'edit-page', true)."'>".__('View the page at ').record_url($block->getPage(), 'edit-page', true).'</p>';
        $mail->setSubject($subject);
        $mail->setBodyHtml($body);
        try {
            $mail->send();
        } catch (Exception $e) {
            _log($e);
        }
    }
    
    protected function adjustResponseBlockIds($block)
    {
        $options = $block->getOptions();
        if (! empty($options['response_ids'])) {
            $responseIds = $options['response_ids'];
            $editorialBlockResponseTable = $this->_db->getTable('EditorialBlockResponse');
            $select = $editorialBlockResponseTable->getSelect();
            $select->where('id IN (?)', $responseIds);
            $responses = $editorialBlockResponseTable->fetchObjects($select);
            foreach($responses as $response) {
                $response->block_id = $block->id;
                $childResponses = $response->getChildResponses();
                foreach($childResponses as $childResponse) {
                    $childResponse->block_id = $block->id;
                    $childResponse->save();
                }
                $response->save();
            }
        }
    }

    protected function adjustPermissions($block)
    {
        $options = $block->getOptions();
        $exhibit = $block->getPage()->getExhibit();
        $db = $this->_db;
        $userTable = $db->getTable('User');
        $accessTable = $db->getTable('EditorialExhibitAccess');
        if (empty($options['allowed_users'])) {
            $users = array();
        } else {
            $userSelect = $userTable->getSelect();
            $userSelect->where('users.id IN (?)', $options['allowed_users']);
            $users = $userTable->fetchObjects($userSelect);
        }
        foreach ($users as $user) {
            $accessRecords = $accessTable->findBy(array(
                'user_id' => $user->id,
                'exhibit_id' => $exhibit->id,
            ));
            if (empty($accessRecords)) {
                $accessRecord = new EditorialExhibitAccess();
                $accessRecord->user_id = $user->id;
                $accessRecord->exhibit_id = $exhibit->id;

                $accessRecord->block_id = $block->id;
                $accessRecord->save();
            }
        }
        //clean out users who have had access revoked
        $select = $accessTable->getSelect();
        $select->where('exhibit_id = ?', $exhibit->id);
        $select->where('block_id = ?', $block->id);
        if (!empty($options['allowed_users'])) {
            $select->where('user_id NOT IN (?)', $options['allowed_users']);
        }
        $oldAccessRecords = $accessTable->fetchObjects($select);
        foreach ($oldAccessRecords as $oldAccessRecord) {
            $oldAccessRecord->delete();
        }
    }

    public static function userHasAccess($record, $user = null)
    {
        if (!$record->exists()) {
            return true;
        }
        if (!$user) {
            $user = current_user();
        }
        if (!$user) {
            return false;
        }

        if ($user->role == 'super' || $user->role == 'admin') {
            return true;
        }

        switch (get_class($record)) {
            case 'ExhibitPageBlock':
                $options = $record->getOptions();
                $infoRecord = get_db()->getTable('EditorialBlockInfo')->findByBlock($record);

                if ($user->id == $infoRecord->owner_id) {
                    return true;
                }

                if (empty($options['allowed_users'])) {
                    return false;
                }

                if (in_array($user->id, $options['allowed_users'])) {
                    return true;
                }
            break;

            case 'EditorialBlockResponse':
                return $record->owner_id == $user->id;
            break;

            default:
                return false;
            break;
        }

        return false;
    }
}
