<?php
set_time_limit(0);                   // ignore php timeout
// Database Connection
include_once('./config.php');
include_once('./db/db.php');

// Tools & functions
include_once('./include/guru.php'); 
include_once('./include/eol.php'); 
include_once('./include/eol_xml_parser.php');
include_once('./include/updater.php');

// Should never be cached - do not remove this
header("Content-type: text/xml; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

$db = Database::GetInstance(); 

$AnnLot = 15;
$QtrLot = 20;

//$ticker = $_REQUEST['ticker'];
$ticker = 'AAPL';

if($ticker!=NULL){
    echo "Attempting to update ticker: ". $ticker;
    echo date('    H:i:s');
    $chek = ckeckNDown($ticker, $AnnLot, $QtrLot, FALSE, TRUE);
    echo "\n";    
    if($chek){echo "Ticker Correctly Updated \n";}else{echo "Ticker Not Updated \n";};
    echo "\n";
}

?>
