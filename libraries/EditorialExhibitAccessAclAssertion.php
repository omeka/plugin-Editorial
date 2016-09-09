<?php

class EditorialExhibitAccessAclAssertion implements Zend_Acl_Assert_Interface
{

    public function assert(
        Zend_Acl $acl,
        Zend_Acl_Role_Interface $role = null,
        Zend_Acl_Resource_Interface $resource = null,
        $privilege = null)
    {

        // it looks like without this always being true,
        // the second check doesn't get made
        // for this privilege, the exhibit object isn't passed in
        // so I can't check on that
        // whomp whomp
        if ($privilege == 'showNotPublic') {
            return true;
        }

        if (get_class($role) == 'User' && get_class($resource) == 'Exhibit') {
            if ($privilege == 'edit') {
                $db = get_db();
                $accessTable = $db->getTable('EditorialExhibitAccess');
                $accessRecords = $accessTable->findBy(array('user_id' => $role->id,
                                                            'exhibit_id' => $resource->id));
                return ! empty($accessRecords);
            }
        }
        return false;
    }
}
