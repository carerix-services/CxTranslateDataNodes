<?php
	define('ROOTDIR', dirname(__FILE__));
	require_once(ROOTDIR . '/autoload.php');
	set_exception_handler(array('ExceptionController', 'addException'));
	
	session_start();
	
	if ( empty($_SESSION['theClass']) ) {
		$_SESSION['theClass'] = new Translate;
	}
	
	switch ( strtoupper(empty($_REQUEST['verb']) ? 'run' : $_REQUEST['verb']) ) {
		case 'UPLOAD' :
			$_SESSION['theClass']->showTranslationUpload();
			break;
		default :
		case 'RUN' :
			$_SESSION['theClass']->run();
			break;
	} // switch
	
	require_once ROOTDIR . '/views/overview.phtml';
