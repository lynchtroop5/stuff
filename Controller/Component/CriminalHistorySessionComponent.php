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
App::uses('Component', 'Controller');
App::uses('CriminalHistorySession', 'Model/Datasource');

class CriminalHistorySessionComponent extends Component
{
    public function initialize(Controller $controller)
    {
        parent::initialize($controller);
    }

    public function startup(Controller $controller)
    {
        parent::startup($controller);
    }

    public function form($key=null)
    {
        return CriminalHistorySession::form($key);
    }
    
    public function data($key=null)
    {
        return CriminalHistorySession::data($key);
    }

    public function setForm($interface, $formId, $formName)
    {
        CriminalHistorySession::setForm($interface, $formId, $formName);
    }

    public static function isCriminalHistoryForm($interface=null, $formName=null)
    {
        return CriminalHistorySession::isCriminalHistoryForm($interface, $formName);
    }

    public static function auditRequired()
    {
        return CriminalHistorySession::auditRequired();
    }
    
    public static function audited()
    {
        return CriminalHistorySession::audited();
    }
    
    public static function approve($data=null)
    {
        CriminalHistorySession::approve($data);
    }
    
    public function published()
    {
        return CriminalHistorySession::published();
    }
    
    public function publish()
    {
        return CriminalHistorySession::publish();
    }

    public static function appendRequest($requestId)
    {
        return CriminalHistorySession::appendRequest($requestId);
    }
    
    public static function startNew()
    {
        CriminalHistorySession::startNew();
    }

    public static function inSession()
    {
        return CriminalHistorySession::inSession();
    }
}
