<?php
class TranslationController {
	/**
	 * DB location for the translations
	 */
	protected $_dbLocation = 'db/.translations';
	
	/**
	 * PDO instance for the translation DB
	 */
	protected $_pdo = null;
	
	/**
	 * Array of prepared statements for selecting the translations based on the 
	 * language that is the key of the array
	 * 
	 * @var string => string
	 */
	protected $_preparedGetFor = array();
	
	/**
	 * Create the TranslationController
	 */
	public function __construct() {
		$this->_dbLocation = ROOTDIR . '/' .$this->_dbLocation;
	} // __construct();
	
	/**
	 * Initialises the translation table
	 * 
	 * @return boolean
	 */
	public function init() {
		if ( !empty($_FILES) ) {
			$this->_createNewTranslations();
		}

		if ( !file_exists($this->_dbLocation) ) {
			return false;
		}
		
		$this->_getPDO();
		return true;
	} // init();
	
	/**
	 * Initialise PDO
	 * @return PDO
	 */
	public function _getPDO() {
		if ( empty($this->_pdo) ) { 
			$this->_pdo = new PDO('sqlite:' . $this->_dbLocation);
			$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set Errorhandling to Exception
		}
		return $this->_pdo;
	} // _getPDO();
	
	/**
	 * Calling this removes the old translation table and creates new translation
	 * one if available from file.
	 * 
	 * @throws Exception
	 */
	public function _createNewTranslations() {
		if ( empty($_FILES['u' . $_SESSION['secure']]) ) {
			throw new Exception('No translation file found');
		}
		$file = (object) $_FILES['u' . $_SESSION['secure']];
		
		if ( !empty($file->error) || ($fp = fopen($file->tmp_name, 'r')) === FALSE ) {
			throw new Exception('Something went wrong with the upload, please try again');
		}
		
		// remove any "old" translations
		$this->removeTranslations();
		touch($this->_dbLocation);
		$this->_getPDO();
		
		if ( !($cols = @fgets($fp)) ) {
			throw new Exception('No valid columns found in file');
		} else {
			$cols = str_getcsv(trim($cols), ';', '"');
		}
		
		$pre = "`";
		$post = "` COLLATE NOCASE";
		$sql = "CREATE TABLE translations ({$pre}" . implode("{$post}, {$pre}", $cols) . "{$post});";
		$this->_pdo->exec($sql);
		
		$sql = "INSERT INTO translations (`" . implode('`, `', $cols) . "`) VALUES (:" . implode(', :', $cols) . ")";
		$stmt = $this->_pdo->prepare($sql);
	
		while ( ($line = fgets($fp)) !== FALSE ) {
			set_time_limit(1);
			$line = str_getcsv(rtrim($line), ';', '"');
			
			$fields = array();
			foreach ( $line as $id => $val ) {
				if ( $id >= count($cols) ) break;
				$stmt->bindValue($cols[$id], $val);
			} // foreach
			$stmt->execute();
		} // while
		
	} // createNewTranslations();

	/**
	 * Return the translation object which has $for in the $language column
	 *  
	 * @param string $for
	 * @param string $language
	 */
	public function getTranslation($for, $language) {
		if ( empty($this->_preparedGetFor[$language]) ) {
			$this->_preparedGetFor[$language] = $this->_pdo->prepare('SELECT * FROM translations WHERE `' . $language . '`=:for LIMIT 1');
		}
		$stmt = $this->_preparedGetFor[$language];
		$stmt->bindValue('for', $for);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_OBJ);
	} // getTranslation();
	
	/**
	 * Returns the available languages
	 * 
	 * These equal the column names of the table.
	 * @return strings[]
	 */
	public function getLanguages() {
		$stmt = $this->_pdo->prepare('SELECT * FROM translations LIMIT 1');
		$stmt->execute();
		$arr = $stmt->fetch(PDO::FETCH_ASSOC);
		return array_keys($arr);
	} // getLanguages();
	
	/**
	 * Shows the uploads view.
	 */
	public function showTranslationUpload() {
		$_SESSION['secure'] = mt_rand();
		ob_start();
		require_once ROOTDIR . '/views/upload.phtml';
		ViewController::setView(ob_get_clean());
	} // showTranslationUpload();
	
	/**
	 * Removes (actually: renames) the translation table 
	 */
	public function removeTranslations() {
		if ( file_exists($this->_dbLocation) ) {
			rename($this->_dbLocation, $this->_dbLocation . '.' . time());
		}
	} // removeTranslations();
	
} // TranslationController

/**
 * Is use the str_getcsv function, which has to be faked for ancient versions of
 * PHP (below 5.3)
 */
if (!function_exists('str_getcsv')) {
	function str_getcsv($input, $delimiter = ',', $enclosure = '"', $escape = '\\') {
		if ( is_string($input) && !empty($input) ) {
			if ( strpos($input, $enclosure) === FALSE ) {
				// no enclosure: keep it simple!
				$output = explode($delimiter, $input);
			} else {
				$output = array();
				while ( strlen($input) ) {
					if ( strpos($input, $enclosure) === 0 ) {
						// get characters until enclosure ends, followed by delimiter
						if ( ($lastPos = strpos($input, $enclosure . $delimiter, strlen($enclosure))) !== FALSE ) {
							$part = substr($input, strlen($enclosure), $lastPos - strlen($enclosure));
							$output[] = $part;
							$input = substr($input, strlen($lastPos));
						} else if ( ($lastPos = strpos($input, $enclosure, strlen($enclosure))) !== FALSE
								&& $lastPos === (strlen($input) - strlen($enclosure))
						) { // enclosed until end of line
							$output[] = substr($input, strlen($enclosure), $lastPos - strlen($enclosure));
							$input = '';
						} else { // no enclosure: get it by default!
							if ( ($lastPos = strpos($input, $delimiter)) !== FALSE ) {
								$part = substr($input, 0, $lastPos);
								$output[] = $part;
								$input = substr($input, $lastPos + strlen($delimiter));
							} else {
								$output[] = $input;
								$input = '';
							}
						}
					} else if ( ($lastPos = strpos($input, $delimiter)) !== FALSE ) {
						$part = substr($input, 0, $lastPos);
						$output[] = $part;
						$input = substr($input, $lastPos + strlen($delimiter));
					} else {
						$output[] = $input;
						$input = '';
					}
				} // while
			}
		} else {
			$output = false;
		}
		return $output;
	} // str_getcsv();
}