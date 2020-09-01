<?php
namespace PHPSpider\Parsers;

use DOMDocument;

class XmlParser
{
    static public function createFromArray($array,$nodename='node',$attr = [],$context = null,$file = null,$dir = null)
    {
        $dom = new DOMDocument('1.0','utf-8');
        $root = $dom->createElement('list');
        $dom->appendChild($root);

        foreach($array as $value) {
            $node = $dom->createElement($nodename);
            $root->appendChild($node);
            $value = json_decode($value,true);

            foreach($value as $filed=>$val)
            {
                $subnode = $dom->createElement($filed);
                $subvalue = $dom->createTextNode($val);

                $subnode->appendChild($subvalue);
                $node->appendChild($subnode);
            }
        }

        if (empty($dir)) {
            header("Content-type:text/xml");
            header("Content-Disposition: attachment; filename=$file.xml");
            echo $dom->saveXML();
        } else {
            $dom->save($dir.$file.'xml');
        }
    }
}