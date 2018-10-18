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

} else if ( $command == 'create_catalog' ) {

# TODO check params
	$params = [] ;
	$params['label'] = $argv[2] ;
	$params['url'] = $argv[3] ;
	if ( isset($argv[4]) ) $params['wd_prop'] = $argv[4] ;

	$s = new Scraper () ;
	$q = $s->getorCreateCatalogItem ( $params ) ;
	if ( !isset($q) ) die ( "Could not get a catalog ID for ".json_encode($p)."\n") ;
	print "{$q}\n" ;

} else if ( $command == 'scrape' ) {

	$s = new Scraper ( getParamQ() ) ;
	$s->ensureDirectoryStructureForCatalog () ;
	$s->updateURLs () ;
	$s->cachePages () ;

	# Include catalog-specific parser, if any
	$p = $s->getCatalogSpecificIncludePath() ;
	if ( file_exists($p) ) include_once ( $p ) ;

	$s->processPages () ;
	$mnm->syncCatalogWithWikidata ( $s->catalog_q ) ;
	$mnm->addAutoMatches ( $s->catalog_q ) ;

} else if ( $command == 'add_meta' ) {
/*
	$j = $mnm->getSPARQL ( "SELECT ?q { ?q wdt:P3 wd:Q16620 MINUS { ?q wdt:{$mnm->config->props->wd_meta_item} [] } }" ) ;
	foreach ( $j->results->bindings AS $b ) {
		$q = preg_replace ( '|^.+/|' , '' , $b->q->value ) ;
		$data = [ 'claims' => [] ] ;
		$data['claims'][] = $mnm->getNewClaimString($mnm->config->props->wd_meta_item,'Q33999') ;
		$data['claims'][0]['qualifiers'] = [
			$mnm->getNewSnakString ( $mnm->config->props->wd_prop , "P106" ) ,
		] ;
		$result = $mnm->doEditEntity ( $q , $data , "Adding P106:Q33999 metadata" ) ;
	}
*/
} else if ( $command == 'lookup' ) {

	if ( !isset($argv[2]) ) die ( "Missing label to look up for matching\n" ) ;
    $label = implode ( " " , array_slice ($argv,2) );

    # TODO: filter by type if requested
	$items = $mnm->getLabelMatches( $label, false ) ;
	foreach ( $items as $item ) {
        $item = $item->j ;
        $label = getPreferredString( $item->labels ) ;
        $description = getPreferredString( $item->descriptions ) ;

        echo "$label ({$item->id})";
        echo $description ? ": {$description}\n" : "\n";
	}

} else {

	die ( "Unknown command '{$command}'\n" ) ;

}

function getPreferredString( $array ) {
    global $wikidata_preferred_langs;

    foreach ( $wikidata_preferred_langs as $language ) {
        if ( isset($array->$language) ) {
            return $array->$language->value ;
        }
    }

    foreach ( $array as $element ) {
        return $element->value ;
    }

    return '' ;
}

?>
