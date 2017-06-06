<?php

/**
 * Class XMLDocument
 */
class XMLDocument extends DOMDocument {

    /**
     * XML data parsed to array
     * @var array
     */
    public $arrayData = array();

    /**
     * Method to parse certain tag names and put data in the field arrayData as a [[pair name => value,pair name => value,...]]
     */
    public function parseXmlToArray($tagName) {
        $rows = $this->getElementsByTagName($tagName);
        for ($k = 0; $k < $rows->length; $k++) {
            $this->arrayData[$k] = array();
            $childNodes = $rows->item($k)->childNodes;
            $this->parseChildNodes($childNodes, $k);
        }
    }

    /**
     * Recursive method to parse child nodes. Put data in the field arrayData as a pair name => value
     * @param $nodes DOMNodeList
     * @param $rowNumber integer
     * @return void
     */
    private function parseChildNodes($nodes, $rowNumber) {
        foreach ($nodes as $node) {
            if ($node->hasChildNodes() && $node->childNodes->length > 1) {
                $this->parseChildNodes($node->childNodes, $rowNumber);
            } elseif ($node->nodeType === 1) {
                if ($node->hasAttribute('field')) {
                    $name = $node->getAttribute('field');
                } else {
                    $name = $node->nodeName;
                }
                $value = $node->textContent;
                if (!isset($this->arrayData[$rowNumber][$name])) {
                    $this->arrayData[$rowNumber][$name] = $value;
                } elseif ($this->arrayData[$rowNumber][$name] != $value && mb_strpos($this->arrayData[$rowNumber][$name], $value) === false) {
                    $this->arrayData[$rowNumber][$name] .= " " . $value;
                }
            }
        }
    }

}
