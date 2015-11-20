<?php
error_reporting(1);
//       "geometry": {
//         "type": "LineString",
//         "coordinates": [
//           [
//             -88.019893,
//             44.519309
//           ],
//           [
//             -88.01937699999999,
//             44.520126
//           ],
//           [
//             -88.01880799999999,
//             44.520931999999995
//           ],
//           [
//             -88.01849,
//             44.521383
//           ],
//           [
//             -88.018104,
//             44.521981
//           ],
//           [
//             -88.018,
//             44.522124999999996
//           ],
//           [
//             -88.01732799999999,
//             44.523055
//           ],
//           [
//             -88.016598,
//             44.524097999999995
//           ]
//         ]
//       },
//       "type": "Feature",
//       "properties": {
//         "RTTYP": "M",
//         "MTFCC": "S1200",
//         "FULLNAME": "N Broadway",
//         "LINEARID": "110488653292"
//       }


$servername = "localhost";
$username = "root";
$password = "rugger31";
$db = 'us_roads2';


//SET @v = - 2.5;
// > SELECT *
// > FROM city
// > WHERE city.lat BETWEEN @x-0.5 AND @x+0.5
// > AND city.lng BETWEEN @y-0.5 AND @y+0.5;


$p = new processJson('./json',$servername,$username,$password,$db);

//$p->dumpDir();
//$p->processDir();
//$p->processFile('./json/tl_2013_38_prisecroads.json');





class FipsCodeHelper{
    var $Fips;
    function __construct($filename){
       for($i=0;$i<=78;$i++){
       	  $this->Fips[$i] = 0;
       }

       //Process fips file to get proper state codes
	    $fipsFile = file($filename);

		for($i=0;$i<sizeof($fipsFile);$i++){
		//AK,2,ALASKA,02,2,2,AK02,AK-02,0
			 list($abbr,$fips,$state,$n1,$n2,$n3,$n4,$n5,$contiguous) = explode(',',trim($fipsFile[$i]));
			 $this->Fips[$fips] = array('abbr'=>$abbr,'fips'=>$fips,'state'=>$state,'contiguous'=>$contiguous);
		}

		print_r($this->Fips);
    }

    function getFipsValue($keyName,$token){
    	if(gettype($token) == 'integer'){
    		return $this->Fips[$token][$keyName];
    	}

    	for($i=0;$i<sizeof($this->Fips);$i++){
    	    if($this->Fips[$i]['abbr'] == $token)
    	    	return $this->Fips[$i][$keyName];
    	    if($this->Fips[$i]['fips'] == $token)
    	    	return $this->Fips[$i][$keyName];
    	    if($this->Fips[$i]['state'] == $token)
    	    	return $this->Fips[$i][$keyName];
    	}
    	return "not found";
    }
}



class processJson{
    var $Dir;
    var $DirName;
    var $fp;
    var $i;
    var $fips;
    var $UUID;
    var $Conn;

    function __construct($dirname,$servername,$username,$password,$db){
       $temp = scandir($dirname);
       array_shift($temp);
       array_shift($temp);

       $this->UUID = 0;
       $this->DirName = $dirname;

       //Make sure DirName has trailing slash
       $this->DirName = rtrim($this->DirName,"/ ")."/";

		// Create connection
		$this->Conn = new mysqli($servername, $username, $password,$db);


		$this->Fips = new FipsCodeHelper('state-fips.csv');

		// Check connection
		if ($this->Conn->connect_error) {
			die("Connection failed: " . $this->Conn->connect_error);
		}

        //$this->Conn->query("truncate nodes");
        $this->Conn->query("truncate road_segments");
        $this->processDir($this->DirName);
    }



    function processDir($dirname){
    	$temp = scandir($dirname);
    	array_shift($temp);
    	array_shift($temp);

    	$files = array();
        foreach($temp as $file){
           $path_parts = pathinfo($file);
           if($path_parts['extension'] == 'json' && substr($path_parts['filename'],0,2) == 'tl'){
           	  $files[$dirname.$file] = filesize($dirname.$file);
           }
        }

    	foreach($files as $file => $size){
    	    echo"\nProcessing: {$file}\n";
    	    $this->processFile($file);
    	}
    }

    function processFile($filename){
    	$fips_index = $this->getFipsIndexFromFileName($filename);
    	$abbr = $this->Fips->getFipsValue('abbr',$fips_index);
    	echo "\nAbbr:".$abbr."\n";
    	if($abbr){
			$this->fp = fopen($filename,'r');
			while($obj = $this->getObject()){
				if(is_array($obj)){
					$this->processObject($obj,$abbr);
					echo ".";
				}
			}
			fclose($this->fp);
    	}
    }


