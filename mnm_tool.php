#!/usr/bin/php
<?php

require_once ( 'interface/mixnmatch.php' ) ;

if ( !isset($argv[1]) ) die ( "Needs command\n" ) ;
$command = $argv[1] ;

$mnm = new MixnMatch ( 'interface/config.json' ) ;

if ( $command == 'auto' ) {

	if ( !isset($argv[2]) or !preg_match ( '/^Q\d+$/' , $argv[2] ) ) die ( "Usage: {$argv[0]} {$command} Qxxx\n" ) ;
	$mnm->addAutoMatches ( $argv[2] ) ;

} else if ( $command == 'deauto' ) {

	if ( !isset($argv[2]) or !preg_match ( '/^Q\d+$/' , $argv[2] ) ) die ( "Usage: {$argv[0]} {$command} Qxxx\n" ) ;
	$mnm->removeAutoMatches ( $argv[2] ) ;

} else {

	die ( "Unknown command '{$command}'\n" ) ;

}

#$data = [ 'claims' => [ $mnm->getNewClaimItem('P3','Q10') ] ] ;
#$result = $mnm->doEditEntity ( 'Q10' , $data , 'TESTING' ) ;
#print "{$result->success}\n" ;


?>