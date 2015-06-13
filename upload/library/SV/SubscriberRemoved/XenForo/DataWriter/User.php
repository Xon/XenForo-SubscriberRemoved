<?php
class SV_SubscriberRemoved_XenForo_DataWriter_User extends XFCP_SV_SubscriberRemoved_XenForo_DataWriter_User
{
	protected function _postSave()
	{
        parent::_postSave();
        if ($this->isChanged('is_banned') && $this->get('is_banned'))
        {
            $this->getModelFromCache('XenForo_Model_User')->notifySubscribedUser('banned', $this->getMergedData());
        }
    }

	protected function _postDelete()
	{
        if (!$this->get('is_banned'))
        {
            $this->getModelFromCache('XenForo_Model_User')->notifySubscribedUser('deleted', $this->getMergedData());
        }
        return parent::_postDelete();
    }
}
