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
					$return[$element] = XmlType::getArray($value);
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

	public static function setSimpleXmlFieldArray($data)
	{
		$result = array();
		$xml = new \SimpleXMLElement($data->any);
		foreach ($xml as $field) {
			$result[(string) $field['id']] = (string) $field['type'];
		}
		return $result;
	}

	public static function setSimpleXml($data)
	{
		$xml = new \SimpleXMLElement($data->any);
		if (!isset($xml->NewDataSet->Table1)){
			return false;
		}
		return $xml->NewDataSet->Table1;
	}

	public static function getXmlAttributes($object)
	{
		$result = array();
		foreach ($object as $key => $value)
		{
			$result[$key] = (string)$value;
		}

		return $result;
	}



}
