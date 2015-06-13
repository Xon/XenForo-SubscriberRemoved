<?php

class SV_SubscriberRemoved_Listener
{
    const AddonNameSpace = 'SV_SubscriberRemoved';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.'_'.$class;
    }
}
