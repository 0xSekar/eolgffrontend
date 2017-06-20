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

echo " AAME on Blacklist :  \n\n";

echo " List :  \n\n";
var_dump($list);
/*
foreach($list as $i => $ticker){
    if($i > 25){
        continue;
    }
    echo "Attempting to update ticker: ". $ticker;
    echo date('         H:i:s');
    $chek = ckeckNDown($ticker, $AnnLot, $QtrLot, FALSE, FALSE);
    echo "\n";
    if($chek){echo "Ticker Correctly Updated \n";}else{echo "Ticker Not Updated \n";};
    echo "\n";
    
}*/


// --------------------------------- Functions --------------------------------- 

function listOfTickers(){
    $db = Database::GetInstance(); 
    $today = date('Y/m/d');
    try {
        $res = $db->prepare("SELECT ticker_id FROM tickers_control WHERE (DATEDIFF('".$today."',last_eol_date) > 90)");        
        $res->execute();
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    $ids = $res->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $key => $value) {
        try {
            $res = $db->prepare("SELECT ticker FROM tickers WHERE id = '".$value."'");            
            $res->execute();
        } catch(PDOException $ex) {
            echo "\nDatabase Error"; //user message
            die("Line: ".__LINE__." - ".$ex->getMessage());
        }
        $res = $res->fetchAll(PDO::FETCH_COLUMN);
        if(isset($res[0])){
            $tickers[] = $res[0];
        }        
    }    
    foreach ($tickers as $key => $value) {
        try {
                $res = $db->prepare("SELECT ticker FROM osv_blacklist WHERE ticker = '".$value."' ");            
                $res->execute();
            } catch(PDOException $ex) {
                echo "\nDatabase Error"; //user message
                die("Line: ".__LINE__." - ".$ex->getMessage());
            }        
        $res = $res->fetchAll(PDO::FETCH_COLUMN);
        if(!isset($res[0])){
            try {
                $res = $db->prepare("SELECT ticker FROM tickers_proedgard_updates WHERE ticker = '".$value."' AND ((DATEDIFF('".$today."', tested_for_today)>7) OR tested_for_today is null) AND downloaded is null" );           
                $res->execute();
            } catch(PDOException $ex) {
                echo "\nDatabase Error"; //user message
                die("Line: ".__LINE__." - ".$ex->getMessage());
            }
            $res = $res->fetchAll(PDO::FETCH_COLUMN);
            if(isset($res[0])){
                $tickerstoupdate[] = $res[0];
            }else{
                try {
                    $res = $db->prepare("SELECT ticker FROM tickers_proedgard_updates WHERE ticker = '".$value."' " );            
                    $res->execute();
                } catch(PDOException $ex) {
                    echo "\nDatabase Error"; //user message
                    die("Line: ".__LINE__." - ".$ex->getMessage());
                }
                $res = $res->fetchAll(PDO::FETCH_COLUMN);
                if(!isset($res[0])){
                    $tickerstoupdate[] = $value;
                }
            }
        }
    }
    return $tickerstoupdate;
}

?>
