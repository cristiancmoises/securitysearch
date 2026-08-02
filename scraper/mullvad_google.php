<?php

class mullvad_google{
	
	public function __construct(){
		
		include "scraper/mullvad.php";
		$this->mullvad = new mullvad("google");
	}
	
	public function getfilters($page){
		
		return $this->mullvad->getfilters($page);
	}
	
	public function web($get){
		
		return $this->mullvad->web($get);
	}
}
