<?php
/*
 * LukyLuke - http://www.ranta.ch
 * Copyright (C) 2017 LukyLuke - Lukas Zurschmiede - https://github.com/LukyLuke
 * (DataSetRempote.php)
 */

namespace Xibo\Entity;

/**
 * Class DataSetRemote
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataSetRemote extends DataSet
{
    /**
     * @SWG\Property(description="Method to fetch the Data, can be GET or POST")
     * @var string
     */
    public $method;

    /**
     * @SWG\Property(description="URI to call to fetch Data from. Replacements are {{DATE}}, {{TIME}} and, in case this is a sequencial used DataSet, {{COL.NAME}} where NAME is a ColumnName from the underlying DataSet.")
     * @var string
     */
    public $uri;

    /**
     * @SWG\Property(description="Data to send as POST-Data to the remote host with the same Replacements as in the URI.")
     * @var string
     */
    public $postData;

    /**
     * @SWG\Property(description="Authentication method, can be none, digest, basic")
     * @var string
     */
    public $authentication;

    /**
     * @SWG\Property(description="Username to authenticate with")
     * @var string
     */
    public $username;

    /**
     * @SWG\Property(description="Corresponding password")
     * @var string
     */
    public $password;

    /**
     * @SWG\Property(description="Time in seconds this DataSet should fetch new Datas from the remote host")
     * @var int
     */
    public $refreshRate;

    /**
     * @SWG\Property(description="Time in seconds when this Dataset should be cleared. If here is a lower value than in RefreshRate it will be cleared when the data is refreshed")
     * @var int
     */
    public $clearRate;

    /**
     * @SWG\Property(description="DataSetID of the DataSet which should be fetched and present before the Data from this DataSet are fetched")
     * @var int
     */
    public $runsAfter;

    /**
     * @SWG\Property(description="Last Synchronisation Timestamp")
     * @var int
     */
    public $lastSync = 0;

    /**
     * @SWG\Property(description="Root-Element form JSON where the data are stored in")
     * @var String
     */
    public $dataRoot;

    /**
     * @SWG\Property(description="Optional function to use for summarize or count unique fields in a remote request")
     * @var String
     */
    public $summarize;

    /**
     * @SWG\Property(description="JSON-Element below the Root-Element on which the consolidation should be applied on")
     * @var String
     */
    public $summarizeField;


    /**
     * Returns an Array to be used with the function `curl_setopt_array($curl, $params);`
     * @param array $values ColumnValues to use on URI and PostData for the {{COL.NAME}} parts
     * @return array
     */
    public function getCurlParams(array $values = []) {
        $params = [
            CURLOPT_URL => $this->relpaceParams($this->uri, $values),
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => ($this->authentication == 'basic') ? CURLAUTH_BASIC : (($this->authentication == 'digest') ? CURLAUTH_DIGEST : 0),
            CURLOPT_USERPWD => ($this->authentication != 'none') ? $this->username . ':' . $this->password : ''
        ];
        
        if ($this->method == 'POST') {
            $params += [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $this->relpaceParams($this->postData, $values)
            ];
        }
        
        return $params;
    }
    
    /**
     * Returns a Timestamp for the next Synchronisation process.
     * @return int Seconds
     */
    public function getNextSyncTime() {
        return $this->lastSync + $this->refreshRate;
    }
    
    /**
     * Returns a Timestamp for the next Clearing process.
     * @return int Seconds
     */
    public function getNextClearTime() {
        return $this->lastSync + $this->clearRate;
    }
    
    /**
     * Returns if there is a consolidation field and method present or not.
     * @return boolean
     */
    public function doConsolidate() {
        return ($this->summarizeField != null) && ($this->summarizeField != '')
            && ($this->summarize != null) && ($this->summarize != '');
    }
    
    /**
     * Returns the last Part of the Fieldname on which the consolidation should be applied on
     * @return String
     */
    public function getConsolidationField() {
        $pos = strrpos($this->summarizeField, '.');
        if ($pos !== false) {
            return substr($this->summarizeField, $pos + 1);
        }
        return $this->summarizeField;
    }
    
    /**
     * Tests if this DataSet contains parameters for getting values on the dependant DataSet
     * @return boolean
     */
    public function containsDependatFieldsInRequest() {
        return strpos($this->postData, '{{COL.') !== false || strpos($this->uri, '{{COL.') !== false;
    }
    
    /**
     * Replaces all URI/PostData parameters
     * @param string String to replace {{DATE}}, {{TIME}} and {{COL.xxx}}
     * @param array $values ColumnValues to use on {{COL.xxx}} parts
     * @return string
     */
    private function relpaceParams($string = '', array $values = []) {
        $string = str_replace('{{DATE}}', date('Y-m-d'), $string);
        $string = str_replace('%7B%7BDATE%7D%7D', date('Y-m-d'), $string);
        $string = str_replace('{{TIME}}', date('H:m:s'), $string);
        $string = str_replace('%7B%7BTIME%7D%7D', date('H:m:s'), $string);
        
        foreach ($values as $k => $v) {
            $string = str_replace('{{COL.' . $k . '}}', urlencode($v), $string);
            $string = str_replace('%7B%7BCOL.' . $k . '%7D%7D', urlencode($v), $string);
        }
        
        return $string;
    }
    
    /**
     * Validate
     */
    public function validate() {
        parent::validate();
    }
    
    /**
     * Load all known information
     */
    public function load() {
        parent::load();
    }
    
    /**
     * Save this DataSet
     * @param array $options
     * @Override
     */
    public function save($options = []) {
        parent::save($options);
        if ($this->exists()) {
            $this->editRemote();
        } else {
            $this->addRemote();
        }
    }
    
    /**
     * Delete DataSet
     */
    public function delete() {
        parent::delete();
        $this->getStore()->update('DELETE FROM `datasetremote` WHERE dataSetId = :dataSetId', ['dataSetId' => $this->dataSetId]);
    }

    /**
     * Checks if there is an entry in `datasetremote`
     */
    private function exists() {
        return $this->getStore()->exists('SELECT DataSetID FROM `datasetremote` WHERE DataSetID = :dataSetId;', ['dataSetId' => $this->dataSetId]);
    }

    /**
     * Add Remote Settings entry
     */
    private function addRemote() {
        $this->getStore()->insert(
          'INSERT INTO `datasetremote` (`DataSetID`, `method`, `uri`, `postData`, `authentication`, `username`, `password`, `refreshRate`, `clearRate`, `runsAfter`, `dataRoot`, `lastSync`, `summarize`, `summarizeField`)
            VALUES (:dataSetId, :method, :uri, :postData, :authentication, :username, :password, :refreshRate, :clearRate, :runsAfter)', [
            'dataSetId' => $this->dataSetId,
            'method' => $this->method,
            'uri' => $this->uri,
            'postData' => $this->postData,
            'authentication' => $this->authentication,
            'username' => $this->username,
            'password' => $this->password,
            'refreshRate' => $this->refreshRate,
            'clearRate' => $this->clearRate,
            'runsAfter' => $this->runsAfter,
            'dataRoot' => $this->dataRoot,
            'summarize' => $this->summarize,
            'summarizeField' => $this->summarizeField,
            'lastSync' => 0
        ]);
    }

    /**
     * Edit Remote Settings Entry
     */
    private function editRemote() {
        $this->getStore()->update(
          'UPDATE datasetremote SET method = :method, uri = :uri, postData = :postData, authentication = :authentication, username = :username, password = :password, refreshRate = :refreshRate, clearRate = :clearRate, runsAfter = :runsAfter, `dataRoot` = :dataRoot, `summarize` = :summarize, `summarizeField` = :summarizeField
            WHERE DataSetID = :dataSetId', [
            'dataSetId' => $this->dataSetId,
            'method' => $this->method,
            'uri' => $this->uri,
            'postData' => $this->postData,
            'authentication' => $this->authentication,
            'username' => $this->username,
            'password' => $this->password,
            'refreshRate' => $this->refreshRate,
            'clearRate' => $this->clearRate,
            'runsAfter' => $this->runsAfter,
            'dataRoot' => $this->dataRoot,
            'summarize' => $this->summarize,
            'summarizeField' => $this->summarizeField
        ]);
    }
}
