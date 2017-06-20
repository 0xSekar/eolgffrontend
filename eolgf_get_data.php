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

$list = listOfTickers();
$lot = count($list);


echo " T on Blacklist :  \n\n";

echo " List :  \n\n";
var_dump($list);
/*
foreach($list as $i => $ticker){
    if($i > 25){
        continue;
    }
    echo " Attempting to update ticker: ". $ticker;
    echo date('         H:i:s');
    $chek = ckeckNDown($ticker, $AnnLot, $QtrLot);
    echo "\n";
    if($chek){echo " Ticker Correctly Updated \n";}else{echo " Ticker Not Updated \n";};
    echo "\n";
}*/

echo " OTC Tickers: \n";
$list = listOfTickersOTC();
$lot = count($list);

echo " OTC List :  \n\n";
var_dump($list);

/*
foreach($list as $i => $ticker){
    if($i > 25){
        continue;
    }
    echo " Attempting to update ticker: ". $ticker;
    echo date('         H:i:s');
    $chek = ckeckNDown($ticker, $AnnLot, $QtrLot, TRUE);
    echo "\n";    
    if($chek){echo " Ticker Correctly Updated \n";}else{echo " Ticker Not Updated \n";};
    echo "\n";
}*/


// --------------------------------- Functions --------------------------------- 

function listOfTickers(){
    $db = Database::GetInstance(); 
    $today = date('Y/m/d');
    try {
        $res = $db->prepare("SELECT a.ticker FROM tickers_proedgard_updates AS a LEFT JOIN osv_blacklist AS b ON a.ticker = b.ticker WHERE (a.downloaded is null AND b.ticker is null 
            AND 
            (a.subject LIKE '%filed a 20_F %' OR a.subject LIKE '%filed a 20_F/A %' OR a.subject LIKE '%filed a 10_Q %' OR a.subject LIKE '%filed a 10_Q/A %' OR a.subject LIKE '%filed a 10-K %' OR a.subject LIKE '%filed a 10-K/A %') 
            AND (
            (DATEDIFF('".$today."',a.insdate) > 90 AND (a.tested_for_today is null OR (a.tested_for_today is not null AND DATEDIFF('".$today."', a.tested_for_today)>6))) 
            OR 
            ( DATEDIFF('".$today."',a.insdate) <= 90 AND (a.tested_for_today is null OR (a.tested_for_today is not null AND DATEDIFF('".$today."', a.tested_for_today)>1 )))   
            ) 
            AND a.otc != 'Y')");
        
        $res->execute();
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    $row = $res->fetchAll(PDO::FETCH_COLUMN);
    $row = array_unique($row);
    return $row;
}

function listOfTickersOTC(){
    $db = Database::GetInstance(); 
    $today = date('Y/m/d');
    try {
        $res = $db->prepare("SELECT a.ticker FROM tickers_proedgard_updates AS a LEFT JOIN osv_blacklist AS b ON a.ticker = b.ticker WHERE (a.downloaded is null AND b.ticker is null
            AND 
            (a.subject LIKE '%filed a 20_F %' OR a.subject LIKE '%filed a 20_F/A %' OR a.subject LIKE '%filed a 10_Q %' OR a.subject LIKE '%filed a 10_Q/A %' OR a.subject LIKE '%filed a 10-K %' OR a.subject LIKE '%filed a 10-K/A %') 
            AND (
            (DATEDIFF('".$today."',a.insdate) > 90 AND (a.tested_for_today is null OR (a.tested_for_today is not null AND DATEDIFF('".$today."', a.tested_for_today)>6))) 
            OR 
            ( DATEDIFF('".$today."',a.insdate) <= 90 AND (a.tested_for_today is null OR (a.tested_for_today is not null AND DATEDIFF('".$today."', a.tested_for_today)>1 )))   
            ) 
            AND a.otc = 'Y') ");        
        $res->execute();
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    $row = $res->fetchAll(PDO::FETCH_COLUMN);
    $row = array_unique($row);
    return $row;
}

?>
