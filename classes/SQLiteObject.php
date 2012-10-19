<?php
/**
 * Abstract class used to allow the model to be stored in a sqlite DB. 
 * @author Jasper Stafleu
 */
abstract class SQLiteObject {
	/**
	 * The static PDO object created by DBConnect
	 * 
	 * @var PDO
	 */
	public static $_pdo = null;

	/**
	 * Static array holding existing table names
	 * 
	 * @var strings[]
	 */
	protected static $_tables = array();
	
	/**
	 * Holds the current ISO8601 timestamp
	 * 
	 * @var string
	 */
	protected static $_now = null;
	
	/**
	 * Creates the SQL lite DB as based upon the SQLLiteObject's field array
	 */
	protected function _createDB() {
		$fields = $this->getModel();
		if ( empty($fields) ) {
			throw new Exception('No fields definition found');
		}
		
		$columnDefs = array();
		foreach ( $fields as $property => $definition ) {
			$columnDef = "`{$property}` {$definition->type}";
			if ( !empty($definition->constraint) ) {
				$columnDef .= " {$definition->constraint}";
			}
			$columnDefs[] = $columnDef; 
		} // foreach
		
		$create = "CREATE TABLE IF NOT EXISTS " . get_class($this) . " (" . implode(", ", $columnDefs) . ")";
		if ( self::$_pdo->query($create) ) {
			self::$_tables[] = get_class($this);
		} else {
			$err = self::$_pdo->errorInfo();
			throw new Exception("PDO Error while creating table " . get_class($this) . ": " . $err[2]);
		}
	} // _createDB();
	
	/**
	 * Retrieves a (static) current ISO8601 timestamp for consistent use in the DB
	 * 
	 * @param string $refresh	If set to true (default), this will generate a new 
	 * 												timestamp; the previous timestamp will used otherwise
	 * @return string
	 */
	public static function getNow($refresh = true) {
		if ( $refresh || empty(self::$_now) ) {
			$now = new DateTime();
			self::$_now = $now->format(DateTime::ISO8601);
		}
		return self::$_now;
	} // getNow();
		
	
	/**
	 * Creates the available fields and fills their values. Available only once.
	 */
	private final function _setDefaults() {
		foreach ( $this->getModel() as $property => $definition ) {
			if ( !property_exists($this, $property) ) {
				$this->$property = isset($definition->default) ? $definition->default : null;
			}
		} // foreach
	} // _setDefaults();
	
	/**
	 * Return the desired DB location
	 * @return string
	 */
	protected function _getDbLocation() {
		return ROOTDIR . '/db/.db';
	} // _getDbLocation()';
	
	/**
	 * Construct the object by looking for its DB and creating it if absent
	 */
	public function __construct() {
		// if not yet available: reference the PDO instance
		if ( self::$_pdo === null ) {
			self::$_pdo = self::getPDO($this->_getDbLocation());
		}
		
		// if not yet available: determine the tables that are currently available
		if ( empty(self::$_tables) ) {
			foreach(self::$_pdo->query("SELECT name FROM sqlite_master") as $row ) {
				self::$_tables[] = $row['name'];
			} // foreach
		}
		
		// if this class does not have a table yet, create it
		if ( !in_array(get_class($this), self::$_tables) ) {
			$this->_createDB();
		}
		
		// set the defaults for this object
		$this->_setDefaults();
	} // __construct();
	
	/**
	 * Returns the PDO isntance
	 * 
	 * @return PDO
	 */
	public static function getPDO() {
		return self::$_pdo ? self::$_pdo : DBConnect::getPDO();
	} // getPDO
	
	/**
	 * Store the object into the DB, retrieving and setting it's AI key if needed 
	 */
	public function store() {
		// retrieve the fields and values of $this as per the public non-static 
		// and properties and store them and their values for later use
		$fields = $values = array();
		foreach ( $this->getModel() as $property => $loos ) {
			$fields []= $property;
			$values [$property] = $this->$property;
		} // foreach
		
// 		$refl = new ReflectionObject($this);
// 		foreach ( $refl->getProperties(ReflectionProperty::IS_PUBLIC) as $prop ) {
// 			if ( $prop->isStatic() ) continue;
// 			$fields []= $prop->name;
// 			$values [$prop->name]= $prop->getValue($this);
// 		} // foreach

		// create the INSERT query as per the fields and values gathered above
		$sql = "INSERT OR REPLACE INTO " . get_class($this)
						. " (`" . implode("`, `", $fields) . "`) "
						. " VALUES (:" . implode(", :", $fields) . ")";
		
		// prepare the statement and bind it's values
		if ( !($stmt = self::$_pdo->prepare($sql)) ) {
			$err = self::$_pdo->errorInfo();
			throw new Exception("PDO Error preparing statement for " . get_class($this) . ": " . $err[2]);
		}
 		foreach ( $values as $field => $value ) {
 			$stmt->bindValue(':'.$field, $value);
 		} // foreach
 		
 		// execute the statement and be verbose about its errors
 		if ( !$stmt->execute() ) {
 			$err = self::$_pdo->errorInfo();
 			throw new Exception("PDO Error while executing " . get_class($this) . "::store() : " . $err[2]);
 		}
 		
 		$stmt->closeCursor();
 		
 		// retrieve the newly created object's AI key
 		$this->id = self::$_pdo->lastInsertId();
	} // store();
	
} // SQLLiteObject();
