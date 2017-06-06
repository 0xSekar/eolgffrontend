<?php
if (!function_exists('str_getcsv')) {

    function str_getcsv($input, $delimiter = ',', $enclosure = '"') {

        if (!preg_match("/[$enclosure]/", $input)) {
            return (array) preg_replace(array("/^\\s*/", "/\\s*$/"), '', explode($delimiter, $input));
        }

        $token = "##";
        $token2 = "::";
        $t1 = preg_replace(array("/\\\[$enclosure]/", "/$enclosure{2}/",
            "/[$enclosure]\\s*[$delimiter]\\s*[$enclosure]\\s*/", "/\\s*[$enclosure]\\s*/"), array($token2, $token2, $token, $token), trim(trim(trim($input), $enclosure)));

        $a = explode($token, $t1);
        foreach ($a as $k => $v) {
            if (preg_match("/^{$delimiter}/", $v) || preg_match("/{$delimiter}$/", $v)) {
                $a[$k] = trim($v, $delimiter);
                $a[$k] = preg_replace("/$delimiter/", "$token", $a[$k]);
            }
        }
        $a = explode($token, implode($token, $a));
        return (array) preg_replace(array("/^\\s/", "/\\s$/", "/$token2/"), array('', '', $enclosure), $a);
    }

}

function parseguru($data, $fechaeol, $AnnLot, $QtrLot) {     
    $iniline = 0;
    $endline = 0;
    $cnt = 0;

    foreach ($data as $line) {
        if ($line[0] == 'Fiscal Period') {
            $iniline = $cnt;
        }
        if ($line[0] == 'Restated Filing Date') {
            $endline = $cnt;            
        }
        $cnt++;
    }

    if($data[$iniline][1] == "TTM/current") return FALSE;   

    //Getting parametters
    $tam = count($data[$iniline]); 
    $c = 0;
    foreach($data[$iniline] as $line){        
        if($line == "TTM/current"){
            $finANN = $c; // Last Ann on Guru, Newest
            $iniQtr = $tam-$QtrLot; //First Qtr on Guru, Oldest is c+2 
            $finQtr = $tam-1; //Last Qtr on Guru, Newest
            $ANN = $c-1; // Quantity of Annuals on Guru
            $iniANN = $ANN-$AnnLot; //5 if 20 Annuals, 15 if 30 Annuals, Annuals that we dont care
            break;
        }
        $c++;
    }

    //Dates checking
    $fechaguru = date("Y-m-d", strtotime($data[$endline][$ANN]));

    if($fechaguru !== $fechaeol) {
        $ANN = $ANN-1;
        $iniANN = $iniANN-1; //4
        $finANN = $finANN-1; //20
    }

    // ANNUALS
    for ($j = 0; $j <= $ANN; $j++) {
        for ($i = $iniline; $i <= $endline; $i ++) {
            $currentvalue = $data[$i][$j];            
            $currentname = $data[$i][0];

            $currentname = str_replace('-', '', preg_replace('/[^A-Za-z0-9\-\']/', '', $currentname));
            $currentvalue = str_replace("&", "and", $currentvalue);
            $currentvalue = str_replace("'", " ", $currentvalue);
            if (strlen($currentvalue) == 1) {
                $currentvalue = str_replace("-", "0", $currentvalue);
            }
            $currentname = str_replace("&", "and", $currentname);
            $currentname = str_replace("'", "", $currentname);
            if ($currentname == 'Shares Outstanding (Diluted Average)' OR $currentname == 'SharesOutstandingDilutedAverage') {
                $currentname = 'SharesOutstandingDiluted';
            }
            if ($currentname == 'Shares Outstanding (Basic Average)' OR $currentname == 'SharesOutstandingBasicAverage') {
                $currentname = 'SharesOutstandingBasic';
            }

            $data[$i][$j] = $currentvalue;
            $data[$i][0] = $currentname;           
        }        
    }

    // QUARTERS
    for ($j = $iniQtr; $j <= $finQtr; $j++) { //for ($j = 63; $j <= 102; $j++) {
        for ($i = $iniline; $i <= $endline; $i ++) {

            $currentvalue = $data[$i][$j];
            $currentname = $data[$i][0];

            $currentname = preg_replace('/[^A-Za-z0-9\-\']/', '', $currentname);
            $currentvalue = str_replace("&", "and", $currentvalue);
            $currentvalue = str_replace("'", " ", $currentvalue);
            if (strlen($currentvalue) == 1) {
                $currentvalue = str_replace("-", "0", $currentvalue);
            }
            $currentname = str_replace("&", "and", $currentname);
            $currentname = str_replace("'", "", $currentname);
            if ($currentname == 'Shares Outstanding (Diluted Average)' OR $currentname == 'SharesOutstandingDilutedAverage') {
                $currentname = 'SharesOutstandingDiluted';
            }
            if ($currentname == 'Shares Outstanding (Basic Average)' OR $currentname == 'SharesOutstandingBasicAverage') {
                $currentname = 'SharesOutstandingBasic';
            }

            $data[$i][$j] = $currentvalue;
            $data[$i][0] = $currentname;
        }
    }

    //UNSET SOBRANTES
    for ($i = $iniline; $i <= $endline; $i ++) {
        array_splice($data[$i], $finANN, $iniQtr-$finANN+1); 
        array_splice($data[$i], 1, $iniANN);      
    }

    for ($j = 0; $j <= 19; $j++) {
        unset($data[$j]);
    }

    $dataname = array();
    foreach ($data as $line) {
        $dataname[$line[0]] = $line;
    }    
    unset ($data);    

    return $dataname;
}

