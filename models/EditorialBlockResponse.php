<?php

class EditorialBlockResponse extends Omeka_Record_AbstractRecord
{
    public $text;

    public $parent_id;

    public $owner_id;

    public $added;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Timestamp($this, 'added', null);
        $this->_mixins[] = new Mixin_Owner($this);
    }

    public function getOwner()
    {
        return $this->getTable('User')->find($this->owner_id);
    }

    public function getChildResponses()
    {
        return $this->getTable()->findBy(array('parent_id' => $this->id));
    }

    protected function afterSave($args)
    {
        if (empty($this->text)) {
            $this->delete();
        }
    }

    protected function afterDelete()
    {
        $children = $this->getChildResponses();
        foreach ($children as $child) {
            $child->delete();
        }
    }
}
