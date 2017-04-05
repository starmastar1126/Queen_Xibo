<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Xibo\Helper\ObjectVars;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class EntityTrait
 * used by all entities
 * @package Xibo\Entity
 */
trait EntityTrait
{
    private $hash = null;
    private $loaded = false;
    private $permissionsClass = null;
    private $canChangeOwner = true;

    public $buttons = [];
    private $jsonExclude = ['buttons', 'jsonExclude', 'originalValues'];

    /** @var array Original values hydrated */
    protected $originalValues = [];

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @return $this
     */
    protected function setCommonDependencies($store, $log)
    {
        $this->store = $store;
        $this->log = $log;
        return $this;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->log;
    }

    /**
     * Hydrate an entity with properties
     *
     * @param array $properties
     * @param array $options
     *
     * @return self
     */
    public function hydrate(array $properties, $options = [])
    {
        $intProperties = (array_key_exists('intProperties', $options)) ? $options['intProperties'] : [];
        $stringProperties = (array_key_exists('stringProperties', $options)) ? $options['stringProperties'] : [];
        $htmlStringProperties = (array_key_exists('htmlStringProperties', $options)) ? $options['htmlStringProperties'] : [];

        foreach ($properties as $prop => $val) {
            if (property_exists($this, $prop)) {

                if ((stripos(strrev($prop), 'dI') === 0 || in_array($prop, $intProperties)) && !in_array($prop, $stringProperties))
                    $val = intval($val);
                else if (in_array($prop, $stringProperties))
                    $val = filter_var($val, FILTER_SANITIZE_STRING);
                else if (in_array($prop, $htmlStringProperties))
                    $val = htmlentities($val);

                $this->{$prop} =  $val;
                $this->originalValues[$prop] = $val;
            }
        }

        return $this;
    }

    /**
     * Reset originals to current values
     */
    public function setOriginals()
    {
        foreach ($this->jsonSerialize() as $key => $value) {
            $this->originalValues[$key] = $value;
        }
    }

    /**
     * Get the original value of a property
     * @param string $property
     * @return null|mixed
     */
    public function getOriginalValue($property)
    {
        return (isset($this->originalValues[$property])) ? $this->originalValues[$property] : null;
    }

    /**
     * Has the provided property been changed from its original value
     * @param string $property
     * @return bool
     */
    public function hasPropertyChanged($property)
    {
        if (!property_exists($this, $property))
            return true;

        return $this->getOriginalValue($property) != $this->{$property};
    }

    /**
     * @param $property
     * @return bool
     */
    public function propertyOriginallyExisted($property)
    {
        return array_key_exists($property, $this->originalValues);
    }

    /**
     * Get all changed properties for this entity
     */
    public function getChangedProperties()
    {
        $changedProperties = [];

        foreach ($this->jsonSerialize() as $key => $value) {
            if (!is_array($value) && !is_object($value) && $this->propertyOriginallyExisted($key) && $this->hasPropertyChanged($key)) {
                $changedProperties[$key] = $this->getOriginalValue($key) . ' > ' . $value;
            }
        }

        return $changedProperties;
    }

    /**
     * Json Serialize
     * @return array
     */
    public function jsonSerialize()
    {
        $exclude = $this->jsonExclude;

        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($properties as $key => $value) {
            if (!in_array($key, $exclude)) {
                $json[$key] = $value;
            }
        }
        return $json;
    }

    /**
     * To Array
     * @return array
     */
    public function toArray()
    {
        return $this->jsonSerialize();
    }

    /**
     * Add a property to the excluded list
     * @param string $property
     */
    public function excludeProperty($property)
    {
        $this->jsonExclude[] = $property;
    }

    /**
     * Remove a property from the excluded list
     * @param string $property
     */
    public function includeProperty($property)
    {
        $this->jsonExclude = array_diff($this->jsonExclude, [$property]);
    }

    /**
     * Get the Permissions Class
     * @return string
     */
    public function permissionsClass()
    {
        return ($this->permissionsClass == null) ? get_class($this) : $this->permissionsClass;
    }

    /**
     * Set the Permissions Class
     * @param string $class
     */
    protected function setPermissionsClass($class)
    {
        $this->permissionsClass = $class;
    }

    /**
     * Can the owner change?
     * @return bool
     */
    public function canChangeOwner()
    {
        return $this->canChangeOwner && method_exists($this, 'setOwner');
    }

    /**
     * @param bool $bool Can the owner be changed?
     */
    protected function setCanChangeOwner($bool)
    {
        $this->canChangeOwner = $bool;
    }

    /**
     * @param $entityId
     * @param $message
     * @param array[Optional] $changedProperties
     */
    protected function audit($entityId, $message, $changedProperties = null)
    {
        if ($changedProperties === null) {
            // No properties provided, so we should work them out
            // If we have originals, then get changed, otherwise get the current object state
            $changedProperties = (count($this->originalValues) <= 0) ? $this->toArray() : $this->getChangedProperties();
        }

        $class = substr(get_class($this), strrpos(get_class($this), '\\') + 1);

        if (count($changedProperties) > 0)
            $this->getLog()->audit($class, $entityId, $message, $changedProperties);
    }
}