<?php

class EditorialBlockInfo extends Omeka_Record_AbstractRecord
{
    public $block_id;

    public $owner_id;

    public function getOwner()
    {
        return $this->_db->getTable('User')->find($this->owner_id);
    }
}
