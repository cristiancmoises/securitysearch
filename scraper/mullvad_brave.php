<?php

class mullvad_brave{
	
	public function __construct(){
		
		include "scraper/mullvad.php";
		$this->mullvad = new mullvad("brave");
	}
	
	public function getfilters($page){
		
		return $this->mullvad->getfilters($page);
	}
	
	public function web($get){
		
		return $this->mullvad->web($get);
	}
}
