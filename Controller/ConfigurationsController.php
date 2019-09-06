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
App::uses('Folder', 'Utility');
App::uses('ClipsConfiguration', 'Lib');

class ConfigurationsController extends AppController
{
    public $uses = array('Configuration');
    private static $passwordFields = array(
        'clipsPassword' => 'clipsConfirmPassword', 
        'connectCicPassword' => 'connectCicConfirmPassword', 
        //'authLdapBindPassword' => 'authLdapConfirmBindPassword'
    );
        
    public function config()
    {
        $idle = Configure::read('Clips.agency.idlePeriod');
        $audio = Configure::read('Clips.agency.audio');

        // Check if the new confirmation alert has been set. Otherwise default to General tone.
        if (!isset($audio['hitConfirmation'])) {
            $hitConfBackup = array('hitConfirmation' => $audio['generalResponse']);
            $audio += $hitConfBackup;
        }

        // Convert the configuration data to something more useful to the view.
        $audio = array(
            'interval' => $audio['interval'],
            'file' => array(
                'general' => $audio['generalResponse'],
                'normal' => $audio['normalResponse'],
                'hit' => $audio['hitResponse'],
                'confirmation' => $audio['hitConfirmation']
            )
        );

        // Revert any non-existing files to the first wave found.
        $files = $this->audioFiles();
        foreach($audio['file'] as $key => $file)
            if (!empty($file) && !in_array($file, $files))
                $audio['file'][$key] = $files[0];

        $this->set(compact('idle', 'audio'));
    }

    public function admin_edit()
    {
        if ($this->request->is('post'))
        {
            $data = $this->request->data[$this->Configuration->alias];
            $data['agencyId'] = $this->CurrentAgency->agencyId;
            
            foreach($data as $key => $value)
                $data[$key] = trim($value);

            $this->Configuration->set($data);
            if (!$this->Configuration->validates())
            {
                $this->setAudioVariables();
                $this->Session->setFlash('Invalid configuration data');
                return;
            }
            
            $old = ClipsConfiguration::toModel(ClipsConfiguration::read(
                $this->CurrentAgency->agencyId));
            
            $result = $this->Configuration->save();
            $this->Session->setFlash(($result == true) 
                    ? 'Configuration changes saved'
                    : 'Failed to save configuration changes');
            
            $this->auditChanges($old);
            
            return $this->redirect(array('action' => 'edit'));
        }
        
        $this->setAudioVariables();

        $this->request->data = $this->Configuration->find('all', array(
            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId)
        ));
    }

    public function system_edit()
    {
        // This field is optional on the configuration screen.
        $this->Configuration->validate['clipsPassword']['allowEmpty'] = true;
        $this->Configuration->validate['connectCicPassword']['allowEmpty'] = true;

        if ($this->request->is('post'))
        {
            $data = $this->request->data[$this->Configuration->alias];

            foreach(self::$passwordFields as $field => $confirm)
            {
                if (empty($data[$field]) && empty($data[$confirm]))
                {
                    unset($data[$field]);
                    unset($data[$confirm]);
                }
            }

            foreach($data as $key => $value)
                $data[$key] = trim($value);

            $this->Configuration->set($data);
            if (!$this->Configuration->validates())
            {
                $this->setAudioVariables();
                $this->Session->setFlash('Invalid configuration data');
                return;
            }

            $old = ClipsConfiguration::toModel(ClipsConfiguration::read());
            
            $result = $this->Configuration->save();
            $this->Session->setFlash(($result == true) 
                    ? 'Configuration changes saved'
                    : 'Failed to save configuration changes');

            $this->auditChanges($old);

            return $this->redirect(array('action' => 'edit'));
        }

        $this->setAudioVariables();

        $data = $this->Configuration->find('all');
        foreach(self::$passwordFields as $field => $confirm)
        {
            unset($data['Configuration'][$field]);
            unset($data['Configuration'][$confirm]);
        }

        $this->request->data = $data;
    }

    private function auditChanges($config)
    {
        $data = $this->request->data[$this->Configuration->alias];

        $passwords = array_keys(self::$passwordFields);
        foreach($data as $field => $value)
        {
            $old = $config[$field];
            if ($value != $old)
            {
                if (in_array($field, $passwords))
                {
                    if (!empty($value))
                        AuditLog::Log(AUDIT_CONFIG_CHANGE_GLOBAL, $field, 'XXXXXX', 'XXXXXX');
    
                    continue;
                }

                AuditLog::Log(AUDIT_CONFIG_CHANGE_GLOBAL, $field, $old, $value);
            }
        }
    }
    
    private function audioFiles()
    {
        $folder = new Folder(WWW_ROOT . DS . 'audio');
        return $folder->find('.*\.mp3', true);
    }
    
    private function setAudioVariables()
    {
        // Find a list of permitted audio files.
        $audioFiles = $this->audioFiles();
        $audioFiles = array("" => '< None >') + array_combine($audioFiles, $audioFiles);
        $this->set('soundFiles', $audioFiles);
    }
}
