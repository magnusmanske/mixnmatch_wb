<?php

header('Content-Type: application/json');

# SPARQL proxy
if ( isset($_REQUEST['query']) ) {
	$url = 'https://mixnmatch-query.wmflabs.org/proxy/wdqs/bigdata/namespace/wdq/sparql?format=json&query=' . urlencode($_REQUEST['query']) ;
	print file_get_contents ( $url ) ;
	exit(0);
}


require_once ( 'mixnmatch.php' ) ;

$out = [ 'status' => 'OK' ] ;

//if ( isset($_REQUEST['callback']) ) print $_REQUEST['callback'].'(' ;
print json_encode($out) ;
//if ( isset($_REQUEST['callback']) ) print ')' ;
exit(0);

?>