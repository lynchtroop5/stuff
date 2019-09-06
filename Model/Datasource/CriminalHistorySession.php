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

class CriminalHistorySession
{
    public static function form($key=null)
    {
        $sessionKey = 'CriminalHistory.form';
        if ($key != null)
            $sessionKey.= '.' . $key;
        
        if (!CakeSession::check($sessionKey))
            return null;
        
        return CakeSession::read($sessionKey);
    }
    
    public static function data($key=null)
    {
        $sessionKey = 'CriminalHistory.data';
        if ($key != null)
            $sessionKey.= '.' . $key;
        
        if (!CakeSession::check($sessionKey))
            return null;

        return CakeSession::read($sessionKey);
    }

    public static function setForm($interface, $formId, $formName)
    {        
        if (!Configure::read("Clips.agency.enableCriminalHistoryAudit"))
            return;

        if (CakeSession::read('CriminalHistory.form.formId') == $formId)
            return;

        self::start($interface, $formId, $formName);
    }

    // TODO: This will always return false if a call to setForm is not made.  Such is the case when issuing a 
    //       transaction from the command line.
    public static function isCriminalHistoryForm($interface=null, $formName=null)
    {
        if ($interface === null)
        {
            $criminalHistory = CakeSession::read('CriminalHistory.form');
            if (empty($criminalHistory))
                return false;

            extract($criminalHistory);
        }

        $forms = Configure::read("Interfaces.$interface.criminalHistory.forms");
        if (in_array($formName, $forms))
            return true;
        
        return false;
    }
    
    public static function auditRequired()
    {
        if (!CakeSession::check('CriminalHistory'))
            return false;

        return self::isCriminalHistoryForm() && !self::audited();
    }

    // Returns true if the audited flag is true (the audit form was shown and submitted)
    public static function audited()
    {
        return CakeSession::read('CriminalHistory.audited');
    }
    
    // Sets the audited flag to true, indicating that the audit operation has completed.
    public static function approve($data=null)
    {
        if ($data === false)
            CakeSession::write('CriminalHistory.audited', false);
        else
            CakeSession::write('CriminalHistory.audited', true);
        
        if (!empty($data))
            CakeSession::write('CriminalHistory.data', $data);
    }

    // Determines if the current criminal history session information has been published to the database.
    public static function published()
    {
        return CakeSession::check('CriminalHistory.data.CriminalHistory.criminalHistoryId');
    }
    
    // Pushes the requested criminal history data to the database.  Should be just before a transaction is actually 
    // issued.
    public static function publish()
    {
        if (self::published())
            return;

        $data = array(
            'CriminalHistory' => array(
                'agencyId' => CurrentAgency::agency('agencyId'),
                'entryDate' => date('Y-m-d H:i:s'),
                'deviceName' => CurrentDevice::device('name'),
                'computerName' => CurrentDevice::device('computerName'),
                'ipAddress' => Router::getRequest()->clientIp(),
                'mnemonic' => CurrentDevice::device('mnemonic'),
                'ori' => CurrentDevice::device('ori'),
                'userName' => CurrentUser::user('userName'),
                'userLastName' => CurrentUser::user('userLastName'),
                'userFirstName' => CurrentUser::user('userFirstName'),
                'disposition' => self::data('CriminalHistory.disposition')
            ),
            'CriminalHistoryCustom' => array(
                'criminalHistoryId' => null
            )
        );
        
        $criminalHistory = ClassRegistry::init('CriminalHistory');
        
        if (!$criminalHistory->transaction(null, true))
            return false;
        
        if (!$criminalHistory->save($data['CriminalHistory']))
            return $criminalHistory->transaction(false, true);

        $data['CriminalHistoryCustom']['criminalHistoryId'] = $criminalHistory->id;
        if (self::data('CriminalHistoryCustom'))
            $data['CriminalHistoryCustom'] = array_merge(
                self::data('CriminalHistoryCustom'),
                $data['CriminalHistoryCustom']);

        if (!$criminalHistory->CriminalHistoryCustom->save($data['CriminalHistoryCustom']))
            return $criminalHistory->transaction(false, true);

        if (!$criminalHistory->transaction(true, true))
            return false;

        $data['CriminalHistory']['criminalHistoryId'] = $criminalHistory->id;
        CakeSession::write('CriminalHistory.data', $data);

        return true;
    }

    public static function appendRequest($requestId)
    {
        $criminalHistoryRequest = ClassRegistry::init('CriminalHistoryRequest');
        $criminalHistoryRequest->set(array(
            'criminalHistoryId' => CakeSession::read('CriminalHistory.data.CriminalHistory.criminalHistoryId'),
            'requestId' => $requestId
        ));
        return $criminalHistoryRequest->save();
    }
    
    public static function startNew()
    {
        CakeSession::delete('CriminalHistory.data');
        CakeSession::write('CriminalHistory.audited', false);
    }
    
    public static function inSession()
    {
        return CakeSession::check('CriminalHistory.data');
    }
    
    private static function start($interface, $formId, $formName)
    {
        CakeSession::write('CriminalHistory.form', array(
            'interface' => $interface,
            'formId' => $formId,
            'formName' => $formName
        ));

        CakeSession::write('CriminalHistory.audited', false);
    }
}