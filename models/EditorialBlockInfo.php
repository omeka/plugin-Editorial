<?php

class EditorialBlockInfo extends Omeka_Record_AbstractRecord
{
    public $block_id;

    public $page_id;

    public $owner_id;

    public $added;

    public $modified;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Timestamp($this); 
    }
    
    public function getOwner()
    {
        return $this->_db->getTable('User')->find($this->owner_id);
    }
}
