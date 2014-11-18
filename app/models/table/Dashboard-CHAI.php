<?php
require_once('Dashboard.php');
require_once('Helper.php');

class DashboardCHAI extends Dashboard
{
	protected $_primary = 'id';

	public function fetchConsumptionDetails($dataName = null, $id, $where = null, $group = null, $useName = null) {
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	    $output = array();
	    
	    $subSelect = new Zend_Db_Expr("(select facility_id, max(date) as C_maxDate from commodity group by facility_id)");

		switch ($dataName) {
		    case 'geo':
		        $select = $db->select()
		        ->from('location');
		        if ($where) // comma seperated string for sql
		            $select = $select->where($where)
		            ->order('location_name');
		        $result = $db->fetchAll($select);
		        
		        foreach ($result as $row){
		        
		          $output[] = array(
		            "id" => $row['id'],
		            "name" => $row['location_name'],
		            "tier" => $row['tier'],
		            "parent_id" => $row['parent_id'],
		            "consumption" => 0,
		            "link" => Settings::$COUNTRY_BASE_URL . "/dashboard/dash3/id/" . $row['id'],
		            "type" => 1
		           );
		        }
		    break;
		    case 'location':
		        $select = $db->select()
		        ->from(array('f' => 'facility'), 
		          array(
		          'f.id as F_id', 
		          'f.facility_name as F_facility_name', 
		          'f.location_id as F_location_id', 
		          'l1.id as L1_id',
		          'l1.location_name as L1_location_name', 
		          'l2.id as L2_id', 
		          'l2.location_name as L2_location_name', 
		          'l2.parent_id as L2_parent_id', 
		          'l3.location_name as L3_location_name', 
		          'ifnull(sum(c.consumption),0) as C_consumption' ))
		        ->joinLeft(array('l1' => "location"), 'f.location_id = l1.id')
		        ->joinLeft(array('l2' => "location"), 'l1.parent_id = l2.id')
		        ->joinLeft(array('l3' => "location"), 'l2.parent_id = l3.id')
		        ->joinLeft(array("c" => "commodity"), 'f.id = c.facility_id')
		        ->joinInner(array('mc' => $subSelect), 'f.id = mc.facility_id and c.date = C_maxDate')
		        ->where($where)
		        ->group($group)
                ->order(array('L3_location_name', 'L2_location_name', 'L1_location_name', 'F_facility_name'));
		        
		        $result = $db->fetchAll($select);
		        
		      foreach ($result as $row){
		          
		        $_child_name = ($id ==  "") ? $row['L2_location_name'] : $row['L1_location_name'] ;
		        
		 	    $output[] = array(
		 	        "id" => $row[$group],
		 	        "name" => $row[$useName],
		 	        "tier" => $row['tier'],
		 	        "parent_id" => $row['L2_parent_id'],
		 	        "child_name" => $_child_name,
		 	        "consumption" => $row['C_consumption'],
		 	        "link" => Settings::$COUNTRY_BASE_URL . "/dashboard/dash3/id/" . $row['l2.parent_id'],
		 	        "type" => 1
		 	     );
		 	    
		 	    //file_put_contents('c:\wamp\logs\php_debug.log', 'Dashboard-CHAI 73 >'.PHP_EOL, FILE_APPEND | LOCK_EX);	ob_start();
		 	    //var_dump('$row[tier]=', $row[tier],"END");
		 	    //var_dump('id=', $id);
		 	    //$result = ob_get_clean(); file_put_contents('c:\wamp\logs\php_debug.log', $result .PHP_EOL, FILE_APPEND | LOCK_EX);
		 	    	
		 	    
		      }
		    break;
		    case 'facility':
		          $select = $db->select()
		          ->from(array('f' => 'facility'), 
		          array(
		          'f.id as F_id', 
		          'f.facility_name as F_facility_name', 
		          'f.location_id as F_location_id', 
		          'l1.id as L1_id',
		          'l1.location_name as L1_location_name', 
		          'l2.id as L2_id', 
		          'l2.location_name as L2_location_name', 
		          'l2.parent_id as L2_parent_id', 
		          'l3.location_name as L3_location_name', 
		          'ifnull(c.consumption,0) as C_consumption' ))
		        ->joinLeft(array('l1' => "location"), 'f.location_id = l1.id')
		        ->joinLeft(array('l2' => "location"), 'l1.parent_id = l2.id')
		        ->joinLeft(array('l3' => "location"), 'l2.parent_id = l3.id')
		        ->joinLeft(array('c' => "commodity"), 'f.id = c.facility_id')
		        ->joinInner(array('mc' => $subSelect), 'f.id = mc.facility_id and c.date = C_maxDate')
		        ->where($where)
                ->order(array('L3_location_name', 'L2_location_name', 'L1_location_name', 'F_facility_name'));
		        
		        $result = $db->fetchAll($select);
		        
		      foreach ($result as $row){
		 	    $output[] = array(
		 	        "id" => $row[$group],
		 	        "name" => $row[$useName],
		 	        "tier" => $row['tier'],
		 	        "parent_id" => $row['L3_parent_id'],
		 	        "child_name" => $row['F_facility_name'],
		 	        "consumption" => $row['C_consumption'],
		 	        "link" => Settings::$COUNTRY_BASE_URL . "/dashboard/dash3/id/" . $row['l2.parent_id'],
		 	        "type" => 1
		 	     );
		      }
		        break;		    
		}
		return $output;
	}
	
	public function fetchAMCDetails($where = null) {
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	    $output = array();
	    
	    $orderClause = new Zend_Db_Expr("`c`.`date` desc limit 12");
	    
	    $select = $db->select()
	    ->from(array('cv' => 'commodity_view_extended_pivot_non_null'),
	        array(
	            'monthname(cv.date) as month',
	            'sum(cv.implant_consumption) as implant_consumption',
	            'sum(cv.injectable_consumption) as injectable_consumption'))
	            ->group(array('monthname(cv.date)'))
	            ->order(array('cv.date'));
	    
	    
	    /*
	    select monthname(date) as month, sum(implant_consumption) as implant_consumption, sum(injectable_consumption) as injectable_consumption
	    from commodity_view_extended_pivot_non_null
	    group by monthname(date)
	    order by date;
	    */
	    
	            
	    $result = $db->fetchAll($select);
	    
	    foreach ($result as $row){
	    
	        $output[] = array(
	            "month" => $row['month'],
	            "implant_consumption" => $row['implant_consumption'],
	            "injectable_consumption" => $row['injectable_consumption']
	        );
	    }
	
	    return $output;
	}
}
?>