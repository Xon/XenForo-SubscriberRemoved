<?php

class SV_SubscriberRemoved_Option_Recipients
{
    public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
    {
        $conversationRecipientsOption = str_replace(array(
            "\r",
            "\r\n"
        ), "\n", $option);
        $usernames = array_filter(explode("\n", $conversationRecipientsOption));
        $users = XenForo_Model::create("XenForo_Model_User")->getUsersByNames($usernames, array(), $notFound);

        if (empty($notFound))
        {
            $usernames = XenForo_Application::arrayColumn($users, 'username');
            $option = implode("\n", $usernames);
            return true;
        }
        

        $dw->error(new XenForo_Phrase('the_following_recipients_could_not_be_found_x', array('names' => implode(', ', $notFound))), $fieldName);
        return false;
    }
}