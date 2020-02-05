<?php

class EditorialBlockResponse extends Omeka_Record_AbstractRecord
{
    public $text;

    public $parent_id;

    public $block_id;

    public $owner_id;

    public $added;

    public $modified;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Timestamp($this);
        $this->_mixins[] = new Mixin_Owner($this);
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
