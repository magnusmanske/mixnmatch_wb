<?php

require_once ( 'interface/mixnmatch.php' ) ;

class Scraper {
	public $mnm ;
	public $base_path = './scraper' ;
	public $db ; # SQLite3 database handle
	public $catalog_q ;
	private $ext_id2q = [] ;

	function __construct ( $q = '' ) {
		$this->mnm = new MixnMatch ( 'interface/config.json' ) ;
		if ( $q != '' ) $this->catalog_q = $q ;
	}

	public function getorCreateCatalogItem ( $parameters , $do_create = true ) {
		$conditions = [] ;
		foreach ( $parameters AS $k => $v ) {
			if ( !isset($this->mnm->config->props->$k) ) continue ;
			$conditions[] = "OPTIONAL { ?q wdt:{$this->mnm->config->props->$k} '{$v}' }" ;
		}
		if ( count($conditions) > 0 ) {
			$sparql = "SELECT ?q { " . implode ( ' ' , $conditions ) . " }" ;
			$j = $this->mnm->getSPARQL ( $sparql ) ;
			$results = [] ;
			foreach ( $j->results->bindings AS $b ) {
				if ( !isset($b->q) ) continue ;
				$results[] = preg_replace ( '|^.*/|' , '' , $b->q->value ) ;
			}
			if ( count($results) == 1 ) {
				$this->catalog_q = $results[0] ;
				return $this->catalog_q ;
			}
			if ( count($results) > 1 ) {
				print "Multiple results:\n" ;
				print_r ( $results ) ;
				print "for paremeters:\n" ;
				print_r ( $parameters ) ;
				die ( "using query {$sparql}\n" ) ;
			}
		}
		if ( !$do_create ) return ; // Nothing found, don't create

		# Create new item for catalog
		$data = [ 'claims' => [] ] ;
		$data['claims'][] = $this->mnm->getNewClaimItem($this->mnm->config->props->isa,$this->mnm->config->items->catalog) ;
		if ( isset($parameters['label']) ) {
			$lang = 'en' ;
			if ( isset($parameters['language']) ) $lang = $parameters['language'] ;
			$data['labels'] = [] ;
			$data['labels'][$lang] = [ 'language' => $lang , 'value' => $parameters['label'] ] ;
		}
		foreach ( $parameters AS $k => $v ) {
			if ( !isset($this->mnm->config->props->$k) ) continue ;
			$prop = $this->mnm->config->props->$k ;
			$data['claims'][] = $this->mnm->getNewClaimString($prop,$v) ;
		}
		$result = $this->mnm->doEditEntity ( '' , $data , 'Created by Scraper' ) ;
		return $result->entity->id ;
	}

	public function getCatalogItem ( $parameters ) {
		return $this->getorCreateCatalogItem ( $parameters , false ) ;
	}

	public function ensureDirectoryStructureForCatalog () {
		if ( !file_exists($this->base_path) ) mkdir ( $this->base_path ) ;
		$path = "{$this->base_path}/{$this->catalog_q}" ;
		if ( !file_exists($path) ) mkdir ( $path ) ;
		$this->openOrCreateDatabase ( $this->catalog_q ) ;
	}

	public function openOrCreateDatabase () {
		if ( isset($this->db) ) return $this->db ;
		$db_file = "{$this->base_path}/{$this->catalog_q}/{$this->catalog_q}.sqlite3" ;
		$create_new_database = !file_exists ( $db_file ) ;
		$this->db = new SQLite3 ( $db_file ) ;
		if ( !$create_new_database ) return ;

		$sql_commands = [
			'CREATE TABLE kv ( id INTEGER PRIMARY KEY , k TEXT, v TEXT )' ,
			'CREATE TABLE page_cache ( id INTEGER PRIMARY KEY , url TEXT , params TEXT , contents TEXT , is_downloaded INTEGER , is_processed INTEGER , ts_downloaded TEXT )' ,
			'CREATE UNIQUE INDEX kv_key ON kv ( k )' ,
			'CREATE UNIQUE INDEX url_index ON page_cache ( url )' ,
			"INSERT OR IGNORE INTO kv ( k , v ) VALUES ( 'Q' , '{$this->catalog_q}' )"
		] ;
		foreach ( $sql_commands AS $sql ) $this->exec ( $sql ) ;
		return $this->db ;
	}

	# Runs a SQL command on the sqlite3 database for the catalog
	private function exec ( $sql ) {
		if ( !isset($this->db) ) $this->openOrCreateDatabase() ;
		if ( !isset($this->db) ) die ( "Could not access sqlite3 database\n" ) ;
		if ( !$this->db->exec($sql) ) die ( $this->db->lastErrorMsg()."\n" ) ;
	}

