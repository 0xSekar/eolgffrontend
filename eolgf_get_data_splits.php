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
$today = date('Y/m/d');

$AnnLot = 15;
$QtrLot = 20;

$list = listOfTickers();
$lot = count($list);

var_dump($list);
exit;
foreach($list as $i => $ticker){
    if($i > 25){
        continue;
    }
    echo "Attempting to update ticker: ". $ticker;
    echo date('         H:i:s');
    $chek = ckeckNDown($ticker, $AnnLot, $QtrLot, FALSE, TRUE);
    echo "\n";
    try {
        $res = $db->prepare("UPDATE tickers_split_parser SET updated_date = '".$today."' WHERE (ticker = ? AND  updated_date is null) ");            
        $res->execute(array(strval($ticker)));
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    if($chek){echo "Ticker Correctly Updated \n";}else{echo "Ticker Not Updated \n";};
    echo "\n";
    
}


// --------------------------------- Functions --------------------------------- 

function listOfTickers(){
    $db = Database::GetInstance(); 
    $tickerstoupdate = array();
    $today = date('Y/m/d');
    try {
        $res = $db->prepare("SELECT ticker FROM tickers_split_parser WHERE updated_date is null AND (DATEDIFF('".$today."',tested_for_today) > 2 OR tested_for_today is null)");        
        $res->execute();
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    $tickers = $res->fetchAll(PDO::FETCH_COLUMN);
    $tickers = array_unique($tickers);
    var_dump($tickers);

    foreach ($tickers as $key => $value) {
        $gurufile = downloadguru($value);
        $arrayguru = array_map('str_getcsv', preg_split('/\r*\n+|\r+/', $gurufile));

        if(count($arrayguru)>20){
            foreach($arrayguru as $name => $val) {
                if($val[0] == "EPS (Basic)"){
                    $i = count($val)-1;
                    $EPS = $val[$i];
                    var_dump($EPS);
                    continue 1;
                } 
            }            
            try {
                $res = $db->prepare("SELECT ticker FROM tickers_split_parser WHERE ticker = '".$value."' AND  old_eps != '".$EPS."' ");            
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
                    $res = $db->prepare("UPDATE tickers_split_parser SET tested_for_today = '".$today."' WHERE (ticker = '".$value."' AND  tested_for_today is null) ");            
                    $res->execute();
                } catch(PDOException $ex) {
                    echo "\nDatabase Error"; //user message
                    die("Line: ".__LINE__." - ".$ex->getMessage());
                }
            }
        }else{
            //guru mal nose q hago
            echo "guru error";
        }       
    }
    return $tickerstoupdate;
}

?>
