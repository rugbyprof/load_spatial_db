<?php

$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads';

$load = new loadPoints($servername,$username,$password,$db);

class loadPoints{
    var $Conn;
    
    function __construct($servername,$username,$password,$db){
       
		// Create connection
		$this->Conn = new mysqli($servername, $username, $password,$db);

		// Check connection
		if ($this->Conn->connect_error) {
			die("Connection failed: " . $this->Conn->connect_error);
		}
		
		$this->Conn->query("truncate spat_nodes");

		for($i=0;$i<595109;$i+=1000){
			$this->loadPoints($i);
		}
		
	}
	
	function loadPoints($i){
		echo "\n$i\n";
		$query = "SELECT * FROM nodes LIMIT $i , 1000 ";
				
		$result = $this->Conn->query($query);
		
		if($result){
			while ($row = $result->fetch_array()){ 
				$this->loadPoint($row);
			}
			$result->close();
        }
    }
    
    function loadPoint($row){
		$query = "INSERT INTO spat_nodes VALUES ('{$row['id']}', ST_GeomFromText('POINT({$row['lon']} {$row['lat']})'),'{$row['rttype']}','{$row['mtfcc']}','{$row['fullname']}','{$row['state']}','{$row['contiguous_us']}','{$row['start']}')";
		echo '.';
		$result = $this->Conn->query($query);
    }
}