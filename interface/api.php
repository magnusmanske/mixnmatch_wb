<?php

header('Content-Type: application/json');

require_once ( 'mixnmatch.php' ) ;

$mnm = new MixnMatch () ;

# SPARQL proxy
if ( isset($_REQUEST['query']) ) {
	$url = $mnm->config->sparql_url . '?format=json&query=' . urlencode($_REQUEST['query']) ;
	print file_get_contents ( $url ) ;
	exit(0);
}


$out = [ 'status' => 'OK' ] ;

//if ( isset($_REQUEST['callback']) ) print $_REQUEST['callback'].'(' ;
print json_encode($out) ;
//if ( isset($_REQUEST['callback']) ) print ')' ;
exit(0);

?>