<?php 
function autoloader($class) {
	$dirs = array('classes', 'controllers', 'models');
	foreach ( $dirs as $dir ) {
		$dir = ROOTDIR . "/{$dir}/";
		if ( file_exists($file = ($dir . $class . '.php')) 
				|| file_exists($file = ($dir . ucfirst($class) . '.php')) ) {
			require_once($file);
			if ( class_exists($class) ) {
				return true;
			}
		}
	} // foreach
} // autoloader
spl_autoload_register('autoloader');