function downloadguru($ticker) {
   	$username = 'jae.jun@oldschoolvalue.com';
	$password = 'wjswogud0366';

	$loginUrl = 'http://www.gurufocus.com/forum/login.php';

    //init curl
    $ch = curl_init();

    //Set the URL to work with
    curl_setopt($ch, CURLOPT_URL, $loginUrl);

    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt');

    //set the cookie the site has for certain features, this is optional
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_REFERER, 'http://www.gurufocus.com');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=' . $username . '&password=' . $password . '&forum_id=0&redir=http://www.gurufocus.com/forum/index.php');

    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie:__cfduid=d331a6914261edeeb59753a4abdfec5b31424130600; __utma=141914404.1885491424.1424130618.1424135387.1424143282.3; __utmc=141914404; __utmz=141914404.1424130618.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); optimizelySegments=%7B%7D; optimizelyEndUserId=oeu1424130623437r0.1662338498920568; optimizelyBuckets=%7B%7D; linkedin_oauth_61zs5c03pdk4=null; linkedin_oauth_61zs5c03pdk4_crc=null; __utmb=141914404.2.10.1424143282; __utmt=1; phorum_tmp_cookie=this+will+be+destroyed+once+logged+in", "Host:www.gurufocus.com", "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"));
    
    $html_0 = curl_exec($ch);

    curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt');
    curl_setopt($ch, CURLOPT_HTTPGET, 1);

    curl_setopt($ch, CURLOPT_URL, 'http://www.gurufocus.com');
    $html = curl_exec($ch);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: __cfduid=db284b1be505b19bca351c95b20396f1c1424133754; optimizelyEndUserId=oeu1424135374936r0.6779736422467977; optimizelySegments=" % "7B" % "7D; optimizelyBuckets=" % "7B" % "7D; __utma=141914404.1346358840.1424134442.1424134442.1424140928.2; __utmb=141914404.1.10.1424140928; __utmc=141914404; __utmz=141914404.1424134442.1.1.utmcsr=172.20.70.128|utmccn=(referral)|utmcmd=referral|utmcct=/scr/s.php; linkedin_oauth_61zs5c03pdk4_crc=null"));

    //set the URL to the protected file
    curl_setopt($ch, CURLOPT_URL, 'http://www.gurufocus.com/download_financials_in_CSV.php?symbol=' . $ticker);
    $ret = curl_exec($ch);

    curl_close($ch);

    //nov 5 2015 original: return $fileurl;
    return $ret;
}

?>
