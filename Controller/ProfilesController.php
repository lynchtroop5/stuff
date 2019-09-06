<?php
App::uses('AppController', 'Controller');

class ProfilesController extends AppController
{
    public $components = array('Paginator');
    public $helpers = array('Paginator');
    
    public $paginate = array(
            'Profile' => array(
            'fields' => array('id', 'request', 'ip', 'computer', 'data', 'created'),
            'limit' => 100,
            'order' => array(
                'Profile.created asc'
            )
        )
    );

    public function index()
    {
        $this->Paginator->settings = $this->paginate;
        $this->Profile->setFileSource(TMP . 'profiler.db');

        $profiles = $this->Paginator->paginate('Profile');
        foreach($profiles as $index => $profile)
        {
            $data = unserialize($profile['Profile']['data']);
            $data = $data['timers'];
            
            $profiles[$index]['Profile']['duration'] = round($data['Request']['duration'], 3);
            unset($profile['Profile']['data']);
        }
        
        if ($this->request['named']['sort'] == 'duration') {
            $direction = ((!empty($this->request['named']['direction']))
                ? $this->request['named']['direction']
                : 'asc');
            if ($direction == 'asc')
                usort($profiles, function($a, $b) {
                    return ($a['Profile']['duration'] < $b['Profile']['duration']);
                });
            else
                usort($profiles, function($a, $b) {
                    return ($a['Profile']['duration'] > $b['Profile']['duration']);
                });
        }
        
        $this->set('profiles', $profiles);
    }

    public function view($id=null)
    {
        $this->Profile->setFileSource(TMP . 'profiler.db');
        $profile = $this->Profile->find('first', array(
            'conditions' => array(
                $this->Profile->primaryKey => $id
            )
        ));
        
        $profile['Profile']['data'] = unserialize($profile['Profile']['data']);
        $this->set('profile', $profile);
    }
}
