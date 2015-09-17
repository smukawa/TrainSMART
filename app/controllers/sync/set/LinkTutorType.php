<?php
// TA:50 added new class
require_once ('app/controllers/sync/set/Simple.php');
require_once ('app/models/table/LinkTutorType.php');

class SyncSetLinkTutorType extends SyncSetSimple
{

    public function fetchLeftPool()
    {
        $rows = $this->getLeftTable()->fetchAll();
        return $rows;
    }

    public function isReferenced($rd)
    {
        return false; // no references
    }

    function __construct($sourceDbParams, $syncfile_id)
    {
        parent::__construct($sourceDbParams, $syncfile_id, 'link_tutor_tutortype');
    }

    protected function getColumns()
    {
        return array(
            'id_tutor',
                'id_tutortype',
                
        );
    }
    
    public function isDirty($ld,$rd) {
        foreach($this->getColumns() as $col) {
            if ( $ld[$col] != $rd[$col])
                return true;
        }
        return false;
    }
    
    public function isConflict($ld, $rd){
        return false;
    }

    protected function getTable($isLeft = true)
    {
        if ($isLeft) {
            return new LinkTutorType($this->sourceDbParams);
        }
        return new LinkTutorType();
    }

    public function fetchFieldMatch($ld)
    {
        //TA:50 print " fetchFieldMatch(id tutor type): " . @$ld->id_tutor . "; "; // TA:50
        $row = $this->getRightTable()->fetchRow("(id_tutor=" . @$ld->id_tutor . " AND id_tutortype=" . @$ld->id_tutortype . ")");
        if ($row) {
            return $row;
        }
        return null;
    }

    public function fetchHardDeletes(){
        $right_rows = $this->getRightTable()->fetchAll();
        $left_table = $this->getLeftTable()->getAdapter();
        $right_rows_to_delete = array();
		
		$sql = "SELECT * FROM ".$this->tableName;
		
		$left_rows = $left_table->query($sql)->fetchAll();
		foreach($right_rows as $rd) {
		    $found = false;
		    foreach($left_rows as $ld) {
		        if($ld['id_tutor'] === $rd['id_tutor'] && $ld['id_tutortype'] === $rd['id_tutortype']){
		            $found = true;
		            break;
		        }
		        
		    }
		    if(!$found)
		        $right_rows_to_delete []= $rd;	
        }
        return $right_rows_to_delete;
    }

    protected function _map_fk($lItem, $fk, $type)
    {
        return parent::_map_fk($lItem, $fk, $type);
    }
}





