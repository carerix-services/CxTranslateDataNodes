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
			if ( $key === 'password' && $val === 'Terug' ) {
				$val = '';
			}
			
			$prop = '_' . $key;
			if ( property_exists($this, $prop) ) {
				$this->$prop = $val;
			}
		} // foreach
	} // __wakeup();
	
	/**
	 * Magic sleep function
	 */
	public function __sleep() {
		return array('_app', '_password', '_sourcelanguage', '_filllanguages', '_overwrite');
	} // __sleep();
	
	/**
	 * Run method: determine preconditions and show forms as required
	 */
	public function run() {
		$this->_tc = new TranslationController();
		
		if ( !$this->_tc->init() ) {
			$this->_tc->showTranslationUpload();
		} else if ( empty($this->_app) || empty($this->_password) ) {
			$this->_showAppForm();
		} else if ( empty($this->_sourcelanguage) || empty($this->_filllanguages) ) {
			$this->_showForm($this->_tc->getLanguages());
		} else {
			$result = $this->_translate();
		}
	} // run();
	
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
	protected function _showForm($languages) {
		ob_start();
		require_once ROOTDIR . '/views/languageform.phtml';
		ViewController::setView(ob_get_clean());
	} // _showAppForm();
	
	/**
	 * Run the actual translation of all datanodes.
	 */
	protected function _translate() {
		$this->_dataNodeCount = 0; // TODO: REMOVE
		while ( $this->_dataNodeCount > -1 ) {
			$xpath = $this->_getAllDataNodes();
			foreach ( $xpath->query('/*/*') as $node) {
				$this->_updateLanguageFor($node, $xpath);
			}
		} // while
		$this->_showResult();
		
		// reset the $_SESSION to reset the form
		$_SESSION = array();
	} // _translate();
	
	/**
	 * Updates the DataNode $node to fit the nwe languages.
	 * 
	 * @param DOMNode $node
	 */
	protected function _updateLanguageFor(DOMNode $node, DOMXPath $xpath) {
		$id = intval($node->getAttribute('id'));
		try {
			$q = "values/*[toLanguageNode/*/value='{$this->_sourcelanguage}']/value";
			if ( !($original = $xpath->query($q, $node)->item(0)) ) {
				throw new Exception('Failed to find original language');
			}
			if ( !($translation = $this->_tc->getTranslation($original->nodeValue, $this->_sourcelanguage)) ) {
				throw new Exception('Failed to find translation');
			}
			
			echo $id . PHP_EOL;
			print_r($translation); die;
			$this->_report($id);
		} catch ( Exception $e ) {
			$this->_report($id, $e->getMessage());
		}
	} // _updateLanguageFor();
	
	/**
	 * Report the success (if $message is null) or failure of a translation of $id
	 * 
	 * @param int $id
	 * @param string $message
	 */
	protected function _report($id, $message = null) {
		
	} // _report();
	
	/**
	 * Retrieve the results and show them 
	 */
	protected function _showResult() {
		
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
				"show" => array("values.value", 'values.toLanguageNode.value'),
		);
		$query = preg_replace('/%5B[0-9]+%5D=/', '=', http_build_query($query));
		
		$xml = file_get_contents(
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
			throw new Exception("Failed to obtain XML for {$http_word} {$view}: {$xml}");
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
	} // _getEmployee();

}
