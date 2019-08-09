<?php
namespace TNW\Salesforce\Synchronize\Transport\Calls\Upsert\Transport;

/**
 * Upsert Transport Output
 */
class Output extends \SplObjectStorage
{
    /**
     * @var array
     */
    private $info = [];

    /**
     * @var string
     */
    protected $type;

    /**
     * @var
     */
    protected $unit;

    /**
     * Data constructor.
     *
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * @param mixed $unit
     */
    public function setUnit($unit)
    {
        $this->unit = $unit;
    }

    /**
     * Type
     *
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * Offset Set
     *
     * @param object $object
     * @param array $data
     */
    public function offsetSet($object, $data = null)
    {
        $index = \spl_object_hash($object);
        parent::offsetSet($object, $index);
        $this->info[$index] = $data;
    }

    /**
     * Offset Get
     *
     * @param object $object
     * @return array
     */
    public function &offsetGet($object)
    {
        if (!$this->contains($object)) {
            $this->offsetSet($object, []);
        }

        return $this->info[parent::offsetGet($object)];
    }

    /**
     * Get Info
     *
     * @return array
     */
    public function getInfo()
    {
        return $this->info[parent::getInfo()];
    }

    /**
     * Set Info
     *
     * @param array $data
     */
    public function setInfo($data)
    {
        $index = \spl_object_hash($this->current());
        parent::setInfo($index);
        $this->info[$index] = $data;
    }

    /**
     * Offset Unset
     *
     * @param object $object
     */
    public function offsetUnset($object)
    {
        unset($this->info[parent::offsetGet($object)]);
        parent::offsetUnset($object);
    }
}
