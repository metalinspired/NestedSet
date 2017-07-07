<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;

/**
 * Class Factory
 *
 * @property Find             $find
 * @property Manipulate       $manipulate
 * @property HybridFind       $hybridFind
 * @property HybridManipulate $hybridManipulate
 */
class Factory
{
    /**
     * Configuration object used for creating classes
     *
     * @var Config
     */
    protected $config;

    /**
     * @var Find
     */
    protected $find;

    /**
     * @var Manipulate
     */
    protected $manipulate;

    /**
     * @var HybridFind
     */
    protected $hybridFind;

    /**
     * @var HybridManipulate
     */
    protected $hybridManipulate;

    /**
     * Factory constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = clone $config;
    }

    /**
     * Variable overloading
     *
     * @param  string $name
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'find':
                return $this->find();
            case 'manipulate':
                return $this->manipulate();
            case 'hybridfind':
                return $this->hybridFind();
            case 'hybridmanipulate':
                return $this->hybridManipulate();
            default:
                throw new InvalidArgumentException('Not a valid magic property for this object');
        }
    }

    /**
     * Loads new configuration
     *
     * @param Config $config
     */
    public function loadConfig(Config $config)
    {
        $this->config = clone $config;

        foreach (get_object_vars($this) as $object) {
            if ($object && method_exists($object, 'loadConfig')) {
                $object->loadConfig($config);
            }
        }
    }

    /**
     * Get Find class
     *
     * @return Find
     */
    public function find()
    {
        if (! $this->find) {
            $this->find = new Find($this->config);
        }

        return $this->find;
    }

    /**
     * Get Manipulate class
     *
     * @return Manipulate
     */
    public function manipulate()
    {
        if (! $this->manipulate) {
            $this->manipulate = new Manipulate($this->config);
        }

        return $this->manipulate;
    }

    /**
     * Get HybridFind class
     *
     * @return HybridFind
     */
    public function hybridFind()
    {
        if (! $this->hybridFind) {
            $this->hybridFind = new HybridFind($this->config);
        }

        return $this->hybridFind;
    }

    /**
     * Get HybridManipulate class
     *
     * @return HybridManipulate
     */
    public function hybridManipulate()
    {
        if (! $this->hybridManipulate) {
            $this->hybridManipulate = new HybridManipulate($this->config);
        }

        return $this->hybridManipulate;
    }
}