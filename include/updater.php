<?php
function ckeckNDown($ticker, $AnnLot, $QtrLot, $OTC = false, $force = false){
    $db = Database::GetInstance(); 
    $arrayeol = array();
    $arrayeol1 = array();
    $dbFY = 0;
    $dbFQ = 0;
    $today = date('Y/m/d H:i:s');

        // ******** Intern Id Fetch *********
    try {
        $res = $db->prepare("SELECT id FROM tickers WHERE ticker = ?");
        $res->execute(array(strval($ticker)));
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }

    $row = $res->fetchAll();
    $exist = count($row);
    $proc = FALSE;
    if($exist == 0){
        //conseguir exchenge de bajar un QTR de eol para ver lo q sigue
        $eolfileQ1 = getEOLXML($ticker, 'QTR', '1'); //1 qtr
        $checkqtr1 = check_eol_xml($eolfileQ1);
        $exchange = 'no market';

        if($checkqtr1 == TRUE) {
            $arrayeol1 = eol_xml_parser($eolfileQ1["xml"], 'QTR', $arrayeol1, $AnnLot, $QtrLot);
            $col = count($arrayeol1['PrimaryExchange'])-1;
            $exchange = strval($arrayeol1['PrimaryExchange'][$col]);
        }else{
            echo "\n Ticker's EOL data failed, marked as tested\n";
            try {
                $res = $db->prepare("UPDATE tickers_proedgard_updates SET tested_for_today = '".$today."' WHERE (ticker = ? AND downloaded is null)");
                $res->execute(array(strval($ticker)));
            } catch(PDOException $ex) {
                echo "\nDatabase Error"; //user message
                die("Line: ".__LINE__." - ".$ex->getMessage());
            }
            return FALSE;
        }
            
        if(strpos($exchange, 'OTC') !== FALSE && $OTC == FALSE){ //cambiar =OTC por contiene OTC
            try {
                $res = $db->prepare("UPDATE tickers_proedgard_updates SET otc = 'Y' WHERE ticker = ?");
                $res->execute(array(strval($ticker)));
            } catch(PDOException $ex) {
                echo "\nDatabase Error"; //user message
                die("Line: ".__LINE__." - ".$ex->getMessage());
            }
            echo " OTC ticker marked\n";
            return FALSE;
        }else{
            //Agrego
            if($OTC == FALSE){
            $intId = addTicker($ticker, $arrayeol1);
            echo " Ticker added \n";
            $proc = TRUE;
            }
        }

        if($OTC==TRUE){  //precio? agrego?

            $resJS = array();
            $queryOD = "http://ondemand.websol.barchart.com/getQuote.json?apikey=fbb10c94f13efa7fccbe641643f7901f&symbols=".$ticker."&mode=I&fields=lastPrice";
            $resOD = file_get_contents($queryOD);
            $resJS = json_decode($resOD, true);

            $code = $resJS['status']['code'];

            if($code == 200){
                $price = $resJS['results'][0]['lastPrice']; //si este es menor q 1 nada, si es mayor q uno agrego y pongo el nuevo int id para q siga procesando
                if($price > 1){
                    $intId = addTicker($ticker, $arrayeol1);
                    echo " Ticker added, price high than U\$s 1\n";
                    $proc = TRUE;
                }else{ 
                    echo " Ticker marked as tested, price is under U\$s 1\n";
                    try {
                        $res = $db->prepare("UPDATE tickers_proedgard_updates SET tested_for_today = '".$today."' WHERE (ticker = ? AND downloaded is null)");
                        $res->execute(array(strval($ticker)));
                    } catch(PDOException $ex) {
                        echo "\nDatabase Error"; //user message
                        die("Line: ".__LINE__." - ".$ex->getMessage());
                    } //proc == FALSE
                } 
                
            }else{
                if($code == 204){
                    //borro registro de la tabla proedgard
                    try {
                        $res = $db->prepare("DELETE FROM tickers_proedgard_updates WHERE ticker = ?");
                        $res->execute(array(strval($ticker)));
                    } catch(PDOException $ex) {
                        echo "\nDatabase Error"; //user message
                        die("Line: ".__LINE__." - ".$ex->getMessage());
                    }
                    echo " Ticker deleted - Barchart code 204\n";
                }else{
                    echo " Barchart code is not 204 or 200\n";
                    return FALSE;
                }
            }
        }

        if($proc == FALSE){
        echo " Id on tickers table doesnt exist \n ";
        return FALSE;
        }
    }else{
        $intId = $row[0]['id'];
    }    

    $eolfileQ = getEOLXML($ticker, 'QTR', '30'); //20 qtr
    $checkqtr = check_eol_xml($eolfileQ);

    if($checkqtr == TRUE) {
        $arrayeol = eol_xml_parser($eolfileQ["xml"], 'QTR', $arrayeol, $AnnLot, $QtrLot);

        $col = count($arrayeol['fiscalYear'])-1;
        $eolFY = strval($arrayeol['fiscalYear'][$col]);
        $eolFQ = strval($arrayeol['FiscalQuarter'][$col]);               

        if($force==FALSE){
            // ******** BD information Fetch *********
            try {
                $res = $db->prepare("SELECT fiscal_year, fiscal_quarter FROM reports_header WHERE ticker_id = ? ORDER BY fiscal_year ASC, fiscal_quarter ASC"); //order by fiscal y y dsp fq
                $res->execute(array(strval($intId)));
            } catch(PDOException $ex) {
                echo "\nDatabase Error"; //user message
                die("Line: ".__LINE__." - ".$ex->getMessage());
            }

            $row = $res->fetchAll();
            $line = count($row)-1;
            if($line == -1){
                echo " Id doesnt exist on reports_header \n ";//forzar descarga
                $force = TRUE;
            }else{
                $dbFY = $row[$line]['fiscal_year'];
                $dbFQ = $row[$line]['fiscal_quarter'];
            }
        }

        
        if($eolFY>$dbFY || ($eolFY==$dbFY && $eolFQ>$dbFQ) || $force == TRUE || $proc == TRUE){ 
            $downOK = downNParse($ticker, $arrayeol, $AnnLot, $QtrLot);

            if($downOK){ 
                try {
                    $res = $db->prepare("UPDATE tickers_proedgard_updates SET downloaded = 'Y', updated_date = '".$today."' WHERE (ticker = ? AND downloaded is null) ");
                    $res->execute(array(strval($ticker)));
                } catch(PDOException $ex) {
                    echo "\nDatabase Error"; //user message
                    die("Line: ".__LINE__." - ".$ex->getMessage());
                }
                return TRUE;
            }            
        }else{
            try {
                    $res = $db->prepare("UPDATE tickers_proedgard_updates SET tested_for_today = '".$today."' WHERE (ticker = ? AND downloaded is null)");
                    $res->execute(array(strval($ticker)));
                } catch(PDOException $ex) {
                    echo "\nDatabase Error"; //user message
                    die("Line: ".__LINE__." - ".$ex->getMessage());
                }
            echo " Previously updated\n";
            return FALSE;
        }

    }else{        
        echo " Download Error !!!  EOL QTR FAILED\n";
        return FALSE;
    }
}

