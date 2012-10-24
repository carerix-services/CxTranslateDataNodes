<?php
class Translate {
	/**
	 * Holder for the app to be translated
	 * @var string
	 */
	protected $_app = null;
	
	/**
	 * Holder for the XML password of the app to be translated
	 * @var string
	 */
	protected $_password = null;
	
	/**
	 * Origin language for the to be translated app
	 * @var string
	 */
	protected $_sourcelanguage = null;
	
	/**
	 * Languages to be translated to the app
	 * @var int[]
	 */
	protected $_filllanguages = array();
	
	/**
	 * Holder for the languages available in the app
	 * @var int => string
	 */
	protected $_availablelanguages = array();
	
	/**
	 * Boolean holding whether to overwrite already existing values or not
	 */
	protected $_overwrite = false;

	/**
	 * The number of datanodes to be handled
	 * @var int
	 */
	protected $_dataNodeCount = 0;
	
	/**
	 * The translation controller
	 * @var TranslationController
	 */
	protected $_tc = null;
	
	/**
	 * Wake up of the translate: get the $_REQUEST values and store the relevant
	 * ones
	 */
	public function __wakeup() {
		foreach ( $_REQUEST as $key => $val ) {
			if ( $key === 'password' && $val === 'Back' ) {
				$val = '';
			}
			
			$prop = '_' . $key;
			if ( property_exists($this, $prop) ) {
				$this->$prop = $val;
			}
		} // foreach
		
		if ( ($pos = array_search($this->_sourcelanguage, $this->_filllanguages)) !== FALSE ) {
			unset($this->_filllanguages[$pos]);
		}
	} // __wakeup();
	
	/**
	 * Magic sleep function
	 */
	public function __sleep() {
		return array('_app', '_password', '_sourcelanguage', '_filllanguages', '_overwrite', '_availablelanguages');
	} // __sleep();
	
	/**
	 * Run method: determine preconditions and show forms as required
	 */
	public function run() {
		$this->_tc = new TranslationController();
		
		if ( !$this->_tc->init() ) {
			$this->showTranslationUpload();
		} else if ( empty($this->_app) || empty($this->_password) ) {
			$this->_showAppForm();
		} else if ( empty($this->_sourcelanguage) || empty($this->_filllanguages) ) {
			$this->_showLanguageForm();
		} else {
			$result = $this->_translate();
			$this->_showResult();
			
			// reset the $_SESSION to reset the form
			$_SESSION = array();
		}
	} // run();
	
	/**
	 * Shows the uploads view.
	 */
	public function showTranslationUpload() {
		if ( empty($this->_tc) ) {
			$this->_tc = new TranslationController();
		}
		return $this->_tc->showTranslationUpload();
	} // showTranslationUpload();
	
	/**
	 * Shows the app form (choose app, choose app password)
	 */
	protected function _showAppForm() {
		ob_start();
		require_once ROOTDIR . '/views/appform.phtml';
		ViewController::setView(ob_get_clean());
	} // _showAppForm();
	
	/**
	 * Shows the app form (choose app, choose app password)
	 */
	protected function _showLanguageForm() {
		// languages available for translation in the translation matrix
		$translatedLanguages = $this->_tc->getLanguages();
		
		// languages available for translation in the app
		$availableLanguages = $this->_getAppLanguageNodes();

		// Intersection of the above	
		$languages = array_intersect($availableLanguages, $translatedLanguages);
		
		ob_start();
		require_once ROOTDIR . '/views/languageform.phtml';
		ViewController::setView(ob_get_clean());
	} // _showAppForm();

	/**
	 * Returns the language available in the app
	 */
	protected function _getAppLanguageNodes() {
		// retrieve the requested XML
		$languages = array();
		$iterationSize = 100;
		$end = -1;
		for ( $it = 0; $it < $end || $end === -1; $it += $iterationSize ) {
			
			$query = array(
					"template" => "objects.xml",
					"entity" => "CRDataNode",
					"qualifier" => "type.identifier='System language'",
					"start" => $it,
					"count" => $iterationSize,
					"show" => array("value"),
			);
			$query = preg_replace('/%5B[0-9]+%5D=/', '=', http_build_query($query));
			
			$xml = @file_get_contents(
					"http://{$this->_app}web.carerix.net/cgi-bin/WebObjects/{$this->_app}web.woa/wa/view?" . $query,
					false,
					stream_context_create(array(
							'http' => array(
									'method' => 'GET',
									'header' => 'x-cx-pwd: ' . $this->_password,
							)
					))
			);
			
			// if this is not an XML: throw exception
			if ( strpos($xml, '<?xml') !== 0 ) {
				throw new Exception("Failed to obtain XML for GET view: {$xml}");
			}
			
			// create and return the DOMXPath
			$doc = new DOMDocument();
			$doc->formatOutput = true;
			$doc->preserveWhiteSpace = false;
			$doc->loadXML($xml, LIBXML_NOENT);
			
			$xpath = new DOMXPath($doc);
			
			if ( $end === -1 ) {
				$end = intval($xpath->query('/*/@count')->item(0)->nodeValue);
			}
			
			foreach ( $xpath->query('/*/*') as $node ) {
				$this->_availablelanguages[$node->getAttribute('id')] 
							= $xpath->query('value', $node)->item(0)->nodeValue;
			}
		} // for
		return $this->_availablelanguages;
	} // _getAppLanguageNodes();
	
	/**
	 * Run the actual translation of all datanodes.
	 */
	protected function _translate() {
		while ( $this->_dataNodeCount > -1 ) {
			set_time_limit(10);
			$xpath = $this->_getAllDataNodes();
			foreach ( $xpath->query('/*/*') as $node) {
				$this->_updateLanguageFor($node, $xpath);
			} // foreach
		} // while
	} // _translate();
	
