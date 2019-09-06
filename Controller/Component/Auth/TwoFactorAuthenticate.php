<?php
/*******************************************************************************
 * Copyright 2011 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 *******************************************************************************
 * $Id$
 ******************************************************************************/
App::Uses('CurrentAgency', 'Model/Datasource');
App::Uses('CurrentDevice', 'Model/Datasource');
App::Uses('CurrentUser', 'Model/Datasource');
App::Uses('Set', 'Lib');

abstract class TwoFactorAuthenticate
{
    protected $settings = array();

    public function TwoFactorAuthenticate($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    public function settings($key=null)
    {
        if (empty($key))
            return $this->settings;
        
        if (!Set::check($this->settings, $key))
            return null;

        return Set::classicExtract($this->settings, $key);
    }
    
    public function authenticate($token)
    {
        $agency = CurrentAgency::agency();
        $device = CurrentDevice::device();
        $user = CurrentUser::user();

        return $this->twoFactor($agency, $device, $user, $token);
    }
    
    abstract protected function twofactor($agency, $device, $user, $token);
}
