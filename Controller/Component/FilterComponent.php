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
App::uses('CakeSession', 'Model/Datasource');

class FilterComponent extends Component
{
    public $key = null;
    public $fields = array();
    public $defaults = array();
    public $datetime = 'created';
    public $modelClass;
    
    private $model;
    private $controller;
    private $changed;
    
    public function __construct(ComponentCollection $collection, $settings=array())
    {
        // Component::__construct will automatically set datetime because it's a public property.
        parent::__construct($collection, $settings);
    }
    
	public function initialize(Controller $controller)
	{
		parent::initialize($controller);
        
		$this->controller = $controller;
        if ($this->modelClass === null)
            $this->modelClass = $controller->modelClass;

        $fields = $this->fields;
        $fieldDefaults = array('comparison' => '=');
        if ($this->modelClass !== false)
        {
            if (isset($controller->{$this->modelClass}))
                $this->model = $controller->{$this->modelClass};

            if (empty($this->key))
                $this->key = $this->model->alias;

            if (empty($fields))
                $fields = array_keys($this->model->schema());
        }
        else
            $this->datetime = false;

        $this->fields = array();
        foreach($fields as $index => $options)
        {
            if (is_int($index))
            {
                $index = $options;
                $options = $fieldDefaults;
            }
            $this->fields[$index] = array_merge($fieldDefaults, $options);
        }
        
        $this->defaults = Set::combine(array_keys($this->fields), '{n}');
        if (!empty($this->datetime))
        {
            $this->defaults += array(
                array('date' => date('Ymd'), 'time' => '00:00:00'),
                array('date' => date('Ymd', strtotime('tomorrow')), 'time' => '00:00:00')
            );

            unset($this->defaults[$this->datetime]);
        }
    }

    public function startup(Controller $controller)
    {
        $changed = false;
        $redirect = false;
        $fields = $this->defaults;

        // Merge anything sent via POST
        if ($this->isFilterPost())
        {
            $redirect = true;
            $changed = true;
            
            if (empty($this->controller->request->data['Filter'][$this->key]))
                $this->controller->request->data['Filter'][$this->key] = array();
            
            $fields = Set::Merge($fields, $this->controller->request->data['Filter'][$this->key]);
        }

        // Merge anything sent via the URL
        if (!empty($this->controller->request->named['Filter'][$this->key]))
        {
            $changed = true;
            $fields = Set::Merge($fields, $this->controller->request->named['Filter'][$this->key]);
        }

        if ($changed == true)
        {
            $this->clear();

            // Precent possible exploits by removing any data not authorized by the fields setting or the table schema.
            $fields = array_intersect_key($fields, $this->defaults);
            if (!empty($this->datetime))
            {
                $fields[0] = array_intersect_key($fields[0], $this->defaults[0]);
                $fields[1] = array_intersect_key($fields[1], $this->defaults[1]);
            }

            $this->sessionFilter($fields);
        }

        $this->changed($changed);
            
        // Redirect
        if ($redirect == true)
            $this->doRedirect();
    }
    
	public function beforeRender(Controller $controller)
	{
		parent::beforeRender($controller);
        $this->controller->request->data['Filter'][$this->key] = $this->sessionFilter();
	}
    
    // Handles setting a changed flag in the session such that the controller can determine if the filter parameters
    // have changed.
    public function changed($changed=null)
    {
        $sessionKey = 'Filter.Changed.' . $this->key;

        // If $changed is true, we're in the fake 'filter' controller action.  Set the changed flag to 1.
        if ($changed === true)
            CakeSession::write($sessionKey, 1);
        else if ($changed === false && CakeSession::check($sessionKey))
        {
            // If changed is false, either the parameters haven't changed or we just finished the redirect after
            // changing.
            $value = CakeSession::read($sessionKey);
            if ($value > 0)
            {
                // 0 means no change since the last page load.
                // 1 will mean we just updated the session with the new parameters.
                // 2 will mean we've finished the redirect after setting the new parameters.
                if (++$value >= 3)
                    $value = 0;

                CakeSession::write($sessionKey, $value);
            }
        }

        return (CakeSession::check($sessionKey) && CakeSession::read($sessionKey) > 0);
    }
    
    public function isFilterPost()
    {
        $action = $this->controller->request->params['action'];
		if (($pos = strpos($action, '_')) !== false)
			$action = substr($action, $pos + 1);
        
        return ($action == 'filter' && $this->controller->request->is('post')/* && 
                !empty($this->controller->request->data['Filter'][$this->key])*/);
    }
    
    public function write($key, $value)
    {
        $sessionKey = 'Filter.' . $this->key . '.' . $key;
        return CakeSession::write($sessionKey, $value);
    }
    
    public function read($key=null)
    {
        $sessionKey = 'Filter.' . $this->key;
        if ($key !== null)
            $sessionKey .= '.' . $key;
        return CakeSession::read($sessionKey);
    }
    
    public function check($key=null)
    {
        $sessionKey = 'Filter.' . $this->key;
        if ($key !== null)
            $sessionKey .= '.' . $key;
        return CakeSession::check($sessionKey);
    }
    
