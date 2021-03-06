<?php
  /*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
  */

  // no direct access
  defined('EMONCMS_EXEC') or die('Restricted access');

  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  // Creates a feed entry and relates the feed to the user
  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  function create_feed($userid,$name,$NoOfDataFields,$datatype)
  {
    $result = db_query("INSERT INTO feeds (name,status,type,datatype) VALUES ('$name','0','1','$datatype')");				// Create the feed entry
    $feedid = db_insert_id();
    if ($feedid>0) {
      db_query("INSERT INTO feed_relation (userid,feedid) VALUES ('$userid','$feedid')");	        // Create a user->feed relation

      $feedname = "feed_".$feedid;									// Feed name

      if ($NoOfDataFields==1) {										// Create a table with one data field
        $result = db_query(										// Used for most feeds
        "CREATE TABLE $feedname (
	  time INT UNSIGNED, data float,
        INDEX ( `time` ))");
      }

      if ($NoOfDataFields==2) {										// Create a table with two data fields
        $result = db_query(										// User for histogram feed
        "CREATE TABLE $feedname (
	  time INT UNSIGNED, data float, data2 float,
        INDEX ( `time` ))");
      }

      return $feedid;											// Return created feed id
    } else return 0;
  }

  function get_user_feeds($userid)
  {
    $result = db_query("SELECT * FROM feed_relation WHERE userid = '$userid'");
    $feeds = array();
    if ($result) {
      while ($row = db_fetch_array($result)) {
        $feed = get_feed($row['feedid']);
        if ($feed) $feeds[] = $feed;
      }
    }
    usort($feeds, 'compare');		// Sort feeds by tag's
    return $feeds;
  }

  function get_user_feed_ids($userid)
  {
    $result = db_query("SELECT * FROM feed_relation WHERE userid = '$userid'");
    $feeds = array();
    if ($result) {
      while ($row = db_fetch_array($result)) {
        $feeds[]['id'] = $row['feedid'];
      }
    }
    return $feeds;
  }

  function compare($x, $y)
  {
    if ( $x[2] == $y[2] )
     return 0;
    else if ( $x[2] < $y[2] )
     return -1;
    else
     return 1;
  }

  function get_feed($feedid)
  {
    $feed_result = db_query("SELECT * FROM feeds WHERE id = '$feedid'");
    $feed_row = db_fetch_array($feed_result);
    if ($feed_row['status'] != 1) { // if feed is not deleted
      $size = get_feedtable_size($feed_row['id']);
      $feed = array($feed_row['id'],$feed_row['name'],$feed_row['tag'],strtotime($feed_row['time'])*1000,$feed_row['value'],$size, $feed_row['type'], $feed_row['datatype']);
    }
    return $feed;
  }

  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  // Gets a feeds ID from it's name and user ID
  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  function get_feed_id($user,$name)
  {
    $result = db_query("SELECT * FROM feed_relation WHERE userid='$user'");
    while ($row = db_fetch_array($result))
    {
      $feedid = $row['feedid'];
      $result2 = db_query("SELECT name FROM feeds WHERE id='$feedid' AND status = 0");
      $row_name = db_fetch_array($result2);
      if ($name == $row_name['name']) return $feedid;
    }
    return 0;
  }

  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  // Gets a feeds name from its ID
  //----------------------------------------------------------------------------------------------------------------------------------------------------------------
  function get_feed_name($feedid)
  {
    $result = db_query("SELECT name FROM feeds WHERE id='$feedid'");
    if ($result) { $array = db_fetch_array($result); return $array['name']; } 
    else return 0;
  }

  function set_feed_name($feedid,$name)
  {
    db_query("UPDATE feeds SET name = '$name' WHERE id='$feedid'");
  }

  function set_feed_tag($feedid,$tag)
  {
    db_query("UPDATE feeds SET tag = '$tag' WHERE id='$feedid'");
  }

  //---------------------------------------------------------------------------
  // Function feed insert
  // updatetime - is the time value that goes in the feeds table
  // feedtime   - is the time value that goes in the feed_no. table
  //---------------------------------------------------------------------------
  function insert_feed_data($feedid,$updatetime,$feedtime,$value)
  { 
    $feedname = "feed_".trim($feedid)."";

    // a. Insert data value in feed table
    $datetime = date("Y-n-j H:i:s", $feedtime); 
    if (get_feed_type($feedid)==0) $feedtime = $datetime;
    db_query("INSERT INTO $feedname (`time`,`data`) VALUES ('$feedtime','$value')");

    // b. Update feeds table
    $updatetime = date("Y-n-j H:i:s", $updatetime); 
    db_query("UPDATE feeds SET value = '$value', time = '$updatetime' WHERE id='$feedid'");

    return $value;
  }

  function update_feed_data($feedid,$updatetime,$feedtime,$value)
  {                     
    $feedname = "feed_".trim($feedid)."";

    // a. update or insert data value in feed table
    $datetime = date("Y-n-j H:i:s", $feedtime); 
    if (get_feed_type($feedid)==0) $feedtime = $datetime;

    $result = db_query("SELECT * FROM $feedname WHERE time = '$feedtime'");
    $row = db_fetch_array($result);

    if ($row) db_query("UPDATE $feedname SET data = '$value', time = '$feedtime' WHERE time = '$feedtime'");
    if (!$row) {$value = 0; db_query("INSERT INTO $feedname (`time`,`data`) VALUES ('$feedtime','$value')"); }

    // b. Update feeds table
    $updatetime = date("Y-n-j H:i:s", $updatetime); 
    db_query("UPDATE feeds SET value = '$value', time = '$updatetime' WHERE id='$feedid'");
    return $value;
  }

  //---------------------------------------------------------------------------
  // Get all feed data (it might be best not to call this on a really large dataset use function below to select data @ resolution)
  //---------------------------------------------------------------------------
  function get_all_feed_data($feedid)
  {
    $feedname = "feed_".trim($feedid)."";
    $type = get_feed_type($feedid);

    $data = array();   
    $result = db_query("select * from $feedname order by time Desc");
    while($array = db_fetch_array($result))
    {
      if ($type == 0) $time = strtotime($array['time'])*1000;
      if ($type == 1) $time = $array['time']*1000;
      
      $kwhd = $array['data'];    
      $data[] = array($time , $kwhd);
    }
    return $data;
  }

  function get_feed_data($feedid,$start,$end,$oldres,$dp)
  {
    $type = get_feed_type($feedid);
    if ($type == 0) $data = get_feed_data_no_index($feedid,$start,$end,$oldres);
    if ($type == 1) $data = get_feed_data_indexed($feedid,$start,$end,$oldres,$dp);

    return $data;
  }

  function get_feed_data_indexed($feedid,$start,$end,$resolution,$dp)
  {
    if ($dp<2) $dp = 500;

    if ($end == 0) $end = time()*1000;

    $feedname = "feed_".trim($feedid)."";
    $start = $start/1000; $end = $end/1000;

    $result = db_query("SELECT * FROM $feedname LIMIT 1");
    $row = db_fetch_array($result);
    if(!isset($row['data2']))
    {

    //----------------------------------------------------------------------------
    $data = array();
    if (($end - $start) > (5000) && $resolution>1) //why 5000?
    {
      $range = $end - $start;
      $td = $range / $dp;

      for ($i=0; $i<$dp; $i++)
      {
        $t = $start + $i*$td;
        $tb = $start + ($i+1)*$td;
        $result = db_query("SELECT * FROM $feedname WHERE `time` >$t AND `time` <$tb LIMIT 1");

        if($result){
          $row = db_fetch_array($result);
          $dataValue = $row['data'];               
          if ($dataValue!=NULL) { // Remove this to show white space gaps in graph      
            $time = $row['time'] * 1000;     
            $data[] = array($time , $dataValue); 
          } 
        }         
      }
    } else {
      $result = db_query("select * from $feedname WHERE time>$start AND time<$end order by time Desc");
      while($row = db_fetch_array($result)) {
        $dataValue = $row['data'];
        $time = $row['time'] * 1000;  
        $data[] = array($time , $dataValue); 
      }
    }
    //----------------------------------------------------------------------------
    } else {
      // Histogram has an extra dimension so a sum and group by needs to be used.
      $result = db_query("select data2, sum(data) as kWh from $feedname WHERE time>='$start' AND time<'$end' group by data2 order by data2 Asc"); 
	
	    $data = array();                                      // create an array for them
	    while($row = db_fetch_array($result))                 // for all the new lines
	    {
	      $dataValue = $row['kWh'];                           // get the datavalue
	      $data2 = $row['data2'];            		  // and the instant watts
	      $data[] = array($data2 , $dataValue);               // add time and data to the array
	    }
    }

    return $data;
  }

  //---------------------------------------------------------------------------
  // Get feed data - within date range and @ specified resolution
  //---------------------------------------------------------------------------
  function get_feed_data_no_index($feedid,$start,$end,$resolution)
  {
    if ($end == 0) $end = time()*1000;

    $feedname = "feed_".trim($feedid)."";
    $start = date("Y-n-j H:i:s", ($start/1000));		//Time format conversion
    $end = date("Y-n-j H:i:s", ($end/1000));  			//Time format conversion

    // Check to see type of feed table.
    $result = db_query("SELECT * FROM $feedname LIMIT 1");
    $row = db_fetch_array($result);
    if(!isset($row['data2']))
    {
	    //This mysql query selects data from the table at specified resolution
	    if ($resolution>1){
	      $result = db_query(
	      "SELECT * FROM 
	      (SELECT @row := @row +1 AS rownum, time,data FROM ( SELECT @row :=0) r, $feedname) 
	      ranked WHERE (rownum % $resolution = 1) AND (time>'$start' AND time<'$end') order by time Desc");
	    }
	    else
	    {
	      //When resolution is 1 the above query doesnt work so we use this one:
	      $result = db_query("select * from $feedname WHERE time>'$start' AND time<'$end' order by time Desc"); 
	    }
	
	    $data = array();                                     //create an array for them
	    while($row = db_fetch_array($result))             // for all the new lines
	    {
	      $dataValue = $row['data'] ;                        //get the datavalue
	      $time = (strtotime($row['time']))*1000;            //and the time value - converted to unix time * 1000
	      $data[] = array($time , $dataValue);               //add time and data to the array
	    }
    } else {
      // Histogram has an extra dimension so a sum and group by needs to be used.
      $result = db_query("select data2, sum(data) as kWh from $feedname WHERE time>='$start' AND time<'$end' group by data2 order by data2 Asc"); 
	
	    $data = array();                                      // create an array for them
	    while($row = db_fetch_array($result))                 // for all the new lines
	    {
	      $dataValue = $row['kWh'];                           // get the datavalue
	      $data2 = $row['data2'];            		  // and the instant watts
	      $data[] = array($data2 , $dataValue);               // add time and data to the array
	    }
    }
    return $data;
  }

 function get_feed_timevalue($feedid)
 {
    $result = db_query("SELECT time,value FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return $feed;
 }

 function get_feed_value($feedid)
 {
    $result = db_query("SELECT value FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return $feed['value'];
 }

 function get_feed_total($feedid)
 {
    $result = db_query("SELECT total FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return $feed['total'];
 }

 function get_feed_stats($feedid)
 {
    $result = db_query("SELECT * FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return array($feed['id'],$feed['name'],$feed['time'],$feed['value'],$feed['today'],$feed['yesterday'],$feed['week'],$feed['month'],$feed['year']);
 }

  function calc_feed_stats($id)
  {
    $kwhd_table = "feed_".$id;

   $type = get_feed_type($feedid);

    $result = db_query("SELECT * FROM $kwhd_table ORDER BY time DESC");

    $now = time();

    $day7   = $now - (3600*24*7);
    $day30  = $now - (3600*24*30);
    $day365 = $now - (3600*24*365); 

    $sum_day7 = 0; $i7 = 0;
    $sum_day30 = 0; $i30 = 0;
    $sum_day365 = 0; $i365 = 0;
    $i=0;
    $row = db_fetch_array($result); //get rid of today
    while($row = db_fetch_array($result))
    {
      $i++;
    
      if ($type == 0) $time = strtotime($row['time']);
      if ($type == 1) $time = $row['time'];

      $kwhd = $row['data'];

      if ($i==1) { $yesterday = $kwhd; }
      if ($day7<=$time) { $i7++; $sum_day7 += $kwhd; }
      if ($day30<=$time) { $i30++; $sum_day30 += $kwhd; }
      if ($day365<=$time) { $i365++; $sum_day365 += $kwhd; }
    }

    $yesterday = number_format($yesterday,1);
    $av7 = number_format($sum_day7 / $i7,1);
    $av30 = number_format($sum_day30 / $i30,1);
    $av365 = number_format($sum_day365 / $i365,1);
 
    $result = db_query("UPDATE feeds SET yesterday = '$yesterday', week='$av7', month = '$av30', year = '$av365' WHERE id = '$id'");
  }

  function delete_feed($userid,$feedid)
  {
    // feed status of 1 = deleted, this provides a way to soft delete so that if the delete was a mistake it can be taken out of the recycle bin as it where.
    // It would be a good idea to have a hard delete option as well so that one can completely erase data.
    db_query("UPDATE feeds SET status = 1 WHERE id='$feedid'");
    //db_query("DELETE FROM feeds WHERE id = '$feedid'");
    //db_query("DELETE FROM feeds WHERE id = '$feedid'");
    //db_query("DELETE FROM feed_relation WHERE userid = '$userid' AND feedid = '$feedid' LIMIT 1");
    //db_query("DROP TABLE feed_".$feedid);
    //echo "delete feed ".$feedid;
  }

  function feed_belongs_user($feedid,$userid)
  {
    $result = db_query("SELECT * FROM feed_relation WHERE userid = '$userid' AND feedid = '$feedid'");
    $row = db_fetch_array($result);

    if ($row) return 1;
    return 0;
  }

  function get_feedtable_size($feedid)
  {
    $feedname = "feed_".$feedid;
    $result = db_query("SHOW TABLE STATUS LIKE '$feedname'");
    $row = db_fetch_array($result);
    $tablesize = $row['Data_length']+$row['Index_length'];
    return $tablesize;
  }

  function get_user_feeds_size($userid)
  {
    $result = db_query("SELECT * FROM feed_relation WHERE userid = '$userid'");
    $total = 0;
    if ($result) {
      while ($row = db_fetch_array($result)) {
        $total += get_feedtable_size($row['feedid']);
      }
    }

    return $total;
  }

  function get_feed_type($feedid)
  {
    $result = db_query("SELECT type FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return $feed['type'];
  }

  function set_feed_type($feedid,$type)
  {
    db_query("UPDATE feeds SET type = '$type' WHERE id='$feedid'");
  }

  function get_feed_datatype($feedid)
  {
    $result = db_query("SELECT datatype FROM feeds WHERE id='$feedid'");
    $feed = db_fetch_array($result);
    return $feed['type'];
  }

  function set_feed_datatype($feedid,$type)
  {
    db_query("UPDATE feeds SET datatype = '$type' WHERE id='$feedid'");
  }

  function get_all_feeds()
  {
    $result = db_query("SELECT id FROM feeds");
    $feeds = array();

    while ($row = db_fetch_array($result)) {
        $feeds[]['id'] = $row['id'];
    }
    return $feeds;
  }




?>
