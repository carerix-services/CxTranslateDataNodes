<?php
	define('ROOTDIR', dirname(__FILE__));
	require_once(ROOTDIR . '/autoload.php');
	set_exception_handler(array('ExceptionController', 'addException'));
	
	session_start();

	if ( empty($_SESSION['theClass']) ) {
		$_SESSION['theClass'] = new Translate;
	}
	$_SESSION['theClass']->run();
	
	require_once ROOTDIR . '/views/overview.phtml';
