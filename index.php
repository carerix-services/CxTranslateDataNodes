<?php
	define('ROOTDIR', dirname(__FILE__));
	require_once(ROOTDIR . '/autoload.php');
	set_exception_handler(array('ExceptionController', 'addException'));
	
	session_start();
	
	$sess = Translate::getSession();
	switch ( strtoupper(empty($_REQUEST['verb']) ? 'run' : $_REQUEST['verb']) ) {
		case 'UPLOAD' :
			$sess->showTranslationUpload();
			break;
		default :
		case 'RUN' :
			$sess->run();
			break;
	} // switch
	
	require_once ROOTDIR . '/views/overview.phtml';
