<?php

$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads';

$loadIt = new loadStateBorders($servername,$username,$password,$db,'state_borders.json');

class loadStateBorders{
    var $Conn;
    var $StartNodes;
    var $Error;
    var $States;
    
    function __construct($servername,$username,$password,$db,$filename){
       
		// Create connection
		$this->Conn = new mysqli($servername, $username, $password,$db);

		// Check connection
		if ($this->Conn->connect_error) {
			die("Connection failed: " . $this->Conn->connect_error);
		}
		
		$this->Borders = array();
		$this->Json = json_decode(file_get_contents($filename));
		
		$this->Conn->query("truncate state_borders");
		
		
		for($i=0;$i<sizeof($this->Json);$i++){
			$poly = "'POLYGON(";
			$num_polys = sizeof($this->Json[$i]->borders);
			$code = $this->Json[$i]->code;
			$name = $this->Json[$i]->name;
			for($j=0;$j<$num_polys;$j++){
				$poly .= $this->MyLineString($this->Json[$i]->borders[$j]);
				if($i<$num_polys - 1){
					$poly .= ',';
				}
			}
			$poly .= ")'";
			echo $code."\n";
			$query = "INSERT INTO state_borders VALUES ('{$code}','{$name}',GeomFromText({$poly}))";
			$result = $this->Conn->query($query);
		}

		
		
//{ "name":"Wyoming", "code":"wy", "borders":[[[-111.137695,45.026951],[-104.029541,45.034710],[-104.040527,40.996483],[-111.115723,40.979897],[-111.137695,45.026951]]] },

	}
	
	function MyLineString($geo){
	    
        for($i=0;$i<sizeof($geo);$i++){
            list($y,$x) = $geo[$i];
        	$geo[$i] = $x." ".$y;
        }
		return "(".implode(' , ',$geo).")";
	}
}