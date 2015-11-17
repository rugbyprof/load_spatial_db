<?php

$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads2';

$create = new createFiles($servername,$username,$password,$db);

class createFiles{
    var $Conn;
    var $StartNodes;
    var $Error;
    var $States;
    
    function __construct($servername,$username,$password,$db){
       
		// Create connection
		$this->Conn = new mysqli($servername, $username, $password,$db);

		// Check connection
		if ($this->Conn->connect_error) {
			die("Connection failed: " . $this->Conn->connect_error);
		}
		
		$query = 
		$result = $this->Conn->query("SELECT * FROM txok_nodes");
		if($result){
			$fp1 = fopen("nodes.csv","w");
			$fp2 = fopen("nodegrometry.json","w");
			while ($row = $result->fetch_assoc()){
				$geometry = $row['geometry'];
				unset($row['geometry']);
				fwrite($fp1,implode(',',$row)."\n");
				$json = array("id"=>$row['id'],"geometry"=>$geometry);
				fwrite($fp2,json_encode($json)."\n");
			}
			fclose($fp1);
			fclose($fp2);
		}
	}
}