<?php 
class ExceptionController {
	protected static $_exceptions = array();  
	
	public static function addException(Exception $e) {
		self::$_exceptions []= $e;
		require_once ROOTDIR . '/views/overview.phtml';
	} // addException();
	
	public static function getAll() {
		return self::$_exceptions;
	} // getAll();
	
} // ExceptionController();