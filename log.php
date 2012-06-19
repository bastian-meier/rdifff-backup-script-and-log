<?php 
error_reporting(0);

class log_parser{
	
	private $db_host 	= 'localhost';
	private $db_user 	= '';
	private $db_name 	= '';
	private $db_pass 	= '';
	private $db 		= NULL;
	private $timestamp 	= 0;
	private $log 		= '';
	
	function __construct(){
		
		$this->db = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
		$this->timestamp = time();
	}
	
	public function report_mail(){
		
		$this->mail();
	}
	
	public function report(){
	
		echo '<b>log:</b><hr><pre>' . $this->get_log() . '</pre>' ;
		echo '<br><br><b>log-file: </b><hr><br><pre>';
		include "rdiff-backup.log";
		echo '</pre>'; 
	}
	
	
	public function register_event($get_param){
		
		echo '$_GET["e"]: '. $get_param . '<br>';
		$location = substr($get_param,0,1);
		$raw_text = substr($get_param,1);
		
		echo 'location: ' . $location . '(0=server  1=backup)';
		echo '<br>raw_text: ' . $raw_text . '<br>';
		
		$db_id    = $this->store_log($location, $raw_text);
		return $db_id;
	}
	
	public function parse_log_file(){
		
		$log_file = file_get_contents('./rdiff-backup.log', true);
		
		$pos = strpos($log_file, 'Errors 0');

		if ($pos === false) {
		    $log_error = "Fehler beim Backup";
		} else {
		    $log_error = "Backup fehlerfrei";
		}
		
		$this->store_log('0', $log_error);
	}

	private function store_log($location, $raw_text) {
		
		$dbinsert = $this->db->prepare("INSERT INTO logs (location, text, timestamp) VALUES (?,?,?)");
		$dbinsert->bind_param('isi', $location, $text, $this->timestamp);
		$text = $this->format_text($raw_text);
		$dbinsert->execute();
		$db_id = $dbinsert->insert_id;
		$dbinsert->close();
		return $db_id;
		
	}
	
	private function get_log($begin=0, $end=0) {
		
		if($begin==0){
			
			$begin = $this->timestamp - 24.5*60*60;
			
		}
		
		if($end==0){
			
			$end = $this->timestamp;
		}
		
		$dbget = $this->db->prepare("SELECT IF(location=0, 'server', 'backup') as location, text, timestamp FROM logs WHERE timestamp > ? AND timestamp < ? ORDER BY timestamp");
		$dbget->bind_param('ii', $begin, $end);
		$dbget->execute();
		$dbget->store_result();	
		$dbget->bind_result($location, $text, $timestamp);
		while($dbget->fetch()) {
			$this->log .= $this->time2date($timestamp) . ' ' . $location .' : ' . $text . "\n";
		}
		$dbget->close();
		return $this->log;
		
	}
	
	private function time2date ($time){
		
		$date = date('d.m H:i:s', $time);
		return $date;
		
	}
	
	private function format_text($raw_text) {
		
		$text = str_replace ('_', ' ', $raw_text);
		return $text;
		
	}
	
	private function check_errors($begin=0, $end=0){
		
		if($begin==0){
				
			$begin = $this->timestamp - 24.5*60*60;
				
		}
		
		if($end==0){
				
			$end = $this->timestamp;
		}
		
		$dbget = $this->db->prepare("SELECT COUNT(*) AS count FROM logs WHERE timestamp > ? AND timestamp < ? AND text='Fehler beim Backup' ORDER BY timestamp");
		$dbget->bind_param('ii', $begin, $end);
		$dbget->execute();
		$dbget->store_result();
		$dbget->bind_result($count);
		$dbget->fetch();
		$dbget->close();
		return $count;  //0 : kein fehler >0 : fehler
		
	}
	
	private function mail (){
		
		$log = $this->get_log() . "\n\r \n\r http://xyz.de.de/log.php";
		$error_count = $this->check_errors();
		
		if($error_count == 0){
			
			$Name = "backup-log";
			$email = "log@.de";
			$recipient = ""; //recipient
			$subject = "backup-bericht"; //subject
			
		}else{
			
			$Name = "warnung";
			$email = "warnung@.de";
			$recipient = ""; //recipient
			$subject = "Achtung: Fehler beim Backup"; //subject
			
		}
		
		$this->db->close();
		$header = "From: ". $Name . " <" . $email . ">\r\n"; //optional headerfields

		mail($recipient, $subject, $log, $header); //mail command :)
		
		
	}


	}

	$log = new log_parser();
	
	if(isset($_GET["e"])){
		
		// $_GET["e"] ist im Format location als 0 oder 1 und dann direkt der text in dem leerstellen als _ übergeben sind.
		// location:  0 - server    1 - backup
		// bsp.:  1das_ist_ein_test-log
		
		if($_GET["e"]=='0log_kopiert'){
			
			$log->parse_log_file();
		}
		
		echo 'eintrag mit der id <b>' . $log->register_event($_GET["e"]) . '</b> eingetragen';
		
	}elseif(isset($_GET["quiet"])){
		
		$log->report_mail();
		
	}else{
	
		$log->report();
	}

?>
