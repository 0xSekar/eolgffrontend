<?php
//Webservice to process alerts coming from pro.edgar sent to gmail through zapier
//Mar 27 2016: Include also the filed date as new field, extracted from subject line
include_once('./db/database.php');
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
        $params = array(
            'ticker' => $ticker,
            'eol_ticker' => $eol_ticker
        );
        $query = "select * from eol_cik_ticker_list where ticker =:ticker or ticker =:eol_ticker limit 1";
        try {
            $result = $db->prepare($query);
            $result->execute($params);
        } catch(PDOException $ex) {
            echo "\nDatabase Error"; //user message
            die("Line: ".__LINE__." - ".$ex->getMessage());
        }
        $row = $result->fetch();
        $ticker_lookup = $row['ticker'];
        $cik_code = $row['cik'];
        
        $fileddate = trim(get_string_between($emailsubject, 'on', 'at'));
        
        $my_date = date('Y-m-d', strtotime($fileddate));
        $params = array(
            'emailsubject' => $emailsubject,
            'ticker' => $ticker,
            'ticker_lookup' => $ticker_lookup,
            'cik_code' => $cik_code,
            'my_date' => $my_date
        );
        $sql = "INSERT INTO eol_proedgar_email (subject, ticker, insdate, ticker_lookup, cik_code, downloaded,filed_date)  VALUES  (:emailsubject, :ticker, now(),:ticker_lookup, :cik_code,null,:my_date)";
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
