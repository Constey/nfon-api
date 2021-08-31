
 $GLOBALS['baseuri'] = "https://portal-api.nfon.net:8090";
 $GLOBALS['apiuri']  = "/api/customers/KXXXX";
 $GLOBALS['debug']  = true;
 $GLOBALS['AccessId']  = "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXX";
 $GLOBALS['SecretKey'] = "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXX";

//JSON Validator function
function json_validator($data=NULL) {

    if (!empty($data)) {
  
                  @json_decode($data);
  
                  return (json_last_error() === JSON_ERROR_NONE);
  
          }
          return false;
  }

function apiRequest(string $easyURI, $method, $data) {
    // VALIDATE
    if (empty($easyURI)) { die("No EasyURL given.");   }
    if (empty($method))  { die("No Method given.");   }
    if (($method == "POST" || $method == "PUT") && empty($data)) {  die("POST or PUT wanted but no DATA given."); }
    if (($method == "POST" || $method == "PUT") && !empty($data)) { 
        if (json_validator($data) == false ) { die("Fehlerhaftes JSON Data übergeben."); }
    }
   

    $debug = false;
    $debugobject = array();

    date_default_timezone_set('UTC');
    // ACCESS TOKENS FOR API
    $AccessId =  $GLOBALS['AccessId'];
    $SecretKey = $GLOBALS['SecretKey'];
    

    // Build URL we need multiple parts of it for the signature and the request
    $canonicalizedResource = $GLOBALS['apiuri'] . $easyURI;
    $fullurl = $GLOBALS['baseuri'] . $canonicalizedResource;

    $debugobject['url_baseuri'] = $GLOBALS['baseuri'];
    $debugobject['url_easyURI'] = $easyURI;
    $debugobject['url_canonicalizedResource'] = $canonicalizedResource;
    $debugobject['url_fullurl'] = $fullurl;

    // Generate Request Date for NFON
    // Example:
    // $requestDate = "Mon, 30 Aug 2021 14:00:48 GMT";
    $requestDate  = date('D, d M Y G:i:s');
    $requestDate  = $requestDate . " GMT";

    $debugobject['requestDate'] = $requestDate;
    if ($debug) { 
        echo "requestDate:" . $requestDate ;
        echo "<br>";
    }
    // Send DATA for POST OR PUT REQUESTS
    // Example:
    // TODO: VALIDATE (json_encode does not work)
    #$data = '';
    $jsondata = json_decode($data);
    $debugobject['data'] = $data;
    $debugobject['jsondata'] = $jsondata;
    if ($debug) { echo ("Jsondata:" . $jsondata); 
        echo "<br>";
    }
    // Generate MD5 HASH of DATA
    $contentMD5  = md5($data, false);
    $debugobject['contentMD5'] = $contentMD5;
    if ($debug) {
        echo ("ContentMD5:" . $contentMD5 );
        echo "<br>"; 
    }

    // SET CONTENT TYPE AND REQUEST METHOD
    $contentType = 'application/json; charset=utf-8';
    #$method = "GET";

    // Generate String to Sign for Signature
    $stringToSign = "";
    $stringToSign = $method . "\n" . $contentMD5 . "\n" . $contentType . "\n" . $requestDate . "\n" . $canonicalizedResource;
    $debugobject['stringToSign'] = $stringToSign;
    $debugobject['method'] = $method;

    if ($debug) {
        echo "StringToSign:<br>" . $stringToSign;
        echo "<br>";
        echo $stringToSign;
        echo "<br>";
        echo base64_encode(hash_hmac('SHA1', utf8_encode($stringToSign), utf8_encode($SecretKey), true));
    }

    // Encrypt the String with our Secret KEY
    $signature = base64_encode(hash_hmac('SHA1', utf8_encode($stringToSign), utf8_encode($SecretKey), true));

    if ($debug) { echo "<br>Test<br><br>"; }


    /*
    ob_start();  
    $out = fopen('php://output', 'w');
    */
    $curl = curl_init();

    switch ($method){
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
            break;
        case "DELETE":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
                echo $url;
    }
    if ($debug) {
        echo $method . " " . $fullurl;
        echo "<br>"; 
    }
    // OPTIONS:
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0); // Damit man nur den Body zurückbekommt
    curl_setopt($curl, CURLOPT_URL, $fullurl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'x-nfon-date: ' . $requestDate,
        'Content-Type: ' . $contentType,
        'Accept: */*',
        'Content-MD5: ' . $contentMD5,
        'Authorization: NFON-API '.$AccessId.':'.$signature,
        'Content-Length: ' . strlen($data),
    ));
    curl_setopt($curl, CURLOPT_VERBOSE, true);
    curl_setopt($curl, CURLOPT_STDERR, $out);  
    // EXECUTE:
    $result = curl_exec($curl);

    // prüfen, ob ein Fehler aufgetreten ist
    if(!curl_errno($curl))
    {
        $info = curl_getinfo($curl);
        $debugobject['http_code'] = $info["http_code"];
        if ($debug) { echo "HTTP-Status: $info[http_code]"; }
    }
 
    // Handle Response
    if(!$result){
        $debugobject['http_code'] = "0";
        $debugobject['result']    = "Connection Failure";
        //die("Connection Failure");
    } else {
        $debugobject['result'] = $result;
    }
    // Overwrite Response when DELETE is korrekt
    if ($method == "DELETE") {
        if ($info["http_code"] == 204) {
            $debugobject['http_code'] = $info["http_code"];
            $debugobject['result']    = "Success";
        }
    }

    curl_close($curl);    

    if ($debug) {
        echo "<br<br>RESULT:<br>";
        var_dump($result); 
    }

    return($debugobject);

    /*fclose($out);  
    $debug = ob_get_clean();
    echo $debug;
    */
}

