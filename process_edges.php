<?php

//stopped at ma

$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads2';

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
		
		$this->States = array('southwest_states');
		
		$this->Error = 0.001;
		$this->Conn->query("truncate southwest_edges");
		
		//$this->createViews();
		
		foreach($this->States as $state){
			$this->findEdges($state);
			
		}
	}
	
	function createViews(){
		foreach($this->States as $state){
			$query1 = "DROP VIEW {$state}";
			$query2 = "CREATE OR REPLACE VIEW from_{$state} AS SELECT * FROM nodes WHERE state = '{$state}' and start = '1'";
			$query3 = "CREATE OR REPLACE VIEW to_{$state} AS SELECT * FROM nodes WHERE state = '{$state}' and start = '0'";
			$result = $this->Conn->query($query1);
			$result = $this->Conn->query($query2);
			$result = $this->Conn->query($query3);
			
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
	
	function findEdges($table){
		$query = "SELECT * FROM {$table}";
				
		$result = $this->Conn->query($query);
		
		if($result){
			while ($row = $result->fetch_array()){
			    echo "\n{$row['id']}=>";
				$endNode = $this->findEndNode($row['id'],$row['end_lat'],$row['end_lon'],$table);
			}
        }else{
            echo "No result";
        }
    }
    
    function findEndNode($fromID,$lat,$lon,$table){
		$query = "SELECT * FROM {$table}
				  WHERE start_lat BETWEEN {$lat}-{$this->Error} AND {$lat}+{$this->Error}
				  AND start_lon BETWEEN {$lon}-{$this->Error} AND {$lon}+{$this->Error}";
		
//		$query = "SELECT * FROM spat_nodes WHERE MBRContains(ST_GeomFromText('Polygon(({$lon}-{$this->Error},{$lat}-{$this->Error},{$lon}+{$this->Error},{$lat}+{$this->Error})'),latlon) AND start = '1'";

		$result = $this->Conn->query($query);
		if($result){
			if(mysqli_num_rows($result) > 0){
				while ($row = $result->fetch_array()){
					$toID = $row['id'];
					echo"$toID ";
					$distance = haversineGreatCircleDistance(floatval($lat), floatval($lon), floatval($row['start_lat']), floatval($row['start_lon']));
					$query = "INSERT INTO southwest_edges VALUES('$fromID','$toID')";
					$this->Conn->query($query);
				}
			}
        }
    }
}

function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthMeanRadius = 3958)
{
    $deltaLatitude = deg2rad($latitudeTo - $latitudeFrom);
    $deltaLongitude = deg2rad($longitudeTo - $longitudeFrom);
    $a = sin($deltaLatitude / 2) * sin($deltaLatitude / 2) +
         cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) *
         sin($deltaLongitude / 2) * sin($deltaLongitude / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthMeanRadius * $c;
}