    function processObject($obj,$state){

    	$startLat = $obj['linestring'][0][1];
    	$startLon = $obj['linestring'][0][0];
    	$endLat = $obj['linestring'][sizeof($obj['linestring'])-1][1];
    	$endLon = $obj['linestring'][sizeof($obj['linestring'])-1][0];

    	$geometry = json_encode($obj['linestring']);

    	$rttype = $obj['properties']['RTTYP'];
    	$mftfcc = $obj['properties']['MFTFCC'];
    	$fullname = $obj['properties']['FULLNAME'];

    	$contiguous = $this->Fips->getFipsValue('contiguous',$state);

    	$id = $this->getUUID();

    	$distance = $this->calcDistance($obj['linestring']);

      $bearing = $this->calcBearing($obj['linestring']);

		$query1 = "INSERT INTO road_segments VALUES ('{$id}', '{$startLat}', '{$startLon}', '{$endLat}', '{$endLon}','{$rttype}', '{$mftfcc}', '{$fullname}', '{$state}','{$contiguous}' ,'{$distance}','{$bearing}','{$geometry}')";


		$this->Conn->query($query1);
    }

    function getUUID(){
    	$id = $this->UUID;
    	$this->UUID++;
    	return $id;
    }

    function getFipsIndexFromFileName($filename){
    	 $parts = explode('_',$filename);
    	 $index = $parts[2] * 1;
    	 return $index;
    }

    function calcDistance($points){
    	$distance = 0;
    	for($i=0;$i<sizeof($points)-1;$i++){
    		$distance += haversineGreatCircleDistance($points[$i][1], $points[$i][0], $points[$i+1][1], $points[$i+1][0]);
    	}
    	return $distance;
    }

    function calcBearing($points){
    	$bearing = 0;
    	for($i=0;$i<sizeof($points)-1;$i++){
    		$bearing += bearing($points[$i][1], $points[$i][0], $points[$i+1][1], $points[$i+1][0]);
    	}
    	return ($bearing /  sizeof($points)-1);
    }


    function getObject(){
    	 $objectEnd = false;
    	 $objectArray = array();
    	 $readPoints = false;
    	 $readFeature = false;
    	 $properties = null;

		 while($objectEnd == false){
		 	 $line = fgets($this->fp);
		 	 if(feof($this->fp)){
		 	 	return false;
		 	 }
			 if(strpos($line,"geometry")){
				 //kill next two lines
				 $null = fgets($this->fp);
				 $null = fgets($this->fp);
				 $readPoints = true;
			 }
			 while($readPoints){
				 $null = fgets($this->fp);
				 $x = trim(fgets($this->fp)," ,\n");
				 $y = trim(fgets($this->fp));
				 $objectArray['linestring'][] = array($x,$y);
				 $last = fgets($this->fp);
				 if(!strpos($last,","))
					$readPoints = false;
					$readFeature = true;
			 }
			 if($readFeature){
			     $null = fgets($this->fp);
			     $null = fgets($this->fp);
			     $null = fgets($this->fp);
			     $null = fgets($this->fp);

				 list($null,$properties['RTTYP']) = explode(":",trim(fgets($this->fp)," ,\n"));
				 list($null,$properties['MFTFCC']) = explode(":",trim(fgets($this->fp)," ,\n"));
				 list($null,$properties['FULLNAME']) = explode(":",trim(fgets($this->fp)," ,\n"));
				 list($null,$properties['LINEARID']) = explode(":",trim(fgets($this->fp)," ,\n"));
	  		     $objectEnd = true;
	         }
             if($properties){
				 foreach($properties as $key => $val){
					$properties[$key] = str_replace('"', '', $val);
				 }
				 $objectArray['properties'] = $properties;
				 return($objectArray);
			 }
			 return 1;
		}

    }

}

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return ($angle * $earthRadius) / 3.28084;   //convert to feet
}


function bearing($lat1,$lng1,$lat2,$lng2){
   $phi1 = deg2rad($lat1);
   $phi2 = deg2rad($lat2);
   $lngDiff = deg2rad($lng2-$lng1);

    // see http://mathforum.org/library/drmath/view/55417.html
   $y = sin($lngDiff) * cos($phi2);
   $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($lngDiff);
   $theta = atan2($y, $x);

   return (rad2deg($theta)+360) % 360;
}