function downNParse($ticker, $arrayeol, $AnnLot, $QtrLot){
    $checkqtr = TRUE;
    $return = array();    

    $eolfileA = getEOLXML($ticker, 'ANN', '25'); //15 ann
    $checkann = check_eol_xml($eolfileA);

    $QtrLotExt = strval(count($arrayeol['fiscalYear']) - $AnnLot);
    if($checkann == TRUE) {$arrayeol = eol_xml_parser($eolfileA["xml"], 'ANN', $arrayeol, $AnnLot, $QtrLotExt);}

    $fechaeol = date("Y-m-d", strtotime($arrayeol["FiledDate"][$AnnLot])); //position 16 is last annual 

    //   *************************  Download GF *********************************************
    $gurufile = downloadguru($ticker);
    $arrayguru = array_map('str_getcsv', preg_split('/\r*\n+|\r+/', $gurufile));

    //   *************************  Processing  *********************************************
    if($arrayguru == FALSE || count($arrayguru)<3){
        $guruok = FALSE;
    }else{
        $guruok = TRUE; //error if guru is missing
    }

    if($checkqtr && $checkann && $guruok) { 
        
        $arrayguru = parseguru($arrayguru, $fechaeol, $AnnLot, $QtrLotExt); //parse guru        
        $returnGuru = holes($arrayguru, $arrayeol, $AnnLot, $QtrLotExt); 
        $arraymerged = array_merge($returnGuru, $arrayeol);

        if (isset($returnGuru['InterestIncome'])) {
            $arraymerged['InterestIncome'] = $returnGuru['InterestIncome'];
        }
        if (isset($returnGuru['InterestExpense'])) {
            $arraymerged['InterestExpense'] = $returnGuru['InterestExpense'];
        }
        
        $arraymerged = cleanZero($arraymerged);
        $arraymerged = arrayTrim($arraymerged, $AnnLot, $QtrLot);
        $arraymerged = finalControl($arraymerged, $AnnLot, $QtrLot);

        //Export to csv
        $o = fopen('file_Ticker'.$ticker.'.csv', 'w');

        foreach($arraymerged as $name=>$value) {
        fputcsv($o,$value);
        }
        fclose($o);
        return TRUE;
    }else{
        echo "\n \n \n ----  DOWNLOAD ERROR !!!!!!!! Tiker unprocessed -----\n ";
        return FALSE;
    }
}

