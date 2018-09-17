<?php
namespace TNW\Salesforce\Synchronize\Transport\Calls\Upsert;

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
     * Data constructor.
     *
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * @param object $object
     * @param array $data
     */
    public function offsetSet($object, $data = null)
    {
        $index = \count($this->info);
        parent::offsetSet($object, $index);
        $this->info[$index] = $data;
    }

    /**
     * @param object $object
     * @return array
     */
    public function &offsetGet($object)
    {
        if(!$this->contains($object)) {
            $this->offsetSet($object, []);
        }

        return $this->info[parent::offsetGet($object)];
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->info[parent::getInfo()];
    }

    /**
     * @param array $data
     */
    public function setInfo($data)
    {
        $index = \count($this->info);
        parent::setInfo($index);
        $this->info[$index] = $data;
    }
}