<?php

//	Program Name:		chart.php
//	Program Title:		Chart
//	Created by:			
//	Template family:	Arctic
//	Template name:		SmartChart Graph.tpl
//	Purpose:        	Build a simple SmartChart
//	Program Modifications:


require_once('/include/WebSmartObject.php');
require_once('/include/xl_functions.php');

class report extends WebSmartObject
{
	protected $filters = array('CMNAME' => '');
	
	// Find more configuration values for the SmartCharts at:
	// https://docs.fusioncharts.com/archive/3.15.2/chart-guide/list-of-charts
	protected $chartConfig = array(
		
		// This is a list of the themes that this template supports.
		// All themes except the one you chose in the wizard are commented out.
		// If you wish to switch to a different theme, comment out the currently selected theme and then remove the comment markers from the one you want to try.
		// "theme" => "candy",
		// "theme" => "carbon",
		"theme" => "fusion",
		// "theme" => "gammel",
		// "theme" => "ocean",
		// "theme" => "umber",
		// "theme" => "zune",
		"xAxisName" => "CMNAME",
		"yAxisName" => "OHOTOT",
		"caption" => "Report",
		"subCaption" => "",
		"showValues" => "1",
		"showLabels" => "1",
		"decimals" => "0",
		"forceDecimals" => "1",
		"formatNumberScale" => "0",
		"numberPrefix" => "",
		"numberSuffix" => "",
		"decimalSeparator" => ".",
		"rotateLabels" => "0",
		"yAxisMinValue" => "",
		"yAxisMaxValue" => ""
	);
	
	protected $grWidth = 700;
	protected $grHeight = 400;
	
	
	
	// This is a list of the graph styles that this Clover template supports.
	// All styles except the one you chose in the wizard are commented out. 
	// If you wish to switch to a different graph style, comment out the currently selected style and then remove the comment markers from the one you want to try.
	// protected $grType = "Column3D"; // (3D Bar)
	protected $grType = "Column2D"; // (2D Bar)
	// protected $grType = "Line"; // (Line)
	// protected $grType = "Pie3D"; // (3D Pie)
	// protected $grType = "Pie2D"; // (2D Pie)
	// protected $grType = "Pyramid"; // (Pyramid)
	// protected $grType = "Funnel"; // (Funnel)
	
	
	public function runMain()
	{
		// Connect to the database
		try
		{
			$this->db_connection = new PDO(
			'ibm:' . $this->defaults['pf_db2SystemName'],
			$this->defaults['pf_db2UserId'],
			$this->defaults['pf_db2Password'],
			$this->defaults['pf_db2PDOOptions']
			);
		}
		catch (PDOException $ex)
		{
			die('Could not connect to database: ' . $ex->getMessage());
		}
		
		// Run the specified task
		switch ($this->pf_task)
		{
			
			case 'filter':
			$this->filter();
			break;
			// Display the Page
			case 'default':
			$this->displayPage();
			break;
		}
	}
	
	// Display the page
	protected function displayPage()
	{
		$this->writeSegment('MainSeg', array_merge(get_object_vars($this), get_defined_vars()));
	}
	
	
	// Load list with filters
	protected function filter()
	{
		// Retrieve the filter information
		$this->filters['CMNAME'] = xl_get_parameter('filter_CMNAME');
		
		$_SESSION[$this->pf_scriptname] = $this->filters;
		
		$this->displayPage();
	}
	
	
	// Output the JSON for the SmartChart.
	protected function getReportJson()
	{
		if (isset($_SESSION[$this->pf_scriptname]))
		{
			$this->filters = $_SESSION[$this->pf_scriptname];
		}
		
		// Create and execute the list Select statement
		$stmt = $this->buildListStmt();
		if (!$stmt)
		{
			$this->dieWithPDOError($stmt);
		}
		
		// Fetch the first row for page
		$result = $stmt->execute();
		if ($result === false)
		{
			$this->dieWithPDOError($stmt);
		}
		$rows = $stmt->fetchAll();
		
		// Build the chart array.
		$chart = array(
			"chart" => $this->chartConfig,
			"data" => array());
		
		$numRows = 0;
		
		$data = array();
		foreach ($rows as $row)
		{
			$key = $row["CMNAME"];
			$value = $row["OHOTOT"];
			if (!isset($data[$key])) {
				$data[$key] = array(
					"label" => "",
					"value" => 0
				);
			}
			$data[$key]["label"] = $key;
			$data[$key]["value"] = $data[$key]["value"] + $value;
			
			$numRows++;
		}
		
		foreach($data as $key=>$value)
		{
			$chart["data"][] = $value;
		}
		
		echo json_encode($chart);
	}
	
	// Build the List statement
	protected function buildListStmt()
	{
		// Build the query with parameters
		$selString = $this->buildSelectString();
		$selString .= ' ' . $this->buildWhereClause();
		$selString .= ' ORDER BY CMNAME';
		$selString .= ' FOR FETCH ONLY';
		// Prepare the statement
		$stmt = $this->db_connection->prepare($selString);
		if (!$stmt)
		{
			return $stmt;
		}
		
		// Bind the filter parameters
		if ($this->filters['CMNAME'] != '')
		{
			$stmt->bindValue(':CMNAME', '%' . $this->filters['CMNAME'] . '%', PDO::PARAM_STR);
		}
		
		return $stmt;
	}
	
