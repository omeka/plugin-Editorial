<?php

class EditorialPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
            'install',
            'uninstall',
            'deactivate',
            'after_save_exhibit_page_block',
            'before_save_exhibit_page_block',
            'after_delete_exhibit_page_block',
            'admin_head',
            'public_head',
            'define_acl',
            );

    protected $_filters = array(
                'exhibit_layouts',
            );

    public function hookDeactivate()
    {
        $db = $this->_db;
        $exhibitContributors = $db->getTable('User')->findBy(array('role' => 'exhibit-contributor'));

        // assumes that original role was Contributor
        foreach ($exhibitContributors as $user) {
            $user->role = 'contributor';
            $user->save();
        }
    }

    public function hookInstall()
    {
        $db = $this->_db;
        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->EditorialBlockResponse` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `text` text COLLATE utf8_unicode_ci NOT NULL,
              `parent_id` int(10) unsigned NULL,
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
              `block_id` int(10) unsigned NOT NULL,
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

    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];

        $db = get_db();
        if (version_compare($oldVersion, '0.2.0-alpha', '<')) {
            $sql = "
            RENAME TABLE `$db->EditorialBlockInfo` TO `$db->EditorialBlockOwner` ;
            ";
            $db->query($sql);

            $sql = "
            ALTER TABLE `$db->EditorialBlockInfo` ADD COLUMN `added` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'
            ";
            $db->query($sql);

            $sql = "
            ALTER TABLE `$db->EditorialBlockInfo` ADD COLUMN `modified` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'
            ";
            $db->query($sql);
            
            $sql = "
            ALTER TABLE `$db->EditorialBlockResponse` ADD COLUMN `modified` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'
            ";
            $db->query($sql);
        }
    }
    
    public function hookUninstall()
    {
        $db = $this->_db;

        // delete these first so the callback don't look for deleted tables
        $editorialBlocks = $db->getTable('ExhibitPageBlock')->findBy(array('layout' => 'editorial-block'));
        foreach ($editorialBlocks as $block) {
            $block->delete();
        }

        $exhibitContributors = $db->getTable('User')->findBy(array('role' => 'exhibit-contributor'));

        // assumes that original role was Contributor
        foreach ($exhibitContributors as $user) {
            $user->role = 'contributor';
            $user->save();
        }

        $sql = "DROP TABLE IF EXISTS `$db->EditorialBlockInfo`";
        $db->query($sql);

        $sql = "DROP TABLE IF EXISTS `$db->EditorialBlockResponse`";
        $db->query($sql);

        $sql = "DROP TABLE IF EXISTS `$db->EditorialExhibitAccess`";
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
        $acl->allow('exhibit-contributor',
                    'ExhibitBuilder_Exhibits',
                    array('edit', 'showNotPublic'),
                    new EditorialExhibitAccessAclAssertion()
                    );
    }

    public function hookAfterDeleteExhibitPageBlock($args)
    {
        $block = $args['record'];
        $options = $block->getOptions();
        $responseTable = $this->_db->getTable('EditorialBlockResponse');
        $responses = $responseTable->findResponsesForBlock($block);
        foreach ($responses as $response) {
            //sad voodoo for response somehow sometimes being null
            if ($response) {
                $response->delete();
            }
        }

        $infoRecord = $this->_db->getTable('EditorialBlockInfo')->findByBlock($block);
        if ($infoRecord) {
            $infoRecord->delete();
        }
    }

    public function hookBeforeSaveExhibitPageBlock($args)
    {
        $block = $args['record'];

        if ($block->layout !== 'editorial-block') {
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
                $response->text = $responseData;
                $response->save();
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

    public function hookAfterSaveExhibitPageBlock($args)
    {
        $block = $args['record'];

        if ($block->layout !== 'editorial-block') {
            return;
        }

        $options = $block->getOptions();

        if (isset($options['old_id'])) {
            $blockInfoRecord = $this->_db->getTable('EditorialBlockInfo')->findByBlock($options['old_id']);
            $blockInfoRecord->block_id = $block->id;
            $blockInfoRecord->save();

            $accessRecords = $this->_db->getTable('EditorialExhibitAccess')->findBy(array('block_id' => $options['old_id']));
            foreach ($accessRecords as $accessRecord) {
                $accessRecord->block_id = $block->id;
                $accessRecord->save();
            }
        } else {
            $blockInfoRecord = $this->_db->getTable('EditorialBlockInfo')->findByBlock($block->id);
            if (!$blockInfoRecord) {
                $blockInfoRecord = new EditorialBlockInfo();
                $blockInfoRecord->block_id = $block->id;
                $owner = current_user();
                $blockInfoRecord->owner_id = $owner->id;
                $blockInfoRecord->save();
            }
            $blockInfoRecord->save();
        }

        $this->adjustPermissions($block);

        if ($options['send_emails']) {
            $this->sendEmails($block);
        }
    }

    public function filterExhibitLayouts($layouts)
    {
        $layouts['editorial-block'] = array(
                    'name' => __('Editorial Block'),
                    'description' => __('Provide commentary on pages being worked on'),
                );

        return $layouts;
    }

    protected function sendEmails($block)
    {
        $options = $block->getOptions();
        $db = $this->_db;
        $userTable = $db->getTable('User');
        $userSelect = $userTable->getSelect();
        $userSelect->where('id IN (?)', $options['email_recipients']);
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
            $userSelect->where('id IN (?)', $options['allowed_users']);
            $users = $userTable->fetchObjects($userSelect);
        }
        foreach ($users as $user) {
            // don't demote supers or admins
            // also basically assumes that Contributors are the
            // ones being promoted
            if ($user->role != 'super' && $user->role != 'admin') {
                $user->role = 'exhibit-contributor';
                $user->save();
            }
            $accessRecords = $accessTable->findBy(array('user_id' => $user->id,
                                                        'exhibit_id' => $exhibit->id, ));
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
                if ($record->owner_id == $user->id) {
                    return true;
                }

                return false;
            break;

            default:
                return false;
            break;
        }

        return false;
    }
}
