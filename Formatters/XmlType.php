<?php
namespace Verint\FeedbackBundle\Formatters;


class XmlType {

	public function __construct( ){}

	public static function getArray($xml) {
		if ($xml instanceof \SimpleXMLElement) {
			$children = $xml->children();
			$return = null;
		}
		foreach ($children as $element => $value) {
			if ($value instanceof \SimpleXMLElement) {
				$values = (array) $value->children();

				if (count($values) > 0) {
					$return[$element] = $this->getArray($value);
				} else {
					if (!isset($return[$element])) {
						$return[$element] = (string) $value;
					} else {
						if (!is_array($return[$element])) {
							$return[$element] = array($return[$element], (string) $value);
						} else {
							$return[$element][] = (string) $value;
						}
					}
				}
			}
		}
		if (is_array($return)) {
			return $return;
		} else {
			return false;
		}
	}
	
	public static function setSimpleXml($data)
	{
		$xml = new \SimpleXMLElement($data->any);
		if (!isset($xml->NewDataSet)){
			return false;
		}
		return $xml->NewDataSet->Table1;
	}



}
