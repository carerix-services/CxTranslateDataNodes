<?php 
class ExceptionController {
	protected static $_exceptions = array();  
	
	public static function addException(Exception $e) {
		self::$_exceptions []= $e;
		$exceptions = self::$_exceptions;
		ob_start();
		require_once ROOTDIR . '/views/exceptions.phtml';
		ViewController::setView(ob_get_clean());
		require_once ROOTDIR . '/views/overview.phtml';
		Translate::getSession()->resetForm();
	} // addException();
	
} // ExceptionController();