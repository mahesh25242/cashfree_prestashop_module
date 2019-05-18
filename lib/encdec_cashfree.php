<?php

function cf_encrypt_e($input, $ky) {
    $key   = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_encrypt ( $input , "AES-128-CBC" , $key, 0, $iv );
    return $data;
}

function cf_decrypt_e($crypt, $ky) {
    $key   = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_decrypt ( $crypt , "AES-128-CBC" , $key, 0, $iv );
    return $data;
}

function cf_pkcs5_pad_e($text, $blocksize)
{
    $pad = $blocksize - (strlen($text) % $blocksize);
    return $text . str_repeat(chr($pad), $pad);
}

function cf_pkcs5_unpad_e($text)
{
    $pad = ord($text{strlen($text) - 1});
    if ($pad > strlen($text))
        return false;
    return substr($text, 0, -1 * $pad);
}

function cf_generateSalt_e($length)
{
    $random = "";
    srand((double) microtime() * 1000000);
    
    $data = "AbcDE123IJKLMN67QRSTUVWXYZ";
    $data .= "aBCdefghijklmn123opq45rs67tuv89wxyz";
    $data .= "0FGH45OP89";
    
    for ($i = 0; $i < $length; $i++) {
        $random .= substr($data, (rand() % (strlen($data))), 1);
    }
    
    return $random;
}

function cf_checkString_e($value)
{
    $myvalue = ltrim($value);
    $myvalue = rtrim($myvalue);
    if ($myvalue == 'null')
        $myvalue = '';
    return $myvalue;
}

function cf_getChecksumFromArray($arrayList, $key, $sort = 1)
{
    if ($sort != 0) {
        ksort($arrayList);
    }
    $str         = getArray2Str($arrayList);
    $salt        = cf_generateSalt_e(4);
    $finalString = $str . "|" . $salt;
    $hash        = hash("sha256", $finalString);
    $hashString  = $hash . $salt;
    $checksum    = cf_encrypt_e($hashString, $key);
    return $checksum;
}

function cf_verifychecksum_e($arrayList, $key, $checksumvalue)
{
    $arrayList = removeCheckSumParam($arrayList);
    ksort($arrayList);
    $str        = getArray2StrForVerify($arrayList);
    $hash = cf_decrypt_e($checksumvalue, $key);
    $salt       = substr($hash, -4);
    
    $finalString = $str . "|" . $salt;
    
    $website_hash = hash("sha256", $finalString);
    $website_hash .= $salt;
    
    $validFlag = "FALSE";
    if ($website_hash == $hash) {
        $validFlag = "TRUE";
    } else {
        $validFlag = "FALSE";
    }
    return $validFlag;
}

function cf_getArray2Str($arrayList) {
	$findme   = 'REFUND';
	$findmepipe = '|';
	$paramStr = "";
	$flag = 1;	
	foreach ($arrayList as $key => $value) {
		$pos = strpos($value, $findme);
		$pospipe = strpos($value, $findmepipe);
		if ($pos !== false || $pospipe !== false) 
		{
			continue;
		}
		
		if ($flag) {
			$paramStr .= cf_checkString_e($value);
			$flag = 0;
		} else {
			$paramStr .= "|" . cf_checkString_e($value);
		}
	}
	return $paramStr;
}

function cf_getArray2StrForVerify($arrayList) {
	$paramStr = "";
	$flag = 1;
	foreach ($arrayList as $key => $value) {
		if ($flag) {
			$paramStr .= cf_checkString_e($value);
			$flag = 0;
		} else {
			$paramStr .= "|" . cf_checkString_e($value);
		}
	}
	return $paramStr;
}

function cf_redirect2PG($paramList, $key)
{
    $hashString = cf_getChecksumFromArray($paramList);
    $checksum   = cf_encrypt_e($hashString, $key);
}

function cf_removeCheckSumParam($arrayList)
{
    if (isset($arrayList["CHECKSUMHASH"])) {
        unset($arrayList["CHECKSUMHASH"]);
    }
    return $arrayList;
}

function cf_getTxnStatus($requestParamList)
{
    return cf_callAPI(CASHFREE_STATUS_QUERY_URL, $requestParamList);
}

function cf_initiateTxnRefund($requestParamList)
{
    $CHECKSUM                     = cf_getChecksumFromArray($requestParamList, CASHFREE_MERCHANT_KEY, 0);
    $requestParamList["CHECKSUM"] = $CHECKSUM;
    return cf_callAPI(CASHFREE_REFUND_URL, $requestParamList);
}

function cf_callAPI($apiURL, $requestParamList)
{
    $jsonResponse      = "";
    $responseParamList = array();
    $JsonData          = json_encode($requestParamList);
    $postData          = 'JsonData=' . urlencode($JsonData);
    $ch                = curl_init($apiURL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ));
    $jsonResponse      = curl_exec($ch);
    $responseParamList = json_decode($jsonResponse, true);
    return $responseParamList;
}

function cf_sanitizedParam($param)
{
    $pattern[0]     = "%,%";
    $pattern[1]     = "%#%";
    $pattern[2]     = "%\(%";
    $pattern[3]     = "%\)%";
    $pattern[4]     = "%\{%";
    $pattern[5]     = "%\}%";
    $pattern[6]     = "%<%";
    $pattern[7]     = "%>%";
    $pattern[8]     = "%`%";
    $pattern[9]     = "%!%";
    $pattern[10]    = "%\\$%";
    $pattern[11]    = "%\%%";
    $pattern[12]    = "%\^%";
    $pattern[13]    = "%=%";
    $pattern[14]    = "%\+%";
    $pattern[15]    = "%\|%";
    $pattern[16]    = "%\\\%";
    $pattern[17]    = "%:%";
    $pattern[18]    = "%'%";
    $pattern[19]    = "%\"%";
    $pattern[20]    = "%;%";
    $pattern[21]    = "%~%";
    $pattern[22]    = "%\[%";
    $pattern[23]    = "%\]%";
    $pattern[24]    = "%\*%";
    $pattern[25]    = "%&%";
    $sanitizedParam = preg_replace($pattern, "", $param);
    return $sanitizedParam;
}

function cf_callNewAPI($apiURL, $requestParamList) {
	$jsonResponse = "";
	$responseParamList = array();
	$JsonData =json_encode($requestParamList);
	$postData = 'JsonData='.urlencode($JsonData);
	$ch = curl_init($apiURL);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);                                                                  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                         
	'Content-Type: application/json', 
	'Content-Length: ' . strlen($postData))                                                                       
	);  
	$jsonResponse = curl_exec($ch);   
	$responseParamList = json_decode($jsonResponse,true);
	return $responseParamList;
}