	private function getScraperSetup () {
		$file = "{$this->base_path}/{$this->catalog_q}/scraper.json" ;
		if ( !file_exists($file) ) die ( "No file {$file}\n" ) ;
		$ret = json_decode ( file_get_contents($file) ) ;
		if ( $ret === false ) die ( "File {$file} contains invalid JSON\n" ) ; # Paranoia
		return $ret ; 
	}

	private function constructURLs ( $j , $params , $level ) {
		# Last level?
		if ( $level >= count($j->levels) ) { # Construct URL
			$url = $j->url_pattern ;
			for ( $level_num = 0 ; $level_num < $level ; $level_num++ ) {
				$k = '$L' . ($level_num+1) ; # ATTENTION: This works only up to level 8!
				$v = $params['ids'][$level_num] ;
				$url = str_replace ( $k , $v , $url ) ;
			}
			$this->exec ( "INSERT OR IGNORE INTO page_cache (url,params,is_downloaded,is_processed) VALUES ('".$this->db->escapeString($url)."','".$this->db->escapeString(json_encode($params))."',0,0)" ) ;
			return ;
		}

		# Iterate through
		$l = $j->levels[$level] ;
		if ( $l->type == 'keys' ) {
			foreach ( $l->keys AS $k ) {
				$params['ids'][$level] = $k ;
				$this->constructURLs ( $j , $params , $level+1 ) ;
			}
		} else {
			print_r ( $j ) ;
			print_r ( $params ) ;
			die ( "Unknown type {$l->type} in level {$level}\n" ) ;
		}
	}

	# Generates all URLs for the entry pages
	public function updateURLs () {
		$this->openOrCreateDatabase() ;
		$j = $this->getScraperSetup() ;
		$params = [ 'ids'=>[] ] ;
		$this->constructURLs ( $j , $params , 0 ) ;
	}

	# Wrapper for possibly more complex downloads
	public function downloadURL ( $url ) {
		return file_get_contents ( $url ) ;
	}

