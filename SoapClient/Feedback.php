<?php
namespace Verint\FeedbackBundle\SoapClient;

use Doctrine\ORM\EntityManager;
use Verint\FeedbackBundle\Formatters\XmlType;
/**
 * Verint API
 *
 * a PHP class to interact with the Verint Web Services API
 * @original author David Briggs - Adapted for Cisco Systems by Andrew Wolder (awolder@cisco.com)
 *
 */
class Feedback {

	/**
	 * @var apiurl
	 * @var userid
	 * @var password
	 */
	protected $configuration;
	protected $em;
    
	public function __construct( array $configuration = array(),EntityManager $em )
	{
		$this->configuration = $configuration;
		$this->em            = $em;
		
		$this->wsdlurl  = $this->configuration['wsdlurl'];
		$this->username = $this->configuration['userid'];
		$this->password = $this->configuration['password'];
		
		
	}

	/**
	 * get_api_url()
	 *
	 * Get the API url
	 *
	 * @access public
	 * @return string
	 */
	public function get_api_url() {
		return $this->wsdlurl;
	}

	/**
	 * set_api_url()
	 *
	 * Set the API url
	 *
	 * @access public
	 * @param string
	 * @return void
	 */
	public function set_api_url($api_url) {
		$this->wsdlurl = $api_url;
	}

	/**
	 * set_username()
	 *
	 * Set the Username
	 *
	 * @access public
	 * @param string
	 * @return void
	 */
	public function setUsername($username) {
		$this->username = $username;
	}

	/**
	 * Set the Password
	 * @access public
	 * @param string
	 */
	public function setPassword($password) {
		$this->password = $password;
	}

	/* Can be used in the GetSurveyDataEx call
	 * @param datetime (format Y-m-d/TH:m:s)
	 */
	public function setStartDate($sd)
	{
		$this->startdate = $sd;
	}
	/* Can be used in the GetSurveyDataEx call
	 * @param datetime (format Y-m-d/TH:m:s)
	 */
	public function setEndDate($ed)
	{
		$this->enddate = $ed;
	}

	/* get total survey responses. id = survey id
	 * status (true/false) completed only
	 */
	public function getResponseCount($id, $status = true)
	{
		return $this->request('GetResponseCount', array('projectId' => $id, 'completedOnly' => $status));
	}
	/**
	 * request()
	 *
	 * Request data from the API
	 *
	 * @access public
	 * @param string, array
	 * @return xml
	 */
	public function request($func, $options) {
		
		ini_set('max_execution_time', 0);
		
		$this->soap = new \SoapClient($this->wsdlurl, array(
				'cache_wsdl' => 1,
				'encoding'   => 'utf-8' ));
		$this->soap->Login(array('userName' => $this->username, 'password' => $this->password));
		try {
			$response = $this->soap->$func($options);
		} catch (\SoapFault $e) {
			return array("error" => $e->faultstring);
		}

		/* Set Preload Data does not have a result key. Just return the $result object. */
		if ($func == "SendInvitations"){
			return $response;
		} else {
			$r = $func . 'Result';
		}

		if (!$response) {
			return false;
		} else {
			return $response->$r;
		}
	}

	/**
	 * __call()
	 *
	 * Request data from the API
	 *
	 * @access public
	 * @param string
	 * @param string, array
	 * @return xml
	 */
	public function __call($method, $args) {
		return $this->request($method, $args);
	}


