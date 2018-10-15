#!/usr/bin/php
<?php

require_once ( 'interface/mixnmatch.php' ) ;
require_once ( 'scraper.php' ) ;

if ( !isset($argv[1]) ) die ( "Needs command\n" ) ;
$command = $argv[1] ;

$mnm = new MixnMatch ( 'interface/config.json' ) ;

function getParamQ ( $pos = 2 ) {
	global $argv ;
	if ( !isset($argv[$pos]) or !preg_match ( '/^Q\d+$/' , $argv[$pos] ) ) die ( "Usage: {$argv[0]} {$argv[1]} Qxxx\n" ) ;
	return $argv[$pos] ;
}

if ( $command == 'auto' ) {

	$mnm->addAutoMatches ( getParamQ() ) ;

} else if ( $command == 'deauto' ) {

	$mnm->removeAutoMatches ( getParamQ() ) ;

} else if ( $command == 'sync_wd' ) {

	$mnm->syncCatalogWithWikidata ( getParamQ() ) ;

} else if ( $command == 'scrape' ) {

	$s = new Scraper ( getParamQ() ) ;
//	$q = $s->getorCreateCatalogItem ( $p ) ;
//	if ( !isset($q) ) die ( "Could not get a catalog ID for ".json_encode($p)."\n") ;
	$s->ensureDirectoryStructureForCatalog () ;
	$s->updateURLs () ;
	$s->cachePages () ;
	$p = $s->getCatalogSpecificIncludePath() ;
	if ( file_exists($p) ) include_once ( $p ) ;
	$s->processPages () ;
	$mnm->syncCatalogWithWikidata ( $s->catalog_q ) ;
	$mnm->addAutoMatches ( $s->catalog_q ) ;

} else {

	die ( "Unknown command '{$command}'\n" ) ;

}

#$data = [ 'claims' => [ $mnm->getNewClaimItem('P3','Q10') ] ] ;
#$result = $mnm->doEditEntity ( 'Q10' , $data , 'TESTING' ) ;
#print "{$result->success}\n" ;


?>