	/**
	 * Updates the DataNode $node to fit the nwe languages.
	 * 
	 * @param DOMNode $node
	 */
	protected function _updateLanguageFor(DOMNode $node, DOMXPath $xpath) {
		try {
			$id = intval($node->getAttribute('id'));
			$q = "values/*[toLanguageNode/*/value='{$this->_sourcelanguage}']/value";
			if ( !($original = $xpath->query($q, $node)->item(0)) ) {
				throw new Exception('Failed to find original language');
			}
			if ( !($translation = $this->_tc->getTranslation($original->nodeValue, $this->_sourcelanguage)) ) {
				throw new Exception('Failed to find translation');
			}
			
			foreach ( $this->_filllanguages as $datanodeid => $toLang ) {
				try {
					$overwriteID = null;
					
					if ( $subnode = $xpath->query("values/CRDataNodeValue[toLanguageNode/*/value='{$toLang}']", $node)->item(0) ) {
						if ( !$this->_overwrite ) {
							$this->_report($id, 'Translation already present', $original->nodeValue, $toLang);
							continue;
						} else {
							$overwriteID = $subnode->getAttribute('id');
						}
					}
					
					$this->_updateNode($id, $datanodeid, $translation->$toLang, $overwriteID);
					$this->_report($id, null, $original->nodeValue, $toLang);
				} catch ( Exception $e ) {
					$this->_report($id, $e->getMessage(), $original->nodeValue, $toLang);
				}
			} // foreach
			
		} catch ( Exception $e ) {
			$this->_report($id, $e->getMessage(), @$original->nodeValue);
			return false;
		}
		
		return true;
	} // _updateLanguageFor();
	
	/**
	 * Calls the XML api to update node ID to contain $value as translation of type $datanodeid
	 * 
	 * @param int $id
	 * @param int $datanodeid
	 * @param string $value
	 */
	protected function _updateNode($id, $datanodeid, $value, $overwriteID = null) {
		$doc = new DOMDocument;
		$doc->loadXML("<?xml version='1.0' encoding='utf-8'?>
				<CRDataNode id='{$id}'>
					<values>
						<CRDataNodeValue" . ($overwriteID !== null ? " id='{$overwriteID}'" : "") . ">
							<toLanguageNode><CRDataNode id='{$datanodeid}'/></toLanguageNode>
							<value>{$value}</value>
						</CRDataNodeValue>
					</values>
				</CRDataNode>");
		
		// retrieve the requested XML
		$query = $doc->saveXML();

		// create the POST context 
		$context = array(
				'http' => array(
						'method' => 'POST',
						'header' => 'x-cx-pwd: ' . $this->_password . "\r\nContent-type: application/x-www-form-urlencoded\r\n",
						'content' => $query,
				)
		);
		
		// do the post
		$xml = file_get_contents(
				"http://{$this->_app}web.carerix.net/cgi-bin/WebObjects/{$this->_app}web.woa/wa/save",
				false,
				stream_context_create($context)
		);
		
		// if this is not an XML: throw exception
		if ( strpos($xml, '<?xml') !== 0 ) {
			throw new Exception("Failed to obtain XML for POST save: " . print_r($http_response_header, 1));
		}
	} // _updateNode();
	
	/**
	 * Report the success (if $message is null) or failure of a translation of $id
	 * 
	 * @param int $id
	 * @param string $message
	 */
	protected function _report($id, $message = null, $original = null, $language = null) {
		$report = new DbReport;
		$report->app = $this->_app;
		$report->datanodeid = $id;
		$report->success = $message === null;
		$report->message = $message;
		$report->language = $language;
		$report->original = $original;
		$report->store();
	} // _report();
	
	/**
	 * Retrieve the results and show them 
	 */
	protected function _showResult() {
		ob_start();
		$reports = DbReport::getPDO()->prepare('SELECT * FROM DbReport ORDER BY datanodeid');
		$reports->execute();
		$reports->setFetchMode(PDO::FETCH_CLASS, 'DbReport');
		require_once ROOTDIR . '/views/resulttable.phtml';
		ViewController::setView(ob_get_clean());		
	} // _showResult();
	
	/**
	 * Returns the employee of the visit in CX
	 *
	 * @return string
	 */
	protected function _getAllDataNodes($query = null) {
		// retrieve the requested XML
		$count = 100;
		$query = array(
				"template" => "objects.xml",
				"entity" => "CRDataNode",
				"start" => $this->_dataNodeCount,
				"count" => $count,
				"ordering" => "({key=dataNodeID;sel=Ascending})",
				"qualifier" => "notActive=0 and type.isMultiLanguage=1",
				"show" => array("values.value", 'values.toLanguageNode.value'),
		);
		$query = preg_replace('/%5B[0-9]+%5D=/', '=', http_build_query($query));
		
		$xml = @file_get_contents(
				"http://{$this->_app}web.carerix.net/cgi-bin/WebObjects/{$this->_app}web.woa/wa/view?" . $query,
				false,
				stream_context_create(array(
						'http' => array(
								'method' => 'GET',
								'header' => 'x-cx-pwd: ' . $this->_password,
						)
				))
		);

		// if this is not an XML: throw exception
		if ( strpos($xml, '<?xml') !== 0 ) {
			throw new Exception("Failed to obtain XML for GET view: " . @print_r($http_response_header, 1));
		}
	
		// create and return the DOMXPath
		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xml, LIBXML_NOENT);
		$xpath = new DOMXPath($doc);
		
		$this->_dataNodeCount += $count;
		if ( intval($xpath->query('/*/@count')->item(0)->nodeValue) < $this->_dataNodeCount ) {
			$this->_dataNodeCount = -1;
		}
		
		return $xpath;
	} // _getAllDataNodes();

} // Translate
