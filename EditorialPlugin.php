<?php

class EditorialPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
            'after_save_exhibit_page_block',
            );
    
    protected $_filters = array(
                    'exhibit_page_blocks_sql',
                    'exhibit_layouts'
            );
    
    public function hookAfterSaveExhibitPageBlock($args)
    {
        $block = $args['record'];
        if ($block->layout !== 'editorial-block') {
            return;
        }
        $owner = current_user();
        
        $options = $block->getOptions();
        $allowedUsers = $options['users'];
        $allowedUsers[] = $owner->id;
        
        $restrictionTable = $this->_db->getTable('EditorialBlockRestriction');
        $restrictionRecords = $restrictionTable->findBy(array(
                                            'block_id' => $block->id,
                                            'owner_id' => $owner->id));

        foreach($restrictionRecords as $restrictionRecord) {
            if (! in_array($restrictionRecord->allowed_user_id, $allowedUsers)) {
                $restrictionRecord->delete();
            }
        }
        
        foreach($allowedUsers as $userId) {
            $count = $restrictionTable->count(array(
                                        'block_id' => $block->id,
                                        'allowed_user_id' => $userId,
                                      ));
            if ($count == 0) {
                $restriction = new EditorialBlockRestriction;
                $restriction->block_id = $block->id;
                $restriction->page_id = $block->page_id;
                $restriction->allowed_user_id = $userId;
                $restriction->owner_id = $owner->id;
                $restriction->save();
            }
        }
        
        if ($options['send-emails']) {
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
    
    public function filterExhibitPageBlocksSql($select, $args)
    {
        $page = $args['page'];
        $user = current_user();
        $db = get_db();
        
        $select->join("{$db->EditorialBlockRestriction}",
                      "exhibit_page_blocks.id = {$db->EditorialBlockRestriction}.block_id",
                      array()
                );
        $select->where("{$db->EditorialBlockRestriction}.allowed_user_id = ? ", $user->id);
        return $select;
    }
    
    protected function sendEmails($block)
    {
        $db = $this->_db;
        $restrictionTable = $db->getTable('EditorialBlockRestriction');
        $userTable = $db->getTable('User');

        $userSelect = $userTable->getSelect();
        $userSelect->join("{$db->EditorialBlockRestriction}",
                          "users.id = {$db->EditorialBlockRestriction}.allowed_user_id",
                          array()
        );
        $userSelect->where("{$db->EditorialBlockRestriction}.block_id = ?", $block->id);
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
        $body = "";
        $mail->setSubject($subject);
        $mail->setBodyHtml($body);
        try {
            $mail->send();
        } catch(Exception $e) {
            _log($e);
        }
    }
}
