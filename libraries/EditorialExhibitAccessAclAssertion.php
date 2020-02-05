<?php

class EditorialExhibitAccessAclAssertion implements Zend_Acl_Assert_Interface
{
    public function assert(
        Zend_Acl $acl,
        Zend_Acl_Role_Interface $role = null,
        Zend_Acl_Resource_Interface $resource = null,
        $privilege = null)
    {
        if ($role instanceof User && $resource instanceof Exhibit) {
            $db = get_db();
            $accessTable = $db->getTable('EditorialExhibitAccess');
            $accessRecords = $accessTable->findBy(array(
                'user_id' => $role->id,
                'exhibit_id' => $resource->id,
            ));

            return !empty($accessRecords);
        }
        return false;
    }
}
