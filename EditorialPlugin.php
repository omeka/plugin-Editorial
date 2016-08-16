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
            
            //if () check if the record exists, by block id and allowed user id
            
            //else, create a new record. that's what the count below does
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
        
        $restrictedBlockIds = $this->restrictedBlockIds($page);
        $select->join("{$db->EditorialBlockRestriction}",
                      "exhibit_page_blocks.id = {$db->EditorialBlockRestriction}.block_id",
                      array()
                );
        $select->where("{$db->EditorialBlockRestriction}.allowed_user_id = ? ", $user->id);
        $select->orWhere("{$db->EditorialBlockRestriction}.owner_id = ? ", $user->id);
        return $select;
    }

    protected function restrictedBlockIds($page)
    {
        $db = get_db();
        $user = current_user();

        $restrictedBlocks = $db->getTable('EditorialBlockRestriction')
                                        ->findBy(array('page_id'=>$page->id));

        $restrictedBlockIds = "";
        foreach ($restrictedBlocks as $restrictedBlock) {
            if ($user) {
                
                if ( ! ( ($restrictedBlock->allowed_user_id == $user->id) && ($restrictedBlock->owner_id == $user->id) ) ) {
                    $restrictedBlockIds .= $restrictedBlock->block_id .= ',';
                }
            } else {
                $restrictedBlockIds .= $restrictedBlock->block_id .= ',';
            }
        }
        return trim($restrictedBlockIds, ',');
    }
}
