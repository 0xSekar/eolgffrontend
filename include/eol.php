<?php
function getEOLXML($ticker, $dura, $numper) {
    $result = array();
    $url = "http://edgaronline.api.mashery.com/v1/corefinancials?primarysymbols=" . $ticker . "&duration=" . $dura . "&conceptgroups=all&numperiods=" . $numper . "&appkey=sr4dj7ny6mbt77p8gdntdp3x";
    $ua = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36"; //this is not needed but we keep this for safe side as code is only handling XML not JSON.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);

    // add accept header to get xml response instead of JSON.
    $headers = array("Accept: application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result['xml'] = curl_exec($ch);
    $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($errno = curl_errno($ch)) {
        $result['error_code'] = $errno;
        $result['error_msg'] = curl_strerror($errno);
    }
    curl_close($ch);
    return $result;
}

function eol_xml_parser($EOLXML, $type, $arrayeol, $AnnLot, $QtrLot) {
    $retValue = null;
    $doc = new XMLDocument();
    $date = date('Y-m-d H:i:s');        
    $first = true;

    $insertQuery = array();
    $insertData = array();
    try {
        $doc->loadXML($EOLXML); //as string
        $doc->parseXmlToArray('row');

        for ($j = 0; $j < count($doc->arrayData); $j++) {
            if ($j == 0) {
                if (preg_match('/^\s*8\s*-\s*k\s*$/i', $doc->arrayData[$j]['FormType'])) {
                    unset($doc->arrayData[$j]);
                }
            } else {
                if (preg_match('/^\s*8\s*-\s*k\s*$/i', $doc->arrayData[$j]['FormType'])) {
                    if ((isset($doc->arrayData[$j - 1]) && !preg_match('/^\s*8\s*-\s*k\s*$/i', $doc->arrayData[$j - 1]['FormType']) && $doc->arrayData[$j - 1]["fiscalYear"] == $doc->arrayData[$j]["fiscalYear"] && $doc->arrayData[$j - 1]["FiscalQuarter"] == $doc->arrayData[$j]["FiscalQuarter"]) || (isset($doc->arrayData[$j + 1]) && !preg_match('/^\s*8\s*-\s*k\s*$/i', $doc->arrayData[$j + 1]['FormType']) && $doc->arrayData[$j + 1]["fiscalYear"] == $doc->arrayData[$j]["fiscalYear"] && $doc->arrayData[$j + 1]["FiscalQuarter"] == $doc->arrayData[$j]["FiscalQuarter"])) {
                        unset($doc->arrayData[$j]);
                    }
                }
            }
        }

        // ---- Creating an array for processing ----
        $arrayAux = array();
        $col = 0;
        foreach ($doc->arrayData as $row) {
            foreach ($row as $name => $value) {
                if($col < 0) {
                        continue;
                    } else {
                        $arrayAux[$name][$col] = $value;                        
                    }            
            }
            $col++;
        }        

    } catch (Exception $e) {
        $msg = "We have a problem: " . $e->getMessage();
        echo "<Error><Message>$msg</Message></Error>"; 
    }  

    // ---- Duplicates control section ----
    $arrayAux = duplicateControl($arrayAux);
    $arrayAux = duplicateFetchAndInform($arrayAux);
    $arrayAux = continuityControl($arrayAux, $type);

    if($type == 'QTR'){ $QtrLot = count($arrayAux['fiscalYear']); }                     

    // ---- All controls complete => Copying only 15 annuals or 20 qtrs ---- 
    $Lot = strval($AnnLot+$QtrLot);
    if($Lot > 80){ 
        $Lot = 80; 
    }
    $tam = count($arrayAux['fiscalYear']);
    foreach ($arrayAux as $name => $line){
        if($type =='QTR'){
            $column = $Lot; 
        }else{
            $column = $AnnLot;
        }      
        $first = TRUE;      
        foreach($line as $col => $value){
            if($first && $type=='QTR') {                    
                $arrayeol[$name] = array_fill(0, $Lot+1, "0");
                $arrayeol[$name][0] = $name;
                $arrayeol[$name][$column] = strval($value);                            
            }else{
                if($col<0 || $col>=$tam || ($type=='QTR' && $col>=$QtrLot) || ($type=='ANN' && $col>=$AnnLot)) { // 
                    continue;
                } else {
                    $arrayeol[$name][$column] = strval($value);                
                }
            }
        $first = FALSE;
        $column--;
        }
    }
    return $arrayeol;
}

function continuityControl($arrayControl, $type){  //Check continuity on fiscal periods 
    foreach ($arrayControl['fiscalYear'] as $col => $value) {
        if($col>0){
            $sameYear = 0;
            $prevYear = 0;
            $prevQtr = 0;
            $sameYear = $arrayControl['fiscalYear'][$col]==$arrayControl['fiscalYear'][$col-1];
            $prevYear = $arrayControl['fiscalYear'][$col]==(($arrayControl['fiscalYear'][$col-1])-1);
            $prevQtr = $arrayControl['FiscalQuarter'][$col]==(($arrayControl['FiscalQuarter'][$col-1])-1);
        }
        if($type == 'ANN' && $col>0 && $prevYear == FALSE && $arrayControl['fiscalYear'][$col]!="0" && $arrayControl['fiscalYear'][$col-1]!="0"){                    
            $arrayControl = makeHole($arrayControl, $col, $type);
            $arrayControl = continuityControl($arrayControl, $type);
            return $arrayControl;
        }
        if($type == 'QTR' && $col>0 && (($sameYear == TRUE && $prevQtr == TRUE) || ($prevYear == TRUE && ($arrayControl['FiscalQuarter'][$col] == "4" && $arrayControl['FiscalQuarter'][$col-1] == "1"))) == FALSE && $arrayControl['fiscalYear'][$col] != "0" && $arrayControl['fiscalYear'][$col-1] != "0"){  
            $arrayControl = makeHole($arrayControl, $col, $type); 
            $arrayControl = continuityControl($arrayControl, $type);
            return $arrayControl;   
        }
    }
    return $arrayControl;
}

function makeHole($arrayClean, $column, $type){    //Move data in "$column" position from an array of an eol ticker    
    $tam = count($arrayClean['fiscalYear']);
    foreach($arrayClean as $name => $value) {
        $arrayClean[$name][] = strval($arrayClean[$name][$tam-1]);
        for($i = ($tam-1); $i >= $column; $i--) {
            if($i != $column){
                $arrayClean[$name][$i] = strval($arrayClean[$name][$i-1]); 
            }else{ 
                $arrayClean[$name][$i] = "0";
            }        
        }
    }
    if($type == 'ANN'){
        $arrayClean['fiscalYear'][$column] = strval($arrayClean['fiscalYear'][$column-1]-1);
    }
    if($arrayClean['FiscalQuarter'][$column-1] == "1" || $type == 'ANN'){
        $arrayClean['FiscalQuarter'][$column] = "4";
        $arrayClean['fiscalYear'][$column] = strval($arrayClean['fiscalYear'][$column-1]-1);
    }else{
        $arrayClean['FiscalQuarter'][$column] = strval($arrayClean['FiscalQuarter'][$column-1]-1);
        $arrayClean['fiscalYear'][$column] = strval($arrayClean['fiscalYear'][$column-1]);
    }
    return $arrayClean;
}

function duplicateControl($arrayControl){  //Detect duplicates and calls "cleanForm" to erase it from the array
    foreach ($arrayControl['fiscalYear'] as $col => $value) {
        if($col>0 && $arrayControl['fiscalYear'][$col] == $arrayControl['fiscalYear'][$col-1] && $arrayControl['FiscalQuarter'][$col]== $arrayControl['FiscalQuarter'][$col-1] && $arrayControl['fiscalYear'][$col] != 0){                    
            if(date("Y-m-d", strtotime($arrayControl['ReceivedDate'][$col-1])) <= date("Y-m-d", strtotime($arrayControl['ReceivedDate'][$col]))){
                $arrayControl = cleanForm($arrayControl, $col-1);
                $arrayControl = duplicateControl($arrayControl);
                return $arrayControl;
            }else{
                $arrayControl = cleanForm($arrayControl, $col);
                $arrayControl = duplicateControl($arrayControl);
                return $arrayControl;
            }                    
        }
    }
    return $arrayControl;
}

function duplicateFetchAndInform($arrayControl){ //Detect duplicates and report them
    if( (count($arrayControl['fiscalYear']))==(count(array_unique($arrayControl['fiscalYear']))) ){
        return $arrayControl;
    }
    $max = count($arrayControl['fiscalYear']);    
    for($c=0;$c<$max;$c++){
        for($d=$c+1;$d<$max;$d++){
            if($d!=$c && $arrayControl['fiscalYear'][$c]==$arrayControl['fiscalYear'][$d] && $arrayControl['FiscalQuarter'][$c]==$arrayControl['FiscalQuarter'][$d] && $arrayControl['fiscalYear'][$d]!=0){
                echo "\n Informe - Duplicado ".$c." con ".$d;
            }
        }
    }
    return $arrayControl;       
}

function cleanForm($arrayClean, $column){    //Erase all data in "$column" position from an array of an eol ticker    
    foreach($arrayClean as $name => $value) {
        unset($arrayClean[$name][$column]);        
        $arrayClean[$name] = array_values($arrayClean[$name]);                                                
    }
    return $arrayClean;
}

function check_eol_xml($resp) { //resp as EOLXML
    $res = TRUE;
    switch ($resp['status_code']) {
                    case 200:
                        // still two cases either there is valid data or not
                        if ((stripos($resp['xml'], 'There is no data') !== false) OR (stripos($resp['xml'], 'No data available') !== false)) {                                                       
                            echo "<Error><Message>There is no data for the current request </Message></Error>"; 
                            $res = FALSE;
                        }
                        break;
                    case 400:
                        echo "<Error><Message>Error 400, Bad Request</Message></Error>"; 
                        $res = FALSE;
                        break;
                    case 403:
                        echo "<Error><Message>Error 403, Forbidden</Message></Error>"; 
                        $res = FALSE;
                        break;
                    case 500:
                        echo "<Error><Message>Error 500, Internal Server Error</Message></Error>"; 
                        $res = FALSE;
                        break;
                    case 504:
                        echo "<Error><Message>Error 504, Gateway timeout</Message></Error>";
                        $res = FALSE; 
                        break;
                    default:
                        echo "<Error><Message>Other error in data source service</Message></Error>"; 
                        $res = FALSE;
                }
    return $res;
}
?>