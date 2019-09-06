<?php
/*******************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/
App::uses('CakeSession', 'Model/Datasource');

class CurrentAgency
{
    public static function registered()
    {
        return CakeSession::check('Agency.Actual');
    }
    
	public static function agency($key=null, $actual=false)
	{
        // Read from the actual agency if the caller requested we do so, or we're not impersonating an agency.
        $actual = ($actual || !self::impersonating());
        $sessionKey = 'Agency.' . (($actual) ? 'Actual' : 'Impersonate');
        if ($key != null)
            $sessionKey.= '.' . $key;

        if (!CakeSession::check($sessionKey))
        {
            // TODO: Should the Agency ORI should be stored in the Agency table, and not the configuration?  Since the
            //       addition of system administrators, it seems like the better place.  But this would give System 
            //       Administrators the ability to set the agency ORI and possibly prevent the agency administrators 
            //       from changing it unless the agency's configuration screen were hacked to change it.
            if ($key == 'ori')
                return Configure::read('Clips.agency.agencyOri');

            return null;
        }

		return CakeSession::read($sessionKey);
	}

    public static function register($agencyId)
    {
        if (self::registered())
            return true;
        
        $agency = self::read($agencyId);
        if (empty($agency))
            return false;

        CakeSession::write('Agency.Actual', $agency);
        return true;
    }
    
	public static function impersonate($agencyId)
	{
        self::clear();
        
        $agency = self::read($agencyId);
        if (empty($agency))
            return;

        CakeSession::write('Agency.Impersonate', $agency);
	}

    public static function clear()
    {
        CakeSession::delete('Agency.Impersonate');
    }

    public static function impersonating()
    {
        return CakeSession::check('Agency.Impersonate');
    }
    
    private static function read($agencyId)
    {
        $agency = ClassRegistry::init('Agency');
        $result = $agency->find('first', array(
            'fields' => array('agencyId', 'interface', 'agencyName'),
            'conditions' => array(
                'agencyId' => $agencyId,
                'agencyEnabled' => 1
            )
        ));

        if (empty($result))
            return null;

        return $result['Agency'];
    }
}