	# Reads all pages into the sqlite3 cache, where necessary
	public function cachePages () {
		$this->openOrCreateDatabase() ;
		$sql = "SELECT * FROM page_cache WHERE is_downloaded=0" ;
		$results = $this->db->query ( $sql ) ;
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			$ts = date('c') ;
			$contents = $this->downloadURL ( $row['url'] ) ;
			$contents = base64_encode ( gzcompress ( $contents , 9 ) ) ;
			$this->exec ( "UPDATE page_cache SET contents='{$contents}',is_downloaded=1,is_processed=0,ts_downloaded='{$ts}' WHERE id={$row['id']}" ) ;
		}
	}

	private function loadExternalIDsForCatalog () {
		if ( !isset($this->catalog_q) ) die ( "No catalog set!\n" ) ;
		$sparql = "SELECT DISTINCT ?q ?ext_id { ?q wdt:{$this->mnm->config->props->catalog} wd:{$this->catalog_q} ; wdt:{$this->mnm->config->props->ext_id} ?ext_id }" ;
		$j = $this->mnm->getSPARQL ( $sparql ) ;
		foreach ( $j->results->bindings AS $b ) $this->ext_id2q[$b->ext_id->value] = preg_replace ( '|^.*/|' , '' , $b->q->value ) ;
	}

	# Processes all the downloaded pages, where necessary
	public function processPages () {
		$j = $this->getScraperSetup() ;
		$this->loadExternalIDsForCatalog() ;
		$this->openOrCreateDatabase() ;
		$sql = "SELECT * FROM page_cache WHERE is_downloaded=1 AND is_processed=0" ;
		$results = $this->db->query ( $sql ) ;
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			$this->processPage ( $row , $j ) ;
		}
	}

	private function escapeScraperRegexp ( $r ) {
		$slash = '/' ;
		$r = $slash . str_replace ( $slash , '\\'.$slash , $r ) . $slash ;
		return $r ;
	}

	# Tries individual regular expression patterns
	# Returns the results of the first fitting one, or undefined
	private function parseBlock ( $config , $block ) {
		$regexes = $config->parse->rx_entry ;
		if ( !is_array($regexes) ) $regexes = [ $regexes ] ;
		foreach ( $regexes AS $r ) {
			$r = $this->escapeScraperRegexp ( $r ) ;
			if ( preg_match ( $r , $block , $ret ) ) return $ret ;
		}
	}

	private function fixVariableHTML ( $s ) {
		$s = preg_replace ( '/<.*?>/' , '' , $s ) ;
		$s = preg_replace ( '/&nbsp;/' , ' ' , $s ) ; // Spaces fix
		$s = preg_replace ( '/&#0*39;/' , '\'' , $s ) ; // Quote
		$s = preg_replace ( '/&amp;/' , '&' , $s ) ; // &
		$s = preg_replace ( '/\s+/' , ' ' , $s ) ;
		return trim($s) ;
	}

	# Tries to turn the regexp result from parseBlock into structured results
	private function resolveParsing ( $params , $config , $res ) {
		$ret = [] ;
		foreach ( $config->parse->resolve AS $key => $rules ) {
			$value = $rules->base ;

			# Replace level variables
			foreach ( $params->ids AS $level_num => $level_value ) {
				$k = '$L' . ($level_num+1) ; # ATTENTION: This works only up to level 8!
				$value = str_replace ( $k , $level_value , $value ) ;
			}

			# Replace result variables
			foreach ( $res AS $k => $v ) {
				$k = '$' . $k ;
				if ( !isset($rules->no_html_fix) ) $v = $this->fixVariableHTML ( $v ) ;
				$value = str_replace ( $k , $v , $value ) ;
			}

			if ( isset($rules->rx) ) {
				foreach ( $rules->rx AS $r ) {
					$pattern = $this->escapeScraperRegexp ( $r->pattern ) ;
					$value = preg_replace ( $pattern , $r->replace , $value ) ;
				}
			}

			$ret[$key] = $value ;
		}
		return $ret ;
	}

	# Processes (parses) and individual page
	public function processPage ( $row , $config ) {
		$contents = gzuncompress ( base64_decode ( $row['contents'] ) ) ;
		if ( isset($config->parse->utf8_encode) ) $contents = utf8_encode ( $contents ) ;
		if ( isset($config->parse->simple_space) ) $contents = preg_replace ( '/\s+/' , ' ' , $contents ) ;

		$params = json_decode ( $row['params'] ) ;
		$blocks = [] ;
		if ( isset ( $config->parse->rx_block ) and $config->parse->rx_block != '' ) {
			$r = $this->escapeScraperRegexp ( $config->parse->rx_block ) ;
			preg_match_all ( $r , $contents , $m ) ;
			if (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) { die ( "Backtrack limit was exhausted on {$row['url']}\n" ) ; }
			$blocks = $m[1] ;
		} else $blocks[] = $contents ;

		foreach ( $blocks AS $block ) {
			$res = $this->parseBlock ( $config , $block ) ;
			if ( !isset($res) ) continue ;
			$res = $this->resolveParsing ( $params , $config , $res ) ;
			if ( !isset($res) or !isset($res['id']) or !isset($res['label']) or $res['id']=='' or $res['label']=='' ) continue ;
			if ( function_exists('catalogSpecificParser') ) catalogSpecificParser ( $config , $block , $res ) ;
			$this->createOrUpdateItem ( $res ) ;
		}

		// Mark page cache as processed
		$this->exec ( "UPDATE page_cache SET is_processed=1 WHERE id={$row['id']}" ) ;
	}

	private function createOrUpdateItem ( $item , $do_update = false ) {
		if ( isset($this->ext_id2q[$item['id']]) and !$do_update ) return ; // Already exists
		$lang = $this->getCatalogLanguage() ;
		$data = [ 'claims' => [] , 'labels' => [] ] ;
		$data['labels'][$lang] = [ 'language' => $lang , 'value' => $item['label'] ] ;
		$data['claims'][] = $this->mnm->getNewClaimString($this->mnm->config->props->ext_id,$item['id']) ;
		$data['claims'][] = $this->mnm->getNewClaimItem($this->mnm->config->props->catalog,$this->catalog_q) ;
		if ( isset($item['desc']) and $item['desc'] != '' ) {
			$data['descriptions'] = [] ;
			$data['descriptions'][$lang] = [ 'language' => $lang , 'value' => $item['desc'] ] ;
		}
		if ( isset($item['url']) and $item['url'] != '' ) $data['claims'][] = $this->mnm->getNewClaimString($this->mnm->config->props->ext_url,$item['url']) ;
		if ( isset($item['type']) and $item['type'] != '' ) $data['claims'][] = $this->mnm->getNewClaimString($this->mnm->config->props->type_q,$item['type']) ;
		$result = $this->mnm->doEditEntity ( '' , $data , 'Created by Scraper' ) ;
		if ( !isset($result->entity) ) {
			if ( isset($result->error) ) {
				# "Item with that label/description already exists" - attempt to bypass by adding ID to description
				if ( $result->error->code == 'modification-failed' and preg_match ( '/already has label.+using the same description text/' , $result->error->info ) ) {
					$item['desc'] .= " [{$item['id']}]" ;
					return $this->createOrUpdateItem ( $item , $do_update ) ;
				}
			}
			print_r ( $item ) ;
//			print_r ( $data ) ;
			print_r ( $result ) ;
			die ("PROBLEM\n");
		}
		$this->ext_id2q[$item['id']] = $result->entity->id ;
	}

	public function getCatalogLanguage () {
		return 'en' ; // TODO
	}

	public function getCatalogSpecificIncludePath () {
		if ( !isset($this->catalog_q) ) return '' ;
		return "{$this->base_path}/{$this->catalog_q}/parser.php" ;
	}

} ;

?>