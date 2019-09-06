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

class CurrentRequest
{
    public static function requestId($requestId=null)
    {
        if ($requestId === null)
        {
            if (!CakeSession::check('Request.requestId'))
                return false;

            return CakeSession::read('Request.requestId');
        }

        if ($requestId === false)
        {
            CakeSession::delete('Request.requestId');
            return true;
        }

        CakeSession::write('Request.requestId', $requestId);
        return $requestId;
    }
    
    public static function paging($page, $totalPages, $totalPerPage, $totalItems)
    {
        CakeSession::write('Request.paging', 
            compact('page', 'totalPages', 'totalPerPage', 'totalItems'));
    }
    
    public static function page($key=null)
    {
        $sessionKey = 'Request.paging';
        if (!empty($key))
            $sessionKey.= '.' . $key;
        
        if (!CakeSession::check($sessionKey))
            return null;
        
        return CakeSession::read($sessionKey);
    }
}