	/**
	 * getCompleteArray()
	 *
	 * Performs the "GetSurveyDataPaged" request and returns the completed data in a nicely formatted array
	 *
	 * @access public
	 * @param string
	 * @param string
	 * @return array
	 */
	public function getCompleteArray($pid, $criteria = null, $datamap = null, $fields = null, $completed = true) {
		$responseCount = $this->request('GetResponseCount', array('projectId' => $pid, 'completedOnly' => $completed));

		$last_iteration = $responseCount % 500;
		if ($last_iteration == 0) {
			$last_iteration = 500;
		}
		$num_of_iterations = ceil($responseCount / 500);

		$prevRecordId = 0;

		$result = array();

		while ($num_of_iterations > 0) {
			$recordCount = 500;
			if ($num_of_iterations == 1) {
				$recordCount = $last_iteration;
			}
			$o = array(
					'projectId' => $pid,
					'completedOnly' => $completed,
					'recordCount' => $recordCount,
					'prevRecordId' => $prevRecordId);
			if ($criteria) {
				$o['filterXml'] = $criteria;
			}
			if ($datamap) {
				$o['dataMapXml'] = $datamap;
			}
			/* In case we want to use GetSurveyDataEx with Date Rage
			 $f = array(
			 'projectId' => $pid,
			 'startTime' => null,
			 'endTime'   => null,
			 'completedOnly'  => false
			 );
			 */
			$data = $this->request('GetSurveyDataPaged', $o);			
			$xml  = XmlType::setSimpleXml($data);
			unset($data);unset($xml);
			
			foreach ($xml as $record) {
				$xmlattr = XmlType::getArray($record);
				/* if $fields is an array, then we want to return each thing in the array */
				if (is_array($fields)) {
					$tempArray = array();
					foreach ($fields as $field) {
						$tempArray[$field] = $xmlattr[$field];
					}
					$result[] = $tempArray;
				} elseif ($fields) {
					/* if $fields is a single entry, then only return that */
					$result[] = $xmlattr[$fields];
				} else {
					/* if $fields is not set, then return the entire record */
					$result[] = $xmlattr;
				}
				/* set the last record */
				$prevRecordId = $xmlattr['recordid'];
			}
			unset($xmlAsArray); // for quicker garbage collection
			/* reduce the number of iterations */
			$num_of_iterations--;
		}

		return $result;
	}

	/**
	 * getCompleteCSV()
	 *
	 * Performs the "GetSurveyDataEx" request and returns the completed data as a comma separated list
	 *
	 * @access public
	 * @param string
	 * @param string
	 * @return array
	 */
	public function getCompleteCSV($pid, $criteria = null) {
		$data = $this->getCompleteArray($pid, $criteria);

		if (!$data) {
			return false;
		}

		$keys = array_keys($data[0]);
		array_unshift($data, $keys);

		//$columns = $this->getColumnList($pid);



		$fp = fopen('completes.csv', 'w');
		foreach ($data as $record) {
			fputcsv($fp, $record);
		}
		fclose($fp);
	}

	public function getColumnList($pid) {
		$result = array();
		$data   = $this->request('GetColumnList', array('projectId' => $pid));

		if (!$data) {
			return false;
		}
		
		$result = XmlType::setSimpleXmlFieldArray($data);
		
		$columnStandard = array(
				"started"       => "Varchar",
				"completed"     => "Varchar",
				"modified"      => "Varchar",
				"branched_out"  => "Varchar"
		
		);
		
		return array_merge($result,$columnStandard);
	}

	/**
	 * addParticipant()
	 *
	 * adds a participant to the specified survey
	 *
	 * @access public
	 * @param string
	 * @param array
	 * @param array
	 * @return string
	 */
	public function addParticipant($pid, $user, $prepop = null) {
		// to prevent user-error in the naming of keys
		$mappings = array(
				'key1' => 'userkey1', 'Key1' => 'userkey1', 'Key 1' => 'userkey1', 'key 1' => 'userkey1',
				'userKey1' => 'userkey1', 'Userkey1' => 'userkey1', 'UserKey1' => 'userkey1',
				'key2' => 'userkey2', 'Key2' => 'userkey2', 'Key 2' => 'userkey2', 'key 2' => 'userkey2',
				'userKey2' => 'userkey2', 'Userkey2' => 'userkey2', 'UserKey2' => 'userkey2',
				'key3' => 'userkey3', 'Key3' => 'userkey3', 'Key 3' => 'userkey3', 'key 3' => 'userkey3',
				'userKey3' => 'userkey3', 'Userkey3' => 'userkey3', 'UserKey3' => 'userkey3',
				'e-mail' => 'email', 'eMail' => 'email', 'Email' => 'email', 'E-mail' => 'email', 'E-Mail' => 'email', 'e-Mail' => 'email',
				'Culture' => 'culture'
		);

		$parameters = array();
		$parameters['projectId'] = $pid;
		foreach ($user as $key => $value) {
			/* change the key if its incorrect */
			if (array_key_exists($key, $mappings)) {
				$key = $mappings[$key];
			}
			$parameters[$key] = $value;
		}

		$recordid = $this->request('AuthorizeParticipantForSurvey', $parameters);

		/* if the request returned an error */
		if (!$recordid || (is_array($recordid)) )
		{
			return $recordid;
		} else {

			if ($prepop)
			{
				/* try to add the prepop, if it fails, return false */
				if (!$this->addPrepop($pid, $recordid, $prepop)) {
					return false;
				};
			}
		}
		return $recordid;
	}

