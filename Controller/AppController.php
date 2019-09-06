<?php
/* * *****************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 * **************************************************************************** */
App::uses('AdminEditor', 'Controller');
App::uses('ClipsSession', 'Model/Datasource/Session');
App::uses('Controller', 'Controller');
App::uses('User', 'Model');
App::uses('Xml', 'Utility');
App::uses('TwoFactorAuthenticate', 'Controller/Component/Auth');

class AppController extends Controller {

    public $components = array(
        'CurrentAgency', 'CurrentUser', 'CurrentDevice', 'Mobile', 'RequestHandler', 'Session',
        'Auth' => array(
            // Alias our custom ClipsAuth component as Auth.  This tricks the rest of the app into thinking
            // it's working with the real core object.
            'className' => 'ClipsAuth',

            // authenticate and authorize settings will be loaded based on configuration.  Authentication happens
            // when Auth->login() is called (from UsersController::do_login).  Authorization happens each time the
            // page is accessed after <Controller>::beforeFilter is called.
            //
            // NOTE: ClipsAuth alters the behavior of how these authentication and authorization objects are
            //       interacted with!  See ClipsAuth::identify and ClipsAuth::isAuthorized for more information.
            'authenticate' => array(),
            'authorize' => array(),

            // Generic Auth settings.
            'authError' => 'Access denied.',
            'loginAction' => array(
                'controller' => 'users',
                'action' => 'login',
                'admin' => false,
                'system' => false,
                'plugin' => false
            ),
            'logoutRedirect' => array(
                'controller' => 'users',
                'action' => 'logout',
                'admin' => false,
                'system' => false,
                'plugin' => false
            )
        )
    );
    public $helpers = array('Session', 'CurrentAgency', 'CurrentUser', 'CurrentDevice', 'Html', 'Form',
        'Js' => array('Jquery'));
    
    private $configured = false;
    
    public function __construct(CakeRequest $request, CakeResponse $response)
    {
        parent::__construct($request, $response);
    }

    protected function configure($agencyId=NULL, $force=false)
    {
        if (!$force && $this->configured == true)
            return;
        
        if (empty($agencyId) && !$this->CurrentAgency->impersonating())
            $agencyId = $this->CurrentAgency->agencyId;

        // Replace the core configuration with the agency specific one
        if (!empty($agencyId))
            Configure::load($agencyId, 'Clips', false);

        // Apply the authentication and authorization schemes based on the loaded CLIPS configuration.
        $this->Auth->authenticate = Configure::read('Clips.agency.authenticate');
        $this->Auth->authorize = Configure::read('Clips.agency.authorize');
        
        $this->configured = true;
    }
    
    public function beforeFilter()
    {
        $this->header('X-UA-Compatible: IE=edge');
        
        if (CakePlugin::loaded('ClipsDemo'))
        {
            $this->IntegratedDemo = $this->Components->load('ClipsDemo.IntegratedDemo');
            $this->IntegratedDemo->initialize($this);
        }

        if (in_array($this->request->params['action'], array('login', 'logout')))
            return;

        if ($this->CurrentUser->loggedIn()) {
            $um = ClassRegistry::init('UserSession');
            $um->updateActivity($this->Session->id(), array(
                    'ipAddress' => $this->request->clientIp(),
                    'agencyId' => $this->CurrentAgency->agencyId,
                    'location' => $this->request->here,
                    'agencyUserId' => $this->CurrentUser->agencyUserId,
                    'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId
                ));
        }
        
        // Allow vendor customizations
        //$this->viewClass = 'Theme';
        //$this->theme = 'Customizations';
        $this->configure();
        
        if ($this->CurrentUser->userEnabled === false) {
            $this->Session->setFlash('Your user account has been disabled by an administrator.');
            return $this->redirect($this->Auth->logout());
        }

        // TODO: Using the static method and then the member method
        //       is ugly. Fix that, someday.
        CurrentDevice::update();
        if (!$this->CurrentDevice->deviceEnabled
                && !$this->CurrentUser->isSystemAdmin() 
                && !$this->CurrentUser->isAgencyAdmin()) {
            $this->Session->setFlash('Your device has been disabled by an administrator.');
            return $this->redirect($this->Auth->logout());
        }

        // Administrators (Systems Admin or Agency Admin) require the admin layout when accessing an administrative
        // function.
        if (!isset($this->request->params['ext']) &&
                (isset($this->request->params['admin']) || isset($this->request->params['system'])))
            $this->layout = 'admin';

        // Stuff Auth policies in the view as a variable.
        // TODO: This really should be accessed via a helper.
        $this->set('auth_types', $this->Auth->authenticate);
        $this->set('auth_policies', $this->Auth->policy());
        
        if ($this->name == 'CakeError')
            $this->layout = 'error';
    }

    // We use CakePHP's ControllerAuthorize to execute a couple controller-specific authorization methods.
    // Since CakePHP's implementation doesn't check for the existence of this method, we have a default
    // implementation here.
    public function isAuthorized()
    {
        return true;
    }
    
    public function beforeRender() {
        if ($this->Session->check('DelayRedirect')) {
            $this->set('delay_redirect', $this->Session->read('DelayRedirect'));
            $this->Session->delete('DelayRedirect');
        }

        return parent::beforeRender();
    }

    public function delayRedirect($url, $delay) {
        if (is_array($url))
            $url = Router::url($url);

        $this->Session->write('DelayRedirect', array($delay, $url));
    }

    public function jump_to_page() {
        if ($this->request->is('post')) {
            // Rebuild the URL, stuffing in the page number we want to use.
            $url = Router::parse($this->request->data['url']);
            $url = array_merge($url, $url['pass'], $url['named'], array('page' => $this->request->data['page']));

            // Clear out left-overs from parsing the url
            unset($url['passed']);
            unset($url['named']);

            // Redirect!
            return $this->redirect($url);
        }
    }

}