    public function clear($key=null)
    {
        $sessionKey = 'Filter.' . $this->key;
        if ($key !== null)
            $sessionKey .= '.' . $key;
        CakeSession::delete($sessionKey);
    }

    public function filter($options=array())
    {
        $options = array_merge(array('conditions' => array(), 'paginate' => false), $options);

        $paginate = $options['paginate'];
        unset($options['paginate']);

        // Conditions passed in take priority over calculated conditions
        $options['conditions'] = Set::merge($this->conditions(), $options['conditions']);

        if ($paginate === true)
        {            
            $backup = $this->controller->paginate;

            $this->controller->paginate = Set::merge($this->controller->paginate, $options);
            $results = $this->controller->paginate($this->model->alias);

            $this->controller->paginate = $backup;
            return $results;
        }

        return $this->model->find('all', $options);
    }

    public function description()
    {
        $description = array();
        $filter = $this->sessionFilter();

        if (!empty($this->datetime))
        {
            if (!empty($filter[0]['date']) && !empty($filter[1]['date']))
                $description[] = sprintf('Date between "%s" and "%s"', 
                        $filter[0]['date'] . ' ' . $filter[0]['time'], 
                        $filter[1]['date'] . ' ' . $filter[1]['time']
                    );
            else if (!empty($filter[0]['date']))
                $description[] = sprintf('Date after "%s"', $filter[0]['date'] . ' ' . $filter[0]['time']);
            else if (!empty($filter[1]['date']))
                $description[] = sprintf('Date before "%s"', $filter[0]['date'] . ' ' . $filter[0]['time']);
        }

        foreach($filter as $key => $value)
        {
            if (empty($value) || is_numeric($key))
                continue;

            $description[] = sprintf('%s = "%s"', $key, $value);
        }
    
        
        return implode(', ', $description);
    }
    
    public function conditions()
    {
        $results = array();
        $filter = $this->sessionFilter();

        if ($this->modelClass !== false)
        {
            if (!empty($this->datetime))
            {
                if (!empty($filter[0]['date']) && !empty($filter[1]['date']))
                {
                    $results[$this->model->alias . '.' . $this->datetime . ' between ? and ?'] = array(
                        $filter[0]['date'] . ' ' . $filter[0]['time'], 
                        $filter[1]['date'] . ' ' . $filter[1]['time']
                    );
                }
                else if (!empty($filter[0]['date']))
                {
                    $results[$this->model->alias . '.' . $this->datetime . ' >='] =
                        $filter[0]['date'] . ' ' . $filter[0]['time'];
                }
                else if (!empty($filter[1]['date']))
                {
                    $results[$this->model->alias . '.' . $this->datetime . ' <='] = 
                        $filter[1]['date'] . ' ' . $filter[1]['time'];
                }
            }
            
            $schema = $this->model->schema();
            foreach($filter as $key => $value)
            {
                if (is_numeric($key) || empty($value))
                    continue;

                $comparison = $this->fields[$key]['comparison'];
                switch(strtolower($comparison))
                {
                case '=':
                    $results[$this->model->alias . '.' . $key] = $value;
                    break;

                case 'like':
                    $value = self::fromMask($value);

                default:
                    $results[$this->model->alias . '.' . $key . ' ' . $comparison] = $value;
                    break;
                }
            }
        }

        if (method_exists($this->controller, '_adjustFilter'))
            $results = $this->controller->_adjustFilter($this->key, $this->model, $filter, $results);

        return $results;
    }

    public static function fromMask($value)
    {
        return '%' . str_replace(
            array('%', '_', '*', '?'),
            array('[%]', '[_]', '%', '_'),
            $value) . '%';
    }
    
    private function sessionFilter($filter=null)
    {
        $sessionKey = 'Filter.' . $this->key;
        if ($filter === null)
        {
            $filter = CakeSession::read($sessionKey);
            if (empty($filter))
                $filter = $this->defaults;

            return $filter;
        }
        
        return CakeSession::write($sessionKey, $filter);
    }
	
    private function doRedirect()
    {
        $url = $this->controller->request->referer();

        // CakePHP's parse function can't handle the http://... in front of a URL.  This removes it, assuming the base
        // is the same as our current host (which it should be for this component).
        if (preg_match('/^[a-z\-]+:\/\//', $url))
        {
            $base = Router::url('/', true);
            $url = substr($url, strlen($base) - 1);
		}

        $url = Router::normalize($url);
        $url = Router::parse($url);

        // The parse command separates the named parameters into the 'named' index.  Merge them back into the root 
        // array.
        $url = array_merge($url, $url['named']);
        unset($url['named']);

        // The parse command places index-based parameters in the 'pass' index.  Merge them back into the root array.
        $url = array_merge($url, $url['pass']);
        unset($url['pass']);

        // Remove the page number from the filter so that we
        // doin't try jumping to a page we don't have.
        unset($url['page']);
        
        // Do the redirect
        return $this->controller->redirect($url, null, true);
    }
}
