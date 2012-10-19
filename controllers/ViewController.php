<?php 
class ViewController {
	protected static $_view = '';  
	
	public static function setView($view) {
		self::$_view = $view;
	} // setView();
	
	public static function showView() {
		echo self::$_view;
	} // showView();
	
} // ViewController();