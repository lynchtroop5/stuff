<?php
/*******************************************************************************
 * Copyright 2014 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/
App::uses('AuthComponent', 'Controller/Component');

class ClipsAuthComponent extends AuthComponent
{
    protected $policyCache = array();

    public function policy($name=null)
    {
        if (empty($this->policyCache))
        {
            if (empty($this->_authenticateObjects)) 
            {
                $this->constructAuthenticate();
            }

            foreach ($this->_authenticateObjects as $auth) 
            {
                if (empty($auth->clips_policies))
                    continue;
                
                // False values take precedence.
                foreach($auth->clips_policies as $policy => $value)
                {
                    if (!empty($this->policyCache[$policy]))
                        $this->policyCache[$policy] = $value;
                    else
                        $this->policyCache[$policy] = false;
                }
                
                $this->policyCache = array_merge($this->policyCache, $auth->clips_policies);
            }
        }

        if (empty($name))
            return $this->policyCache;
        
        if (!empty($this->policyCache[$name]))
            return $this->policyCache[$name];
        
        return null;
    }
    
    // Alter the behavior of CakePHP's core identify method to execute all authentication handlers
    // until one fails.  If one fails, we fail authentication.  Otherwise, each authentication 
    // handler has the ability to extend user information when it executes.
    //
    // Original code is from CakePHP's base AuthComponent, with some minor modifications.
	public function identify(CakeRequest $request, CakeResponse $response) 
    {
		if (empty($this->_authenticateObjects)) 
        {
			$this->constructAuthenticate();
		}
        
        $data = array();
		foreach ($this->_authenticateObjects as $auth) 
        {
            // Inject the current user data that will be returned.
            $auth->data = $data;
            
            // Call $auth->authenticate.  The handler can access currently configured user data
            // through $this->data.  It should return any new array data that should be merged into
            // $this->data.
			$result = $auth->authenticate($request, $response);
            
            // Keep going unless authentication explicitly fails.
			if (!empty($result) && is_array($result)) 
                $data = Hash::merge($data, $result);
            else if ($result === true)
                continue;
            else
            {
                // Set $this->error in your auth object to set loginError on the auth component.
                // Callers can use that error message, if they choose.
                if (!empty($auth->error))
                    $this->loginError = $auth->error;
                return false;
            }
		}
		return $data;
    }
    
    // Alter the behavior of CakePHP's core isAuthorized method to execute all authorization handlers
    // until one fails.  If one fails, authorization fails.  Otherwise, each authorization handler gets
    // a chance to further restrict user access as needed.
    //
    // Original code is from CakePHP's base AuthComponent, with some minor modifications.
	public function isAuthorized($user = null, CakeRequest $request = null) 
    {
		if (empty($user) && !$this->user()) 
        {
			return false;
		}
		if (empty($user)) 
        {
			$user = $this->user();
		}
		if (empty($request)) 
        {
			$request = $this->request;
		}
		if (empty($this->_authorizeObjects)) 
        {
			$this->constructAuthorize();
		}

		foreach ($this->_authorizeObjects as $authorizer) 
        {
			if ($authorizer->authorize($user, $request) !== true)
            {
                return false;
            }
		}
        
		return true;
    }
}
