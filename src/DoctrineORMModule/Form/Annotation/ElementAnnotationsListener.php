<?php
namespace DoctrineORMModule\Form\Annotation;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use DoctrineModule\Form\Element;
use ReflectionClass;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class ElementAnnotationsListener implements ListenerAggregateInterface
{

    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $objectManager;

    /**
     * @param ObjectManager $objectManager
     */
    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Detach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if (false !== $events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Attach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('configureElement', array($this, 'handleAttributesAnnotation'));
        $this->listeners[] = $events->attach('configureElement', array($this, 'handleFilterAnnotation'));
        $this->listeners[] = $events->attach('configureElement', array($this, 'handleRequiredAnnotation'));
        $this->listeners[] = $events->attach('configureElement', array($this, 'handleTypeAnnotation'));
        $this->listeners[] = $events->attach('configureElement', array($this, 'handleValidatorAnnotation'));

        $this->listeners[] = $events->attach('configureElement', array($this, 'handleToManyAnnotation'));

        $this->listeners[] = $events->attach('checkForExclude', array($this, 'handleExcludeAnnotation'));
    }

    /**
     * Handle ToOne relationships.
     *
     * @param Event $e
     * @return void
     */
    public function handleToOneAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ManyToOne && !$annotation instanceof OneToOne) {
            return;
        }

        $elementSpec = $e->getParam('elementSpec');
        $elementSpec['spec']['type']    = 'DoctrineORMModule\Form\Element\EntitySelect';
        $elementSpec['spec']['options'] = array(
            'object_manager' => $this->objectManager,
            'target_class'   => $annotation->targetEntity
        );
    }

    /**
     * Handle ToMany relationships.
     *
     * @param Event $e
     * @return void
     */
    public function handleToManyAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ManyToMany && !$annotation instanceof OneToMany) {
            return;
        }

        $elementSpec = $e->getParam('elementSpec');
        $elementSpec['spec']['type']    = 'DoctrineORMModule\Form\Element\EntitySelect';
        $elementSpec['spec']['options'] = array(
            'object_manager' => $this->objectManager,
            'target_class'   => $annotation->targetEntity
        );
        $elementSpec['spec']['attributes']['multiple'] = true;
    }

    /**
     * Handle the Attributes annotation
     *
     * Sets the attributes array of the element specification.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleAttributesAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Column) {
            return;
        }

        $elementSpec = $e->getParam('elementSpec');
        switch ($annotation->type) {
            case 'bool':
            case 'boolean':
                $elementSpec['spec']['attributes']['type'] = 'checkbox';
                break;
            case 'text':
                $elementSpec['spec']['attributes']['type'] = 'textarea';
                break;
        }
    }

    /**
     * Handle the AllowEmpty annotation
     *
     * Sets the allow_empty flag on the input specification array.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return bool
     */
    public function handleExcludeAnnotation(Event $e)
    {
        $annotations = $e->getParam('annotations');
        foreach ($annotations as $annotation) {
            if ($annotation instanceof GeneratedValue) {
                if ($annotation->strategy == 'AUTO') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle the Filter annotation
     *
     * Adds a filter to the filter chain specification for the input.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleFilterAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Column) {
            return;
        }

        $inputSpec = $e->getParam('inputSpec');
        if (!isset($inputSpec['filters'])) {
            $inputSpec['filters'] = array();
        }

        switch ($annotation->type) {
            case 'bool':
            case 'boolean':
                $inputSpec['filters'][] = array('name' => 'Boolean');
                break;
            case 'bigint':
            case 'integer':
            case 'smallint':
                $inputSpec['filters'][] = array('name' => 'Int');
                break;
            case 'datetime':
            case 'datetimetz':
            case 'date':
            case 'time':
            case 'string':
            case 'text':
                $inputSpec['filters'][] = array('name' => 'StringTrim');
                break;
        }
    }

    /**
     * Handle the Required annotation
     *
     * Sets the required flag on the input based on the annotation value.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleRequiredAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Column) {
            return;
        }

        $inputSpec = $e->getParam('inputSpec');
        $inputSpec['required'] = (bool) !$annotation->nullable;
    }

    /**
     * Handle the Type annotation
     *
     * Sets the element class type to use in the element specification.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleTypeAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Column) {
            return;
        }

        $type = $annotation->type;
        switch ($type) {
            case 'bool':
            case 'boolean':
                $type = 'Zend\Form\Element\Checkbox';
                break;
            case 'date':
                $type = 'Zend\Form\Element\Date';
                break;
            case 'datetime':
                $type = 'Zend\Form\Element\DateTime';
                break;
            default:
                $type = 'Zend\Form\Element';
                break;
        }

        $elementSpec = $e->getParam('elementSpec');
        $elementSpec['spec']['type'] = $type;
    }

    /**
     * Handle the Validator annotation
     *
     * Adds a validator to the validator chain of the input specification.
     *
     * @param  \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleValidatorAnnotation(Event $e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Column) {
            return;
        }

        $inputSpec = $e->getParam('inputSpec');
        if (!isset($inputSpec['validators'])) {
            $inputSpec['validators'] = array();
        }

        switch ($annotation->type) {
            case 'bool':
            case 'boolean':
                $inputSpec['validators'][] = array(
                    'name' => 'InArray',
                    'options' => array('haystack' => array('0', '1'))
                );
                break;
            case 'float':
                $inputSpec['validators'][] = array('name' => 'Float');
                break;
            case 'bigint':
            case 'integer':
            case 'smallint':
                $inputSpec['validators'][] = array('name' => 'Int');
                break;
            case 'string':
                if ($annotation->length) {
                    $inputSpec['validators'][] = array(
                        'name' => 'StringLength',
                        'options' => array('max' => $annotation->length)
                    );
                }
                break;
        }
    }
}
