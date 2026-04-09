<?php

class ClassPosition{
	var $_position = 0;
	var $_Position_Ends = "0th";

	public function setClassPosition($batchid, $totalscore, $termid = "", $classid = ""){
		include("dbstring.php");

		$batchid = mysqli_real_escape_string($con, (string)$batchid);
		$termid = mysqli_real_escape_string($con, (string)$termid);
		$classid = mysqli_real_escape_string($con, (string)$classid);
		$targetScore = (float)$totalscore;
		$filters = array(
			"sa.batchid='$batchid'",
			"su.systemtype='Student'"
		);

		if($termid !== ""){
			$filters[] = "sa.termname='$termid'";
		}
		if($classid !== ""){
			$filters[] = "sa.classid='$classid'";
		}

		$sql = "SELECT SUM(mk.mark) AS TotalMark
			FROM tblmark mk
			INNER JOIN tblsystemuser su ON mk.userid=su.userid
			INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
			WHERE ".implode(" AND ", $filters)."
			GROUP BY su.userid
			ORDER BY TotalMark DESC";
		$_SQL = mysqli_query($con, $sql);

		if(!$_SQL){
			$this->_position = 0;
			$this->_Position_Ends = "0th";
			return $this->_Position_Ends;
		}

		$position = 1;
		$matched = false;
		while($row = mysqli_fetch_array($_SQL, MYSQLI_ASSOC)){
			$currentScore = (float)$row['TotalMark'];
			if($currentScore > $targetScore){
				$position++;
				continue;
			}
			if(abs($currentScore - $targetScore) < 0.00001){
				$matched = true;
				break;
			}
		}

		if(!$matched){
			$this->_position = 0;
			$this->_Position_Ends = "0th";
			return $this->_Position_Ends;
		}

		$this->_position = $position;
		$this->_Position_Ends = $this->getPositionSuffix($position);
		return $this->_Position_Ends;
	}

	private function getPositionSuffix($position){
		if($position % 100 >= 11 && $position % 100 <= 13){
			return $position.'th';
		}
		switch($position % 10){
			case 1:
				return $position.'st';
			case 2:
				return $position.'nd';
			case 3:
				return $position.'rd';
			default:
				return $position.'th';
		}
	}

	public function getClassPosition(){
		return $this->_Position_Ends;
	}
}

?>
