<?php

$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads';

$process = new processEdges($servername,$username,$password,$db);

class processEdges{
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
		
		$this->States = array();
		$this->load_states();
		
		$this->Error = 0.002;
		$this->Conn->query("truncate edges");
		
		//$this->createViews();
		
		foreach($this->States as $state){
			$this->findEdges($state);
			
		}
	}
	
	function createViews(){
		foreach($this->States as $state){
			$query = "CREATE OR REPLACE VIEW {$state} AS SELECT * FROM nodes WHERE state = '{$state}'";
			$result = $this->Conn->query($query);
			
		}
    }
	
	function load_states(){
		$result = $this->Conn->query("SELECT DISTINCT(state) as state FROM nodes");
		if($result){
			while ($row = $result->fetch_array()){
				$this->States[] = $row['state'];
			}
		}
	}
	
	
// SET @x = $lon;
// SET @y = $lat;
// SELECT *
// FROM nodes
// WHERE lat BETWEEN {$lat}-0.5 AND {$lat}+0.5
// AND lon BETWEEN {$lon}-0.5 AND {$lon}+0.5;
	
	function findEdges($state){
		$query = "SELECT * FROM {$state}
				WHERE contiguous_us = 'Y'
				AND  start = '0'
				ORDER BY id asc";
				
		$result = $this->Conn->query($query);
		
		if($result){
			while ($row = $result->fetch_array()){
			    echo "\n{$row['id']}=>";
				$endNode = $this->findEndNode($row['id'],$row['lat'],$row['lon'],$state);
			}
        }else{
            echo "No result";
        }
    }
    
    function findEndNode($fromID,$lat,$lon,$state){
		$query = "SELECT * FROM {$state}
				  WHERE lat BETWEEN {$lat}-{$this->Error} AND {$lat}+{$this->Error}
				  AND lon BETWEEN {$lon}-{$this->Error} AND {$lon}+{$this->Error}
				  AND start = '1'";
		
//		$query = "SELECT * FROM spat_nodes WHERE MBRContains(ST_GeomFromText('Polygon(({$lon}-{$this->Error},{$lat}-{$this->Error},{$lon}+{$this->Error},{$lat}+{$this->Error})'),latlon) AND start = '1'";

		$result = $this->Conn->query($query);
		if($result){
			if(mysqli_num_rows($result) > 0){
				while ($row = $result->fetch_array()){
					$toID = $row['id'];
					echo"$toID ";
					$query = "INSERT INTO edges VALUES('$fromID','$toID')";
					$this->Conn->query($query);
				}
			}
        }
    }
}