	/**
	 * addPrepop()
	 *
	 * adds prepop to the desired participant
	 *
	 * @param string
	 * @param string
	 * @param array
	 */
	public function addPrepop($pid, $rid, $pp) {
		/* get the types list |*/
		$types = $this->getColumnList($pid);
		if (!$types) {
			return false;
		}

		/* @note: the $pp array will need to be setup as array(db_heading => value, ...)  */
		$datastring = '<Rows><Row id="' . $rid . '">';
		foreach ($pp as $ppkey => $ppvalue) {
			$datastring .= '<Field id="' . $ppkey . '" type="' . $types[$ppkey] . '">' . $this->xml_entities($ppvalue) . '</Field>';
		}
		$datastring .= '</Row></Rows>';

		$response = $this->soap->SetPreloadData(array('projectId' => $pid, 'dataString' => $datastring));

		/* if response has a value, then the request failed */
		if ($response) {
			return false;
		}

		return true;
	}

	public function getParticipantData($pid, $status = null) {
		$participantCount = $this->request('GetAuthorizedParticipantCount', array('projectId' => $pid));

		$last_iteration = $participantCount % 1000;
		if ($last_iteration == 0) {
			$last_iteration = 1000;
		}
		$num_of_iterations = ceil($participantCount / 1000);
		$startRecordId = 0;

		$records = array();
		while ($num_of_iterations > 0) {
			$recordCount = 1000;
			if ($num_of_iterations == 1) {
				$recordCount = $last_iteration;
			}
			$o = array(
					'projectId' 	=> $pid,
					'recordCount' 	=> $recordCount,
					'surveyStatus' 	=> $status ? $status : 'Any',
					'startRecordId' => $startRecordId);
			$data = $this->request('GetParticipantDataPaged', $o);
			try {
				$xml = new \SimpleXMLElement($data->any);
			} catch (Exception $e) {
				$records[] = array('ERROR' => $e->getMessage(), 'data' => $data->any);
				return $records;
			}

			unset($data); // for quicker garbage collection

			foreach ($xml->children() as $record) {
				$attr = $record->attributes();
				$record = (string) $attr['recordid'];
				/* store the recordid */
				$records[$record] = array(
						'key1' 		=> (string) $attr['user_key1'],
						'email' 	=> (string) $attr['email'],
						'status' 	=> (int) $attr['invite_status'],
						'completed' => ($attr['completed'] != "") ? 1 : 0,
						'culture' 	=> (string) $attr['culture']
				);

				$startRecordId = $record;
			}
			unset($xml); // for quicker garbage collection

			$num_of_iterations--;
		}

		return $records;
	}

	/**
	 * getPreloadData()
	 *
	 * Performs the "GetSurveyDataPaged" request and returns the completed data in a nicely formatted array
	 *
	 * @access public
	 * @param string
	 * @param string
	 * @return array
	 */
	public function getPreloadData($pid, $fields = null) {
		$records = $this->getParticipantData($pid);
		$result  = array();
		/* Take the arrays and */
		foreach ($records as $recordid => $record) {
			$record;
			$data = $this->request('GetPreloadData', array('projectId' => $pid, 'recordId' => $recordid));
			$xml = new \SimpleXMLElement($data->any);
			if (is_array($fields)) {
				foreach ($fields as $field) {
					$result[$field] = (string) $xml->Field[$field];
				}
			} else {
				$result[$fields][] = (string) $xml->Field[$fields];
			}
		}
		return $result;
	}

