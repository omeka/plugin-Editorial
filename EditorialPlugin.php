<?php

class EditorialPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
            'after_save_exhibit_page_block',
            'before_save_exhibit_page_block',
            );
    
    protected $_filters = array(
                'exhibit_layouts'
            );
    
    public function hookBeforeSaveExhibitPageBlock($args)
    {
        $block = $args['record'];
        if ($block->layout !== 'editorial-block') {
            return;
        }
        $owner = current_user();

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
        $block->setOptions($options);
    }

    public function hookAfterSaveExhibitPageBlock($args)
    {
        $block = $args['record'];
        if ($block->layout !== 'editorial-block') {
            return;
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
        
        $body = snippet($block->text, 0, 250);
        
        $body .= "<p>" . __("View the page at ") . record_url($block->getPage(), 'edit', true) . "</p>";
        $mail->setSubject($subject);
        $mail->setBodyHtml($body);
        try {
            //$mail->send();
        } catch(Exception $e) {
            _log($e);
        }
    }
    
    static public function userHasAccess($block, $user = null)
    {
        if (! $user) {
            $user = current_user();
        }
        if (! $user) {
            return false;
        }
        
        $options = $block->getOptions();
        if ($user->id == $options['owner_id'] ) {
            return true;
        }
        
        if (in_array($user->id, $options['allowed_users'])) {
            return true;
        }
        return false;
    }
}
