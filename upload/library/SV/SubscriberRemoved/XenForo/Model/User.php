<?php
class SV_SubscriberRemoved_XenForo_Model_User extends XFCP_SV_SubscriberRemoved_XenForo_Model_User
{
    public function notifySubscribedUser($action, $user)
    {
        $active_upgrades = $this->fetchAllKeyed("
            select distinct
                xf_user_upgrade.*,
                xf_user_upgrade_log.*
            from xf_user_upgrade_active
            join xf_user_upgrade on xf_user_upgrade.user_upgrade_id = xf_user_upgrade_active.user_upgrade_id
            left join xf_user_upgrade_log on xf_user_upgrade_log.user_upgrade_record_id = xf_user_upgrade_active.user_upgrade_record_id
            where recurring = 1 and user_id = ? and
                xf_user_upgrade_log.log_date =
                (
                    select max(log_date)
                    from xf_user_upgrade_log
                    where xf_user_upgrade_log.user_upgrade_record_id = xf_user_upgrade_active.user_upgrade_record_id
                )
            ", 'user_upgrade_id', $user['user_id']);

        if (empty($active_upgrades))
        {
            return;
        }

        // user has active upgrades and was banned and/or deleted

        $username = $user['username'];
        $email = $user['email'];
        $userlink = XenForo_Link::buildPublicLink('full:members', $user);

        $title = new XenForo_Phrase('SubNotify_thread_subject', array(
            'username' => $username,
            'email' => $email,
            'userlink' => $userlink,
            'action' => $action,
        ));

        $upgrades = array();

        foreach($active_upgrades as $upgrade)
        {
            $transaction_details = @unserialize($upgrade['transaction_details']);
            if (!empty($transaction_details) && is_array($transaction_details))
            {
                $upgrade = array_merge($upgrade, $transaction_details);
            }
            $upgrades[] = new XenForo_Phrase('SubNotify_thread_message_upgrade', $upgrade) . '';
        }

        $message = new XenForo_Phrase('SubNotify_thread_message', array(
            'username' => $username,
            'email' => $email,
            'userlink' => $userlink,
            'action' => $action,
            'upgrades' => implode("\n",$upgrades),
        ));

        $options = XenForo_Application::getOptions();
        if ($options->subnotify_createthread)
        {
            try
            {
                $forumId = $options->subnotify_forumid;
                $threadstarter_userId = $options->subnotify_userid;
                $threadstarter_username = $options->subnotify_username;
                if (empty($forumId) || empty($threadstarter_userId) || empty($threadstarter_username))
                {
                    throw new Exception("Subscriber Removed Notification - Create Thread is not properly configured when reporting $username ($email) as being $action");
                }
                $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
                $threadDw->bulkSet(array(
                    'user_id' => $threadstarter_userId,
                    'node_id' => $forumId,
                    'title' => $title,
                    'username' => $threadstarter_username
                ));

                $firstPostDw = $threadDw->getFirstMessageDw();
                $firstPostDw->set('message', $message);
                $threadDw->save();
            }
            catch(\Exception $e)
            {
                XenForo_Error::logException($e, false);
            }
        }

        if ($options->subnotify_sendpm)
        {
            try
            {
                $conversationStarterId = $options->subnotify_pmsenderid;
                $conversationStarterUsername = $options->subnotify_pmusername;
                $conversationRecipientsOption = str_replace(array(
                    "\r",
                    "\r\n"
                ), "\n", $options->subnotify_pmrecipients);
                $conversationRecipients = array_filter(explode("\n", $conversationRecipientsOption));

                $starterArray = $this->getFullUserById($conversationStarterId, array(
                    'join' => XenForo_Model_User::FETCH_USER_FULL | XenForo_Model_User::FETCH_USER_PERMISSIONS
                ));
                if (empty($starterArray) || empty($conversationStarterUsername) || empty($conversationRecipients))
                {
                    throw new Exception("Subscriber Removed Notification - Start PM is not properly configured when reporting $username ($email) as being $action");
                }
                $starterArray['permissions'] = XenForo_Permission::unserializePermissions($starterArray['global_permission_cache']);

                $conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
                $conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $starterArray);
                $conversationDw->set('user_id', $conversationStarterId);
                $conversationDw->set('username', $conversationStarterUsername);
                $conversationDw->set('title', $title);
                $conversationDw->set('open_invite', 1);
                $conversationDw->set('conversation_open', 1);
                $conversationDw->addRecipientUserNames($conversationRecipients);

                $firstMessageDw = $conversationDw->getFirstMessageDw();
                $firstMessageDw->set('message', $message);
                $conversationDw->save();
            }
            catch(\Exception $e)
            {
                XenForo_Error::logException($e, false);
            }
        }
    }
}
