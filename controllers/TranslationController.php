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
		
		$cols = fgetcsv($fp, 0, ';', '"', '"');
		
		$sql = "CREATE TABLE translations (`" . implode('`, `', $cols) . "`);";
		$this->_pdo->exec($sql);
		
		$sql = "INSERT INTO translations (`" . implode('`, `', $cols) . "`) VALUES (:" . implode(', :', $cols) . ")";
		$stmt = $this->_pdo->prepare($sql);
		
		while ( ($line = fgetcsv($fp, 0, ';', '"', '"')) !== FALSE ) {
			$fields = array();
			foreach ( $line as $id => $val ) {
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
	} // _showTranslationUpload();
	
	/**
	 * Removes (actually: renames) the translation table 
	 */
	public function removeTranslations() {
		if ( file_exists($this->_dbLocation) ) {
			rename($this->_dbLocation, $this->_dbLocation . '.' . time());
		}
	} // removeTranslations();
	
} // TranslationController