	public function getCampaignStatus($pid, $rid) {
		$data = $this->request('GetCampaignHistory', array('projectId' => $pid, 'participantId' => $rid));
		$xml = new \SimpleXMLElement($data->any);
		/* possible undeliverable error codes */
		$undes = array(30, 31, 32, 33, 34, 40, 41, 42, 43, 44, 45, 46);
		/* possible unsubscribe error codes */
		$unsubs = array(90);
		foreach ($xml->children() as $child) {
			$atts = $child->attributes();
			if (in_array($atts['status'], $undes)) {
				return 50;
			}
			if (in_array($atts['status'], $unsubs)) {
				return 51;
			}
		}
		return 1;
	}

	/* Get data of a single participant for a given Record Id - Used to check if a record was branched out */
	public function getSinglePartipant($pid, $rid) {
		$data = $this->request('GetParticipantInformation', array('projectId' => $pid, 'recordId' => $rid));
		$xml = new \SimpleXMLElement($data->any);
		$result = array();
		$result["started"]      = (string)$xml[0]["started"];
		$result["completed"]    = (string)$xml[0]["completed"];
		$result["branchedout"]  = (string)$xml[0]["branched_out"];
		$result["email"]        = (string)$xml[0]["email"];

		return $result;
	}
	
	/* Get the Survey Data Map, which returns the field to value mappings.
	 * This can be used to export Survey Data Values instead of Raw values
	 * when calling functions such as GetSurveyDataEx, by adding option 'dataMapXml' => resultOfThisFunction 
	 */
	public function getReportDataMap($surveyid = null)
	{
		$ep = array('projectId' => $surveyid);
		$dm = $this->request('GetReportDataMap', $ep);
		return $dm->any;
	}
	
	/* Return an array of data containing each field with it's result for each respondant of the survey */
	public function getSurveyDataArray($surveyid = null, $reportvalues = false,$startdate = null, $enddate = null, $completed = false)
	{
		$o = array();
			$o['projectId'] 	= $surveyid;
			$o['completedOnly'] = false;
		if (true === $reportvalues){
			$o['dataMapXml'] = $this->getReportDataMap($surveyid);
		}
		if (null !== $startdate){
			$o['startTime'] = $startdate;
		}
		if (null !== $enddate){
			$o['endTime'] = $enddate;
		}
		if (true === $completed){
			$o['completedOnly'] = true;
		}

		$xmlattr = array();
		$result  = array();
		
		$data = $this->request('GetSurveyDataEx',$o);
		$xml  = XmlType::setSimpleXml($data);
		unset($data);
		
		foreach ($xml as $record) {
			
			$xmlattr  = XmlType::getArray($record);
			$result[] = $xmlattr;
		}
		/* unset $xml array for better garbage collection */
		unset($xml);
		
		return $result;
	}
	
	public function getSingleResultByKey1($surveyid = null, $reportvalues = false, $key1 = null)
	{
		$o = array();
		$o['projectId'] 	= $surveyid;
		$o['completedOnly'] = false;
		if (true === $reportvalues){
			$o['dataMapXml'] = $this->getReportDataMap($surveyid);
		}
		$filterxml = '<CriteriaCollection>
        					<Criterion heading	= "user_key1"
        						expression	= "="
        						value		= "'.$key1.'" />
    						</CriteriaCollection>';
			
		$o['filterXml'] 	= $filterxml;
		
		$data = $this->request('GetSurveyDataEx',$o);
		$xml  = XmlType::setSimpleXml($data);
		unset($data);
		
		if ($xml){
			foreach ($xml as $record) {
		
				$xmlattr  = XmlType::getArray($record);
				$result[] = $xmlattr;
			}
		}
		/* unset $xml array for better garbage collection */
		unset($xml);
		
		return $result;
	}

	/* Used to decode special characters in XML Strings  */
	function xml_entities($string) {
		return strtr(
				$string,
				array(
						"<" => "&lt;",
						">" => "&gt;",
						'"' => "&quot;",
						"'" => "&apos;",
						"&" => "&amp;",
				)
				);
	}

}
