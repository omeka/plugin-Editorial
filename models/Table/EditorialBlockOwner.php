<?php

class Table_EditorialBlockOwner extends Omeka_Db_Table
{
    public function findByBlock($block)
    {
        $select = $this->getSelect();
        $select->where( $this->getTableAlias().'.block_id = ?', $block->id);
        $select->limit(1);
        $select->reset(Zend_Db_Select::ORDER);
        return $this->fetchObject($select);
    }
    
    public function findOwnerByBlock($block)
    {
        if ($block->exists()) {
            $record = $this->findByBlock($block);
            return $record->getOwner();
        }
        
        return current_user();
    }
}