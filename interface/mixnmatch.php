<?php

class MixnMatch {
	var $config ;

	function __construct ( $config_json_url ) {
		$this->config = json_decode ( file_get_contents ( $config_json_url ) ) ;
	}
} ;

?>