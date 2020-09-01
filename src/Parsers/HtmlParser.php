<?php
namespace PHPSpider\Parsers;

use DOMDocument;
use DOMXPath;
use PHPSpider\Parsers\Adapters\CssSelector;

class HtmlParser
{
    private $_id;

    static protected $_xpath = [];
    static protected $_document = [];

    public $_contextnode = null;

    public static function load($html)
    {
        if (is_file($html) || filter_var($html,FILTER_VALIDATE_URL)) {
            $html = file_get_contents($html);
        }

        $html = preg_replace("/<script[\s\S]*?<\/script>/i",'',$html); //过滤掉影响HTML的JS代码
        $document = new DOMDocument();;
        $flag = libxml_use_internal_errors(true);
        $document->loadHTML(html_entity_decode($html));
        libxml_use_internal_errors($flag);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace("php", "http://php.net/xpath");
        $xpath->registerPHPFunctions();

        $id = uniqid();
        self::$_document[$id] = $document;
        self::$_xpath[$id] = $xpath;

        return new self($id);
    }

    protected function __construct($id, $contextnode = null)
    {
        $this->_id = $id;
        $this->_contextnode = $contextnode;
    }

    public function findText($expression, $contextnode = null)
    {
        $expression = CssSelector::translate($expression);

        $text = [];

        $nodeList = $this->getXpath()->query($expression,$contextnode);

        foreach($nodeList as $node)
        {
            $text[] = $node->textContent;
        }

        return $text;
    }

    public function find($expression, $contextnode = null)
    {
        $expression = CssSelector::translate($expression);

        return $this->xpath($expression,$contextnode);
    }

    public function xpath($expression, $contextnode = null)
    {
        if (empty($contextnode) && !empty($this->_contextnode)) {
            $contextnode = $this->_contextnode;
        }

        $nodeList = $this->getXpath()->query($expression,$contextnode);

        $result = [];

        foreach ($nodeList as $node) {
            $result[] = new self($this->_id,$node);
        }
        return $result;
    }

    public function remove($expression,$contextnode=null)
    {
        if (empty($contextnode) && !empty($this->_contextnode)) {
            $contextnode = &$this->_contextnode;
        } else if(empty($contextnode)) {
            $contextnode = $this->getDocument();
        }

        $expression = CssSelector::translate($expression);

        $nodeList = $this->getXpath()->query($expression,$contextnode);

        foreach ($nodeList as $node) {
            $node->parentNode->removeChild($node);
        }

        return $this;
    }

    public function html($contextnode = null)
    {
        $contextnode = !empty($contextnode)?$contextnode:$this->_contextnode;

        return $this->getDocument()->saveHtml($contextnode);
    }

    public function text($contextnode = null)
    {
        $contextnode = !empty($contextnode)?:$this->_contextnode;

        return $contextnode->textContent;
    }

    public function getXpath()
    {
        return self::$_xpath[$this->_id];
    }

    public function getDocument()
    {
        return self::$_document[$this->_id];
    }
}