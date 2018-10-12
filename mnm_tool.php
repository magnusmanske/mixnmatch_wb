#!/usr/bin/php
<?php

require_once ( 'interface/mixnmatch.php' ) ;

if ( !isset($argv[1]) ) die ( "Needs command\n" ) ;
$commands = $argv[1] ;

$mnm = new MixnMatch ( 'interface/config.json' ) ;

if ( $command == 'auto' ) {

	$q = $argv[2] ;
	if ( !preg_match ( '/^Q\d+$/' , $q ) ) die ( "Usage: {$argv[0]} {$command} Qxxx\n" ) ;
	$mnm->addAutoMatches ( $q ) ;

} else if ( $command == 'deauto' ) {

	$q = $argv[2] ;
	if ( !preg_match ( '/^Q\d+$/' , $q ) ) die ( "Usage: {$argv[0]} {$command} Qxxx\n" ) ;
	$mnm->addAutoMatches ( $q ) ;

} else {

	die ( "Unknown command '{$command}'\n" ) ;

}

#$data = [ 'claims' => [ $mnm->getNewClaimItem('P3','Q10') ] ] ;
#$result = $mnm->doEditEntity ( 'Q10' , $data , 'TESTING' ) ;
#print "{$result->success}\n" ;


?>