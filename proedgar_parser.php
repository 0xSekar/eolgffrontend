<?php
//Webservice to process alerts coming from pro.edgar sent to gmail through zapier
//Mar 27 2016: Include also the filed date as new field, extracted from subject line
include_once('./config.php');
include_once('./db/db.php');
$db = Database::GetInstance(); 

$emailcontents = $_POST['contents'];
$emailsubject = $_POST['subject'];

$re = "/ticker=(.*?)\"/";
$str = $emailcontents;
preg_match($re, $str, $matches);
$ticker = $matches[1];

$eol_ticker = $ticker; //eol_ticker is to conform to EOL single quote standards, just to download
$eol_ticker = str_replace(".", "'", $eol_ticker);
$eol_ticker = str_replace(",", "'", $eol_ticker);
$eol_ticker = str_replace("/", "'", $eol_ticker);
$eol_ticker = str_replace("-", "'", $eol_ticker);
$eol_ticker = str_replace("'", "'", $eol_ticker);
$appkey = $_REQUEST['appkey'];
$params = array(
    'appkey' => $appkey,
);
$sql = "SELECT * FROM osv_appkey WHERE appkey =:appkey limit 1";
try {
    $result = $db->prepare($sql);
    $result->execute($params);
} catch(PDOException $ex) {
    echo "\nDatabase Error"; //user message
    die("Line: ".__LINE__." - ".$ex->getMessage());
}
$row = $result->fetch();
$key = "";
$key = $row['appkey'];
$s = $row['status'];
if ($key == null OR $key == "" OR empty($key)) {
    echo "Invalid appkey</br>\n";
} else {
    if (strpos($emailsubject, 'filed a NT 10-K') !== true) {        
        $fileddate = trim(get_string_between($emailsubject, 'on', 'at'));        
        $my_date = date('Y-m-d', strtotime($fileddate));
        $params = array(
            'emailsubject' => $emailsubject,
            'ticker' => $ticker,            
            'my_date' => $my_date
        );
        $sql = "INSERT INTO tickers_proedgard_updates (subject, ticker, insdate, downloaded, filed_date, updated_date, tested_for_today, otc)  VALUES  (:emailsubject, :ticker, now(), null, :my_date, null, null, null)";
        try {
            $result = $db->prepare($sql);
            $result->execute($params);
        } catch(PDOException $ex) {
            echo "\nDatabase Error"; //user message
            die("Line: ".__LINE__." - ".$ex->getMessage());
        }        
    }
}

function get_string_between($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0)
        return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
?>