	// Build SQL Select string
	protected function buildSelectString()
	{
		$selString = 'SELECT  MU_CUSTF.`CMNAME`, MU_ORDHF.`OHOTOT` FROM MU_ORDHF inner join MU_CUSTF on test.MU_ORDHF.OHCUST = test.MU_CUSTF.CMCUST';
		
		return $selString;
	}
	
	// Build where clause to filter rows from table
	protected function buildWhereClause()
	{
		$whereClause = '';
		$link = 'WHERE ';
		
		// Filter by CMNAME
		if ($this->filters['CMNAME'] != '')
		{
			$whereClause = $whereClause . $link . 'MU_CUSTF.`CMNAME` LIKE :CMNAME';
			$link = " AND ";
		}
		
		return $whereClause;
	}
	
	// Output the last PDO error, and exit
	protected function dieWithPDOError($stmt = false)
	{
		if ($stmt)
		{
			$err = $stmt->errorInfo();
		}
		else
		{
			$err = $this->db_connection->errorInfo();
		}
		die('<b>Error #' . $err[1] . ' - ' . $err[2] . '</b>');
	}
 

	function writeSegment($xlSegmentToWrite, $segmentVars=array())
	{
		foreach($segmentVars as $arraykey=>$arrayvalue)
		{
			${$arraykey} = $arrayvalue;
		}
		// Make sure it's case insensitive
		$xlSegmentToWrite = strtolower($xlSegmentToWrite);

	// Output the requested segment:

	if($xlSegmentToWrite == "mainseg")
	{

		echo <<<SEGDTA
<!DOCTYPE html>
<html>
  <head>
    <meta name="generator" content="WebSmart" />
    <meta http-equiv="Pragma" content="no-cache" />
    <title>Report</title>
    
    <link rel="stylesheet" type="text/css" href="/css/jquery-ui.css"/>
    <link rel="stylesheet" type="text/css" href="/css/screen.css" media="all" />
    <link rel="stylesheet" type="text/css" href="/css/print.css" media="print" />
    <link rel="stylesheet" type="text/css" href="/css/noheader.css" media="all" />
    <script type="text/javascript" src="/js/jquery.min.js"></script>
    <script type="text/javascript" src="/js/jquery-ui.min.js"></script>
    <script type="text/javascript" src="/smartcharts/smartcharts.js"></script>
    <script type="text/javascript" src="/smartcharts/themes/fusioncharts.theme.
SEGDTA;
 echo $this->chartConfig["theme"]; 
		echo <<<SEGDTA
.js"></script>
    <script type="text/javascript">
	jQuery(function() {
    	jQuery("input:submit").button();
    });
    </script>
  </head>
  <body class="chart">
    <div id="outer-content">
      <div id="page-title-block" class="page-title-block">
        <img class="page-title-image" src="/websmart/v13.0/Arctic/images/company-logo.png" alt="logo" />
        <div id="page-divider-top" class="page-divider"></div>
      </div>
      <div id="page-content">
        <div id="contents">
          <form id="promptform" class="filter" method="post" action="$pf_scriptname">
            <input id="task" type="hidden" name="task" value="filter" />
            <div id="filters">
              <span class="filter-group">
                <label for="filter_CMNAME">CMNAME</label>
                <input id="filter_CMNAME" class="filter" type="text" name="filter_CMNAME" maxlength="50" value="{$this->filters['CMNAME']}"/>
              </span>
              <input id="promptbutton" class="filter-button" type="submit" value="Submit" />
            </div>
          </form>
          <div class="clear-layout"></div>
          <div id="chartContainer">
            <script type="text/javascript">
				// Create an Instance with chart options
				var chartInstance = new SmartCharts({
					type: '{$this->grType}',
					width: '{$this->grWidth}',
					height: '{$this->grHeight}',
					dataFormat: 'json',
					dataSource: 
SEGDTA;
 $this->getReportJson(); 
		echo <<<SEGDTA

				});
				// Render the chart
				chartInstance.render("chartContainer");
			</script>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>

SEGDTA;
		return;
	}

		// If we reach here, the segment is not found
		echo("Segment $xlSegmentToWrite is not defined! ");
	}

	// return a segment's content instead of outputting it to the browser
	function getSegment($xlSegmentToWrite, $segmentVars=array())
	{
		ob_start();
		
		$this->writeSegment($xlSegmentToWrite, $segmentVars);
		
		return ob_get_clean();
	}
	
	function __construct()
	{
		
		$this->pf_liblLibs[1] = 'test';
		
		parent::__construct();

		$this->pf_scriptname = 'chart.php';
		$this->pf_wcm_set = '';
		
		
		$this->xl_set_env($this->pf_wcm_set);
		
	}
}