function addExtensionInSkill ($skillNumber, $extensionNumber) {
    if (!preg_match('/^[0-9][0-9][0-9]$/', $extensionNumber)) {
        die("Keine gültige Telefonnummer");
    } 
    $data = '{"links": [{"rel": "phoneExtension","href": "' . $GLOBALS['apiuri'] . '/targets/phone-extensions/' . $extensionNumber .'"}]}';
    $easyURI = "/targets/skill-services/" . $skillNumber . "/members";
    $method = "POST";
    $response = apiRequest ($easyURI, $method, $data);
    if ($response["http_code"] == 201) { "Success"; } elseif ($response["http_code"] == 400 ) { "Already exists"; }
    if ($GLOBALS['debug']){ echo "<pre>"; print_r($response); echo "</pre>"; }
}

function getExtensionInSkill ($skillNumber, $extensionNumber) {
    if (!preg_match('/^[0-9][0-9][0-9]$/', $extensionNumber)) {
        die("Keine gültige Telefonnummer");
    } 
    $data = "";
    $easyURI = "/targets/skill-services/" . $skillNumber . "/members/" . $extensionNumber;
    $method = "GET";
    $response = apiRequest ($easyURI, $method, $data);
    if ($GLOBALS['debug']){ echo "<pre>"; print_r($response); echo "</pre>"; }
    if ($response["http_code"] == 200) { 
        "Success"; 
        return $response["result"];
    } else {
        return $response["result"];
    }
}

function removeExtensionInSkill ($skillNumber, $extensionNumber) {
    if (!preg_match('/^[0-9][0-9][0-9]$/', $extensionNumber)) {
        die("Keine gültige Telefonnummer");
    } 
    $data = "";
    $easyURI = "/targets/skill-services/" . $skillNumber . "/members/" . $extensionNumber;
    $method = "DELETE";
    $response = apiRequest ($easyURI, $method, $data);
    if ($response["http_code"] == 200) { "Success"; } 
    if ($GLOBALS['debug']){ echo "<pre>"; print_r($response); echo "</pre>"; }
}

function getSkillLoginStatus ($extensionNumber) {
    if (!preg_match('/^[0-9][0-9][0-9]$/', $extensionNumber)) {
        die("Keine gültige Telefonnummer");
    } 
    $data = "";
    $easyURI = "/targets/phone-extensions/" . $extensionNumber . "/skills/login-status";
    $method = "GET";
    $response = apiRequest ($easyURI, $method, $data);
    if ($GLOBALS['debug']){ echo "<pre>"; print_r($response); echo "</pre>"; }
    if ($response["http_code"] == 200) { 
        "Success"; 
        try {
            $skillstatusResponse = json_decode($response["result"]);
            $skillstatus = $skillstatusResponse->data[0]->value;
            if ($skillstatus == 1 || $skillstatus == true) { 
                // Service aktiviert
                return true;
            } else {
                // Service deaktiviert
                return false;
            }
        }  catch (Exception $e) {
            return $e->getMessage();
        }
    } else {
        return $response["result"];
    }
}

//addExtensionInSkill ("1", "317");
//removeExtensionInSkill ("1", "317");
//getExtensionInSkill ("1", "317");
getSkillLoginStatus ("317");
