<?php
/**
 * Singleton holder of the DBConnection
 * @author Jasper Stafleu
 */
class DBConnect {
	/**
	 * The static instance of PDO this singleton keeps track of.
	 * @var unknown
	 */
	private static $_instance = null;

	/**
	 * Constructor of a singleton. Use getInstance instead
	 */
	private function __construct() {
	} // __construct();

	/**
	 * @throws Exception
	 */
	private function __clone() {
		throw new Exception('Cloning ' . __CLASS__ . ' is not possible ');
	}	 // __clone();

	/**
	 * Returns the static instance of the PDO object
	 *
	 * @throws Exception
	 * @return PDO
	 */
	public static function getPDO() {
		if ( self::$_instance === null ) {
			$loc = ROOTDIR . 'db/.db';
			if ( !touch($loc) ) {
				throw new Exception('Can neither find nor create the db file');
			}
			self::$_instance = new PDO('sqlite:' . $loc);
		}
		return self::$_instance;
	} // __getInstance();

} // DBConnect