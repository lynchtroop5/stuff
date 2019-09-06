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

App::uses('Session', 'Core');
App::uses('Router', 'Routing');
App::uses('CurrentAgency', 'Model/Datasource');
App::uses('CurrentDevice', 'Model/Datasource');
App::uses('CurrentUser', 'Model/Datasource');
App::uses('CakeSessionHandlerInterface', 'Model/Datasource/Session');

define('CLIPS_SESSION_FORCE_LOGOUT',   -1);
define('CLIPS_SESSION_ADDRESS_CHANGE', -2);
define('CLIPS_SESSION_FORCE_LOGOUT_DEVICE', -3);

// This class is very similar to CakePHP's built-in database session handling.  However, since we have to use a custom
// model, which doesn't follow CakePHP's naming conventions, we have to re-build the whole class.  An added benefit is
// we get to track additional data in the table to support the active users screen.
class ClipsSession implements CakeSessionHandlerInterface 
{
    protected $model;
    protected $timeout;
    private $forceLogoff;
    private $error;
    private static $instance;

    public function __construct()
    {
        self::$instance = $this;
        $this->error = 0;

        $this->model = ClassRegistry::init('UserSession');
        $this->timeout = Configure::read('Session.timeout');
    }

    public function __destruct()
    {
        // Ensure the session information is written before the database access objects are destroyed by PHP.
        session_write_close();
    }
	
    public static function instance()
    {
        return self::$instance;
    }
    
    // TODO: Move this to the UserSession model to complete the other half of the changes made to fix
    //       CLIPS-83.
    public function forceLogoff()
    {
        return $this->forceLogoff;
    }
    
    // TODO: Move this to the UserSession model to complete the other half of the changes made to fix
    //       CLIPS-83.
    public function error()
    {
        $ret = $this->error;
        $this->error = 0;
        return $ret;
    }
    
    // TODO: Move this to the UserSession model to complete the other half of the changes made to fix
    //       CLIPS-83.
    public function errorString($error)
    {
        if (empty($error))
            $error = $this->error();
        
        switch($error)
        {
        case CLIPS_SESSION_FORCE_LOGOUT:    
            return 'User has been logged out by an administrator';
            
        case CLIPS_SESSION_FORCE_LOGOUT_DEVICE: 
            return 'You have been logged out because you logged in to another device';
            
        case CLIPS_SESSION_ADDRESS_CHANGE:  
            return 'Session/Device Conflict';
        }
    }
    
    public function open()
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    // TODO: Move dealing with the force logoff to the UserSession model to complete the other half of the 
    //       changes made to fix CLIPS-83.
    public function read($id)
    {
        $result = $this->model->find('first', array(
            'fields'     => array('ipAddress', 'sessionData', 'forceLogoff'),
            'conditions' => array($this->model->primaryKey => $id)
        ));

        if (cs_debug_writing_session())
        {
            cs_debug_begin_capture();
            echo "Session Read ($id): ";
            $resultCopy = $result[$this->model->alias];
            $resultCopy['sessionData'] = session_decode($resultCopy['sessionData']);
            print_r($resultCopy);
            echo "\r\n";
            cs_debug_end_capture();
        }

        if (empty($result[$this->model->alias]))
            return '';

        $result = $result[$this->model->alias];

        // We don't fail the session because we need it. It's detected in the core application
        // code.
        $request = Router::getRequest();
        if ($request && $result['ipAddress'] != $request->clientIp())
            $this->error = CLIPS_SESSION_ADDRESS_CHANGE;
        
        $this->forceLogoff = (!empty($result['forceLogoff'])
                ? $result['forceLogoff']
                : 0);
        switch($this->forceLogoff)
        {
            case 1:
                $this->error = CLIPS_SESSION_FORCE_LOGOUT;
                break;
            
            case 2:
                $this->error = CLIPS_SESSION_FORCE_LOGOUT_DEVICE;
                break;
            
            default:
                break;
        }            

        if (empty($result['sessionData']))
        {
            $this->error = 'No user session data stored';
            return '';
        }

        return (string)$result['sessionData'];
    }

    public function write($id, $data)
    {
        // Store the Agency ID, User Name, Device Name, IP Address, and Session Data
        $request = Router::getRequest();
        $clientIp = (($request) ? $request->clientIp() : NULL);

        $session[$this->model->alias] = array(
                $this->model->primaryKey => $id,
                'sessionData' => $data,
                'ipAddress' => $clientIp
            );
        
        if (cs_debug_writing_session())
        {
            cs_debug_begin_capture();
            echo "Session Write ($id): ";
            $sessionCopy = $session;
            $sessionCopy['sessionData'] = session_decode($sessionCopy['sessionData']);
            print_r($sessionCopy);
            echo "\r\n";
            cs_debug_end_capture();
        }

        $options = array(
            'validate' => false,
            'callbacks' => false,
            'counterCache' => false
        );
        
        // HACK: Prevent the tblSession table from being accessed during unit testing.
        //       Doing so causes core CakePHP tests to fail.
        if ($this->model->useDbConfig != 'test')
        {
            try                    { return (bool)$this->model->save($session, $options); }
            catch(PDOException $e) { return (bool)$this->model->save($session, $options); }
        }
        else {
            return true;
        }
    }

    public function destroy($id)
    {
        if (cs_debug_writing_session())
            cs_debug_write("Session Destroy ($id)\r\n");

        // HACK: Prevent the tblSession table from being accessed during unit testing.
        //       Doing so causes core CakePHP tests to fail.
        if ($this->model->useDbConfig != 'test') {
            return (bool)$this->model->delete($id);
        }
        else {
            return true;
        }
    }

    public function gc($expires=null)
    {
        if (!$expires)
           $expires = time();

        $expires = date('Y-m-d H:i:s', $expires - (60 * $this->timeout));
        // HACK: Prevent the tblSession table from being accessed during unit testing.
        //       Doing so causes core CakePHP tests to fail.
        if ($this->model->useDbConfig != 'test')
        {
            $this->model->deleteAll(array(
                    "{$this->model->alias}.lastActivityTime <= CONVERT(datetime,'$expires')"
                ), false, false);
        }

        return true;
    }
}
