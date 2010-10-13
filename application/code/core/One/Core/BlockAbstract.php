<?php


abstract class One_Core_BlockAbstract
    extends One_Core_Object
    implements Zend_View_Interface
{
    protected $_name = '';

    protected $_childNodes = array();

    protected $_childIndex = array();

    protected $_basePath = '';

    protected $_scriptPath = '';

    protected $_layout = null;

    private $_loaders = array();
    private $_helper = array();
    private $_filter = array();
    private $_path = array();

    protected $_loaderTypes = array('helper', 'filter');

    public function __construct($module, $options, One_Core_Model_Application $application, One_Core_Model_Layout $layout = null)
    {
        $this->_layout = $layout;

        parent::__construct($module, $options, $application);
    }

    protected function _construct($options)
    {
        $config = One::app()->getOption('frontoffice');
        $basePath = implode(One::DS, array(APPLICATION_PATH, 'design', 'frontoffice',
            $config['layout']['design'], $config['layout']['template']));

        $this->setBasePath($basePath)
            ->setScriptPath($basePath . One::DS . 'template')
        ;

        if (isset($options['name'])) {
            $this->setName($options['name']);
            unset($options['name']);
        } else {
            $this->setName(uniqid('block_'));
        }

        if (isset($options['block'])) {
            if (!is_array($options['block']) || !is_int(key($options['block']))) {
                $options['block'] = array($options['block']);
            }
            foreach ($options['block'] as $block) {
                $this->_buildChildNode($block);
            }
            unset($options['block']);
        }

        if (isset($options['action'])) {
            if (!is_array($options['action']) || !is_int(key($options['action']))) {
                $options['action'] = array($options['action']);
            }
            foreach ($options['action'] as $action) {
                $this->_executeAction($action);
            }
            unset($options['action']);
        }

        return parent::_construct($options);
    }

    protected function _buildChildNode($node)
    {
        if (!isset($node['type'])) {
            return $this;
        }

        $childBlock = One::app()
            ->getBlock($node['type'], $node, $this->_layout)
            ->setBasePath($this->getBasePath())
            ->setScriptPath($this->getScriptPath())
        ;

        if (isset($node['name'])) {
            $name = $node['name'];
        } else {
            $name = uniqid('block_');
        }
        $this->appendChild($name, $childBlock);

        $this->_layout->registerBlock($name, $childBlock);

        return $this;
    }

    protected function _executeAction($action)
    {
        $reflectionObject = new ReflectionObject($this);
        if ($reflectionObject->hasMethod($action['method'])) {
            $reflectionMethod = $reflectionObject->getMethod($action['method']);

            $callParams = array_pad(array(), $reflectionMethod->getNumberOfParameters(), null);
            $parameters = array();
            foreach ($reflectionMethod->getParameters() as $reflectionParam) {
                $parameters[$reflectionParam->getName()] = $reflectionParam;
                if ($reflectionParam->isOptional()) {
                    $callParams[$reflectionParam->getPosition()] = $reflectionParam->getDefaultValue();
                }
            }
            foreach ($action['params'] as $paramName => $paramValue) {
                if (!isset($parameters[$paramName])) {
                    continue;
                }

                if (isset($paramValue)) {
                    $callParams[$parameters[$paramName]->getPosition()] = $paramValue;
                } else {
                    $callParams[$parameters[$paramName]->getPosition()] = null;
                }
            }

            $reflectionMethod->invokeArgs($this, $callParams);
        } else {
            $this->__call($action['method'], array_values($action['params']));
        }

        return $this;
    }

    public function getLayout()
    {
        return $this->_layout;
    }

    public function setLayout(One_Core_Model_Layout $layout)
    {
        $this->_layout = $layout;

        return $this;
    }

    public function getChildNode($childName)
    {
        if (isset($this->_childNodes[$childName])) {
            return $this->_childNodes[$childName];
        }
        return null;
    }

    public function getAllChildNodes()
    {
        return $this->_childNodes;
    }

    public function renderChild($childName, $templateName = null)
    {
        return $this->getChildNode($childName)->render($templateName);
    }

    public function appendChild($childName, One_Core_BlockAbstract $childNode)
    {
        $this->_childNodes[(string) $childName] = $childNode;
        $this->_childIndex[] = $childName;

        return $this;
    }

    public function prependChild($childName, One_Core_BlockAbstract $childNode)
    {
        $this->_childNodes[(string) $childName] = $childNode;
        array_unshift($this->_childIndex, $childName);

        return $this;
    }

    public function setName($name)
    {
        $this->_name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function render($name)
    {
        $obLevel = ob_get_level();

        $this->_beforeRender($name);
        $content = $this->_render();
        $this->_afterRender($name, $content);

        while (ob_get_level() < $obLevel) {
            ob_end_clean();
        }

        return $content;
    }

    protected function _beforeRender()
    {
        return $this;
    }

    abstract protected function _render();

    protected function _afterRender()
    {
        return $this;
    }

    public function getEngine()
    {
    }

    public function setScriptPath($path)
    {
        $this->_scriptPath = $path;

        return $this;
    }

    public function getScriptPath()
    {
        return $this->_scriptPath;
    }

    public function getScriptPaths()
    {
        return $this->_scriptPath;
    }

    public function setBasePath($path, $classPrefix = 'Zend_View')
    {
        $this->_basePath = $path;

        return $this;
    }

    public function getBasePath()
    {
        return $this->_basePath;
    }

    public function addBasePath($path, $classPrefix = 'Zend_View')
    {
        $this->setBasePath($path, $classPrefix);

        return $this;
    }

    public function assign($spec, $value = null)
    {
    }

    public function clearVars()
    {
    }
}