function addTicker($ticker, $EOLQtr){ 
    $db = Database::GetInstance(); 

    $col = count($EOLQtr['PrimaryExchange'])-1;
    $exchange = strval($EOLQtr['PrimaryExchange'][$col]);

    try {
        $qe = "SELECT name_to AS exchange FROM exchange_conversion WHERE name_from = ?";
        $params = array();
        $params[] = $exchange;

        $re = $db->prepare($qe);
        $re ->execute($params);
        if(!$rowe = $re->fetch(PDO::FETCH_ASSOC)) {
            $rowe["exchange"] = "";
        }
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    try {
        $res = $db->prepare("INSERT INTO tickers (ticker, cik, company, exchange, sic, entityid, formername, industry, sector, country) VALUES (:ticker, :cik, :company, :exchange, :sic, :entityid, :formername, :industry, :sector, :country)");
        $res->execute(array(
                    ':ticker' => (is_null($ticker)?'':$ticker),
                    ':cik' => (is_null($EOLQtr['CIK'][$col])?'':$EOLQtr['CIK'][$col]), 
                    ':company' => (is_null($EOLQtr['COMPANYNAME'][$col])?'':$EOLQtr['COMPANYNAME'][$col]), 
                    ':exchange' => $rowe["exchange"], 
                    ':sic' => (is_null($EOLQtr['SICCode'][$col])?'':$EOLQtr['SICCode'][$col]), 
                    ':entityid' => (is_null($EOLQtr['entityid'][$col])?'':$EOLQtr['entityid'][$col]), 
                    ':formername' => (is_null($EOLQtr['Formername'][$col])?'':$EOLQtr['Formername'][$col]), 
                    ':industry' => (is_null($EOLQtr['Industry'][$col])?'':$EOLQtr['Industry'][$col]), 
                    ':sector' => (is_null($EOLQtr['Sector'][$col])?'':$EOLQtr['Sector'][$col]),
                    ':country' => (is_null($EOLQtr['Country'][$col])?'':$EOLQtr['Country'][$col])
                   ));
        $id = $db->lastInsertId();
        $res = $db->exec("INSERT into tickers_control (ticker_id, last_eol_date, last_yahoo_date, last_barchart_date, last_volatile_date, last_estimates_date) VALUES ($id, '2000-01-01', '2000-01-01', '2000-01-01', '2000-01-01', '2000-01-01')");
    } catch(PDOException $ex) {
        echo "\nDatabase Error"; //user message
        die("Line: ".__LINE__." - ".$ex->getMessage());
    }
    return $id;
}

function holes($arrayguru, $arrayeol, $AnnLot, $QtrLot){
    $fiscalPeriod = array();
    $periodEndDate = array();
    $order = array();
    $Lot = strval($AnnLot+$QtrLot);
    $order = array_fill(0, $Lot, array('0','0'));

    $arrayguru = homoDates($arrayguru, 'GF');
    $arrayeol = homoDates($arrayeol, 'EOL');

    $fiscalPeriod = $arrayguru['FiscalPeriod'];
    $periodEndDate = $arrayeol['PeriodEndDate'];

    foreach($periodEndDate as $col => $value) {
        if($periodEndDate[$col]!='0' && $periodEndDate[$col] != 'PeriodEndDate'){
            if($col>0 && $col<count($periodEndDate)){ 
                switch ($col) {
                    case '1': //First ANN
                        $gfDiff = dayGap($periodEndDate[$col], $fiscalPeriod[$col]);
                        $gfDiffPost = dayGap($periodEndDate[$col], $fiscalPeriod[$col+1]);

                        if($gfDiff<$gfDiffPost){
                            $order[$col] = array(strval($col), strval($gfDiff));
                        }else{
                            $order[$col] = array(strval($col+1), strval($gfDiffPost));
                        }
                        break;

                    case $AnnLot : //Last ANN
                        $gfDiff = dayGap($periodEndDate[$col], $fiscalPeriod[$col]);
                        $gfDiffPrev = dayGap($periodEndDate[$col], $fiscalPeriod[$col-1]);

                        if($gfDiff<$gfDiffPrev){
                            $order[$col] = array(strval($col), strval($gfDiff));
                        }else{
                            $order[$col] = array(strval($col-1), strval($gfDiffPrev));
                        }
                        break;

                    case $AnnLot+1 : //First QTR
                        $gfDiff = dayGap($periodEndDate[$col], $fiscalPeriod[$col]);
                        $gfDiffPost = dayGap($periodEndDate[$col], $fiscalPeriod[$col+1]);
                        
                        if($gfDiff<$gfDiffPost){
                            $order[$col] = array(strval($col), strval($gfDiff));
                        }else{
                            $order[$col] = array(strval($col+1), strval($gfDiffPost));
                        }
                        break;

                    case $Lot-1 : //Last QTR
                        $gfDiff = dayGap($periodEndDate[$col], $fiscalPeriod[$col]);
                        $gfDiffPrev = dayGap($periodEndDate[$col], $fiscalPeriod[$col-1]);

                        if($gfDiff<$gfDiffPrev){
                            $order[$col] = array(strval($col), strval($gfDiff));
                        }else{
                            $order[$col] = array(strval($col-1), strval($gfDiffPrev));
                        }
                        break;

                    default: 
                        $gfDiff = dayGap($periodEndDate[$col], $fiscalPeriod[$col]);
                        $gfDiffPrev = dayGap($periodEndDate[$col], $fiscalPeriod[$col-1]);
                        $gfDiffPost = dayGap($periodEndDate[$col], $fiscalPeriod[$col+1]);

                        if($gfDiffPrev<$gfDiff){ // Comparative between 3 periods
                            if($gfDiffPrev<$gfDiffPost){
                                $order[$col] = array(strval($col-1), strval($gfDiffPrev));  //prev
                            }else{
                                $order[$col] = array(strval($col+1), strval($gfDiffPost));   //post
                            }
                        }else{
                            if($gfDiff<$gfDiffPost){
                                $order[$col] = array(strval($col), strval($gfDiff));  //o
                            }else{
                                $order[$col] = array(strval($col+1), strval($gfDiffPost));  //post
                            }
                        }
                        break;
                }
            }           
        }else{
            continue;
        }
    }

    foreach ($order as $key => $value) {
        if($key<(count($order)-2) && $order[$key][0]==$order[$key+1][0] && $order[$key][0]!='0'){
            if($order[$key][1]<$order[$key+1][1]){
                $order[$key+1][0] = 'hole';
            }else{
                $order[$key][0] = 'hole';
            }
        }
    }

    // New array 
    $arrayGuruNew = array();
    $col = 0;
    foreach($arrayguru as $name => $row) {
        foreach ($row as $col => $value) {
            if($col < 0 || $col >= $Lot) {
                continue;
            } else {
                $tally = $order[$col][0]; 
                if($tally!='0'){
                    if($tally =='hole'){
                        $arrayGuruNew[$name][$col] = '0';
                    }else{
                        $arrayGuruNew[$name][$col] = $arrayguru[$name][$tally]; 
                    }
                }else{
                    $arrayGuruNew[$name][$col] = $arrayguru[$name][$col];                    
                }              
            }            
        }
    }
    // ---- Only return new guru that has changes 
    return $arrayGuruNew;    
}

function homoDates($array, $source){
    if($source =='GF'){
        foreach($array['FiscalPeriod'] as $col => $value) {
            if($value == 'FiscalPeriod'){
                continue;
            }else{
                $month = substr($value, -2);
                if($month != "02"){
                    $array['FiscalPeriod'][$col] = $value."30";
                }else{
                    $array['FiscalPeriod'][$col] = $value."28";
                }
            }
        }
    }else{
        foreach($array['PeriodEndDate'] as $col => $value) {
            if($value == "0" || $value == 'PeriodEndDate' ){
                continue;
            }else{
                $array['PeriodEndDate'][$col] = date("Ymd", strtotime($value));
            }
        }
    }
    return $array;       
}

function dayGap($day_i, $day_f){
    $days = (strtotime($day_i)-strtotime($day_f))/86400;
    $days = intval(round($days));
    $days = abs($days); 
         
    return $days;
}

function cleanZero($arraymerged){
    foreach($arraymerged['PeriodEndDate'] as $col => $value) {
            if($col < 0) {
                continue;
            } else {
                if($arraymerged['PeriodEndDate'][$col] == '0'){ 
                    $arraymerged = cleanForm($arraymerged, $col); //eol.php function
                    $arraymerged = cleanZero($arraymerged);
                    return $arraymerged;
                }
            }    
    }
    return $arraymerged;
}

function arrayTrim($arraymerged, $AnnLot, $QtrLot){
    $Lot = strval($AnnLot + $QtrLot);
    $Qtr = strval(count($arraymerged['PeriodEndDate']) - $QtrLot - $AnnLot -1); 
    $col=16;
    while($Qtr>0){        
        $arraymerged = cleanForm($arraymerged, $col); //eol.php function 
        $Qtr--;
    }
    return $arraymerged;
}

function finalControl($arraymerged, $AnnLot, $QtrLot){
    $AnnQty = $QtrQty = 0;
    $Lot = $AnnLot + $QtrLot;
    $LotOrig = count($arraymerged['PeriodLength']);
    foreach($arraymerged['PeriodLength'] as $col => $value) {
        if($value == 12){
            $AnnQty++;
        }
        if($value == 3){
            $QtrQty++;
        }
    }
    if($AnnQty<$AnnLot || $QtrQty<$QtrLot){
        $arrayComplete = array();
        foreach($arraymerged as $name => $row) {
            if($name == NULL){
                continue;
            }else{
                $arrayComplete[$name] = array_fill(0, $Lot+1, "0");
                $arrayComplete[$name][0] = $arraymerged[$name][0];
                $colOrig = $AnnQty;
                $col = $AnnLot;
                $AnnCount = $AnnQty;
                $QtrCount = $QtrQty;               
                while($col>0){
                    if($AnnCount>0){
                        $arrayComplete[$name][$col] = $arraymerged[$name][$colOrig];
                        $colOrig--;
                        $AnnCount--;
                    }else{
                        $arrayComplete[$name][$col] = '0';
                    }
                    $col--;                
                }                
                $colOrig = $LotOrig-1;
                $col = $Lot;
                while($col>$AnnLot) {
                    if($QtrCount>0){
                        $arrayComplete[$name][$col] = $arraymerged[$name][$colOrig];
                        $colOrig--;
                        $QtrCount--;
                    }else{
                        $arrayComplete[$name][$col] = '0';
                    }
                    $col--;                
                }             
            }
        }        
        return $arrayComplete;
    }
    return $arraymerged;
}

?>