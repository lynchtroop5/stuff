<?php

/* * *****************************************************************************
 * Copyright 2011 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 * **************************************************************************** */
App::uses('Component', 'Controller');
App::uses('CakeSession', 'Model/Datasource');

class MobileComponent extends Component {

    public $components = array('RequestHandler', 'CurrentDevice');

    public function isMobile() {
        //return true;
        return $this->RequestHandler->isMobile() ||
                $this->CurrentDevice->deviceType == DEVICE_TYPE_MOBILE ||
                $this->CurrentDevice->deviceType == DEVICE_TYPE_HANDHELD;
    }

    public function isHandheld() {
        return $this->RequestHandler->isMobile() ||
                $this->CurrentDevice->deviceType == DEVICE_TYPE_HANDHELD;
    }
    
    public function initialize(Controller $controller) {
        
    }

    public function startup(Controller $controller) {
        $controller->set('is_mobile', $this->isMobile());
    }

    public function beforeFilter(Controller $controller) {
        
    }

    // Determines if we're a mobile device and, if so, updates the layout and view.  The layout is always set to 
    // 'mobile'.  The only thing changed about the view is the path to where the view is located.  For mobile devices,
    // the following paths will be searched in order:
    //
    //    \Themed\<ThemeName>\<Controller>\mobile\<action>.ctp
    //    \<Controller>\mobile\<action>.ctp
    //    \Themed\<ThemeName>\<Controller>\<action>.ctp
    //    \<Controller>\<action>.ctp
    //
    // If a view file is found, the controller's viewPath is updated accordingly.
    public function beforeRender(Controller $controller) {
        // Nothing to do if we're not a mobile device
        if (!$this->isMobile() || !empty($controller->request->params['ext']))
            return;

        // Set the layout to "Mobile"
        if (!$controller->request->is('ajax'))
            $controller->layout = 'mobile';

        // Scan the views folder for a mobile version of the view.  If it's found, we'll use it instead of the
        // default one.  We only check for a themed vs non-themed mobile file.  CakePHP will handle the other paths
        // for us.
        $paths = array();
        $viewBase = APP . 'View' . DS;
        if (!empty($controller->theme))
            $paths[] = $viewBase . 'Themed' . DS . $controller->theme . DS;
        $paths[] = $viewBase;

        foreach ($paths as $path) {
            $path.= $controller->name . DS . 'mobile' . DS . $controller->action . '.ctp';
            if (file_exists($path) == true) {
                $controller->viewPath.= DS . 'mobile';
                break;
            }
        }
    }

    public function afterFilter($controller) {
        
    }

}
