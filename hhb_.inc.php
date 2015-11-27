<?php

function hhb_br( /*int*/ $multiplier = 1, /*string*/ $definition = "<br/>\n")
{
    echo str_repeat($definition, $multiplier);
}
function hhb_tohtml($str)
{
    return htmlentities($str, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true);
}
function hhb_mustbe( /*string*/ $type, /*mixed*/ $variable)
{
    //should it be UnexpectedValueException or InvalidArgumentException?
    //going with UnexpectedValueException for now...
    if ($type === 'ip') {
        $dbg = filter_var($variable, FILTER_VALIDATE_IP);
        if ($dbg === false) {
            throw new UnexpectedValueException('variable is NOT a valid ip address!');
        }
        return $variable;
    } else if ($type === 'ipv4') {
        $dbg = filter_var($variable, FILTER_VALIDATE_IP, array(
            'flags' => FILTER_FLAG_IPV4,
            'options' => array(
                'default' => false
            )
        ));
        if ($dbg === false) {
            throw new UnexpectedValueException('variable is NOT a valid ipv4 address!');
        }
        return $variable;
    } else if ($type === 'ipv6') {
        $dbg = filter_var($variable, FILTER_VALIDATE_IP, array(
            'flags' => FILTER_FLAG_IPV6,
            'options' => array(
                'default' => false
            )
        ));
        if ($dbg === false) {
            throw new UnexpectedValueException('variable is NOT a valid ipv6 address!');
        }
        return $variable;
    }
    $actual_type = gettype($variable);
    if ($actual_type === 'unknown type') {
        //i dont know how this can happen, but it is documented as a possible return value of gettype...
        throw new Exception('could not determine the type of the variable!');
    }
    if ($actual_type === 'object') {
        if (!is_a($variable, $type)) {
            $dbg = get_class($variable);
            throw new UnexpectedValueException('variable is an object which does NOT implement class: ' . $type . '. it is of class: ' . $dbg);
        }
        return $variable;
    }
    if ($actual_type === 'resource') {
        $dbg = get_resource_type($variable);
        if ($dbg !== $type) {
            throw new UnexpectedValueException('variable is a resource, which is NOT of type: ' . $type . '. it is of type: ' . $dbg);
        }
        return $variable;
    }
    //now a few special cases
    if ($type === 'bool') {
        $parsed_type = 'boolean';
    } else if ($type === 'int') {
        $parsed_type = 'integer';
    } else if ($type === 'float') {
        $parsed_type = 'double';
    } else if ($type === 'null') {
        $parsed_type = 'NULL';
    } else {
        $parsed_type = $type;
    }
    if ($parsed_type !== $actual_type && $type !== $actual_type) {
        throw new UnexpectedValueException('variable is NOT of type: ' . $type . '. it is of type: ' . $actual_type);
    }
    //ok, variable passed all tests.
    return $variable;
}



function hhb_curl_init($custom_options_array = array())
{
    if (empty($custom_options_array)) {
        $custom_options_array = array();
        //i feel kinda bad about this.. argv[1] of curl_init wants a string(url), or NULL
        //at least i want to allow NULL aswell :/
    }
    if (!is_array($custom_options_array)) {
        throw new InvalidArgumentException('$custom_options_array must be an array!');
    }
    ;
    $options_array = array(
        CURLOPT_AUTOREFERER => true,
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_COOKIESESSION => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FORBID_REUSE => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 11,
        CURLOPT_ENCODING => ""
        //CURLOPT_REFERER=>'example.org',
        //CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0'
    );
    if (!array_key_exists(CURLOPT_COOKIEFILE, $custom_options_array)) {
        //do this only conditionally because tmpfile() call..
        static $curl_cookiefiles_arr = array(); //workaround for https://bugs.php.net/bug.php?id=66014
        $curl_cookiefiles_arr[]            = $options_array[CURLOPT_COOKIEFILE] = tmpfile();
        $options_array[CURLOPT_COOKIEFILE] = stream_get_meta_data($options_array[CURLOPT_COOKIEFILE]);
        $options_array[CURLOPT_COOKIEFILE] = $options_array[CURLOPT_COOKIEFILE]['uri'];
        
    }
    //we can't use array_merge() because of how it handles integer-keys, it would/could cause corruption
    foreach ($custom_options_array as $key => $val) {
        $options_array[$key] = $val;
    }
    unset($key, $val, $custom_options_array);
    $curl = curl_init();
    if($curl===false){
		throw new RuntimeException('could not create a curl handle! curl_init() returned false');
	}
    curl_setopt_array($curl, $options_array);
    return $curl;
}


function hhb_curl_exec($ch, $url)
{
    static $hhb_curl_domainCache = "";
    //$hhb_curl_domainCache=&$this->hhb_curl_domainCache;
    //$ch=&$this->curlh;
    if (!is_resource($ch) || get_resource_type($ch) !== 'curl') {
        throw new InvalidArgumentException('$ch must be a curl handle!');
    }
    if (!is_string($url)) {
        throw new InvalidArgumentException('$url must be a string!');
    }
    
    $tmpvar = "";
    if (parse_url($url, PHP_URL_HOST) === null) {
        if (substr($url, 0, 1) !== '/') {
            $url = $hhb_curl_domainCache . '/' . $url;
        } else {
            $url = $hhb_curl_domainCache . $url;
        }
    }
    ;
    
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error (curl_errno=' . curl_errno($ch) . ') on url ' . var_export($url, true) . ': ' . curl_error($ch));
        // echo 'Curl error: ' . curl_error($ch);
    }
    if ($html === '' && 203 != ($tmpvar = curl_getinfo($ch, CURLINFO_HTTP_CODE)) /*203 is "success, but no output"..*/ ) {
        throw new Exception('Curl returned nothing for ' . var_export($url, true) . ' but HTTP_RESPONSE_CODE was ' . var_export($tmpvar, true));
    }
    ;
    //remember that curl (usually) auto-follows the "Location: " http redirects..
    $hhb_curl_domainCache = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST);
    return $html;
}


function hhb_curl_exec2($ch, $url, &$returnHeaders = array(), &$returnCookies = array(), &$verboseDebugInfo = "")
{
    $returnHeaders    = array();
    $returnCookies    = array();
    $verboseDebugInfo = "";
    if (!is_resource($ch) || get_resource_type($ch) !== 'curl') {
        throw new InvalidArgumentException('$ch must be a curl handle!');
    }
    if (!is_string($url)) {
        throw new InvalidArgumentException('$url must be a string!');
    }
    $verbosefileh = tmpfile();
    $verbosefile  = stream_get_meta_data($verbosefileh);
    $verbosefile  = $verbosefile['uri'];
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, $verbosefileh);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $html             = hhb_curl_exec($ch, $url);
    $verboseDebugInfo = file_get_contents($verbosefile);
    curl_setopt($ch, CURLOPT_STDERR, NULL);
    fclose($verbosefileh);
    unset($verbosefile, $verbosefileh);
    $headers       = array();
    $crlf          = "\x0d\x0a";
    $thepos        = strpos($html, $crlf . $crlf, 0);
    $headersString = substr($html, 0, $thepos);
    $headerArr     = explode($crlf, $headersString);
    $returnHeaders = $headerArr;
    unset($headersString, $headerArr);
    $htmlBody = substr($html, $thepos + 4); //should work on utf8/ascii headers... utf32? not so sure..
    unset($html);
    //I REALLY HOPE THERE EXIST A BETTER WAY TO GET COOKIES.. good grief this looks ugly..
    //at least it's tested and seems to work perfectly...
    $grabCookieName = function($str,&$len)
    {
        $len=0;
        $ret = "";
        $i   = 0;
        for ($i = 0; $i < strlen($str); ++$i) {
            ++$len;
            if ($str[$i] === ' ') {
                continue;
            }
            if ($str[$i] === '=') {
                --$len;
                break;
            }
            $ret .= $str[$i];
        }
        return urldecode($ret);
    };
    foreach ($returnHeaders as $header) {
        //Set-Cookie: crlfcoookielol=crlf+is%0D%0A+and+newline+is+%0D%0A+and+semicolon+is%3B+and+not+sure+what+else
        /*Set-Cookie:ci_spill=a%3A4%3A%7Bs%3A10%3A%22session_id%22%3Bs%3A32%3A%22305d3d67b8016ca9661c3b032d4319df%22%3Bs%3A10%3A%22ip_address%22%3Bs%3A14%3A%2285.164.158.128%22%3Bs%3A10%3A%22user_agent%22%3Bs%3A109%3A%22Mozilla%2F5.0+%28Windows+NT+6.1%3B+WOW64%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F43.0.2357.132+Safari%2F537.36%22%3Bs%3A13%3A%22last_activity%22%3Bi%3A1436874639%3B%7Dcab1dd09f4eca466660e8a767856d013; expires=Tue, 14-Jul-2015 13:50:39 GMT; path=/
        Set-Cookie: sessionToken=abc123; Expires=Wed, 09 Jun 2021 10:18:14 GMT;
        //Cookie names cannot contain any of the following '=,; \t\r\n\013\014'
        //
        */
        if (stripos($header, "Set-Cookie:") !== 0) {
            continue;
            /**/
        }
        $header = trim(substr($header, strlen("Set-Cookie:")));
        $len=0;
        while (strlen($header) > 0) {
            $cookiename                 = $grabCookieName($header,$len);
            $returnCookies[$cookiename] = '';
            $header                     = substr($header, $len + 1); //also remove the = 
            if (strlen($header) < 1) {
                break;
            }
            ;
            $thepos = strpos($header, ';');
            if ($thepos === false) { //last cookie in this Set-Cookie.
                $returnCookies[$cookiename] = urldecode($header);
                break;
            }
            $returnCookies[$cookiename] = urldecode(substr($header, 0, $thepos));
            $header                     = trim(substr($header, $thepos + 1)); //also remove the ;
        }
    }
    unset($header, $cookiename, $thepos);
    return $htmlBody;
}

function hhb_var_dump()
{
    //informative wrapper for var_dump
    //<changelog>
    //version 5 ( 1372510379573 )
    //v5, fixed warnings on PHP < 5.0.2 (PHP_EOL not defined),
    //also we can use xdebug_var_dump when available now. tested working with 5.0.0 to 5.5.0beta2 (thanks to http://viper-7.com and http://3v4l.org )
    //and fixed a (corner-case) bug with "0" (empty() considders string("0") to be empty, this caused a bug in sourcecode analyze)
    //v4, now (tries to) tell you the source code that lead to the variables
    //v3, HHB_VAR_DUMP_START and HHB_VAR_DUMP_END .
    //v2, now compat with.. PHP5.0 + i think? tested down to 5.2.17 (previously only 5.4.0+ worked)
    //</changelog>
    //<settings>
    $settings = array();
    $PHP_EOL  = "\n";
    if (defined('PHP_EOL')) { //for PHP >=5.0.2 ...
        $PHP_EOL = PHP_EOL;
    }
    
    $settings['debug_hhb_var_dump']   = false; //if true, may throw exceptions on errors..
    $settings['use_xdebug_var_dump']  = true; //try to use xdebug_var_dump (instead of var_dump) if available?
    $settings['analyze_sourcecode']   = true; //false to disable the source code analyze stuff.
    //(it will fallback to making $settings['analyze_sourcecode']=false, if it fail to analyze the code, anyway..)
    $settings['hhb_var_dump_prepend'] = 'HHB_VAR_DUMP_START' . $PHP_EOL;
    $settings['hhb_var_dump_append']  = 'HHB_VAR_DUMP_END' . $PHP_EOL;
    //</settings>
    
    $settings['use_xdebug_var_dump'] = ($settings['use_xdebug_var_dump'] && is_callable("xdebug_var_dump"));
    $argv                            = func_get_args();
    $argc                            = count($argv, COUNT_NORMAL);
    if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    } else if (version_compare(PHP_VERSION, '5.3.6', '>=')) {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    } else if (version_compare(PHP_VERSION, '5.2.5', '>=')) {
        $bt = debug_backtrace(false);
    } else {
        $bt = debug_backtrace();
    }
    ;
    $analyze_sourcecode = $settings['analyze_sourcecode'];
    //later, $analyze_sourcecode will be compared with $config['analyze_sourcecode']
    //to determine if the reason was an error analyzing, or if it was disabled..
    $bt                 = $bt[0];
    //<analyzeSourceCode>
    if ($analyze_sourcecode) {
        $argvSourceCode = array(
            0 => 'ignore [0]...'
        );
        try {
            if (version_compare(PHP_VERSION, '5.2.2', '<')) {
                throw new Exception("PHP version is <5.2.2 .. see token_get_all changelog..");
            }
            ;
            $xsource = file_get_contents($bt['file']);
            if (empty($xsource)) {
                throw new Exception('cant get the source of ' . $bt['file']);
            }
            ;
            $xsource .= "\n<" . '?' . 'php ignore_this_hhb_var_dump_workaround();'; //workaround, making sure that at least 1 token is an array, and has the $tok[2] >= line of hhb_var_dump
            $xTokenArray      = token_get_all($xsource);
            //<trim$xTokenArray>
            $tmpstr           = '';
            $tmpUnsetKeyArray = array();
            ForEach ($xTokenArray as $xKey => $xToken) {
                if (is_array($xToken)) {
                    if (!array_key_exists(1, $xToken)) {
                        throw new LogicException('Impossible situation? $xToken is_array, but does not have $xToken[1] ...');
                    }
                    $tmpstr = trim($xToken[1]);
                    if (empty($tmpstr) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
                        $tmpUnsetKeyArray[] = $xKey;
                        continue;
                    }
                    ;
                    switch ($xToken[0]) {
                        case T_COMMENT:
                        case T_DOC_COMMENT: //T_ML_COMMENT in PHP4 -.-
                        case T_INLINE_HTML: {
                            $tmpUnsetKeyArray[] = $xKey;
                            continue;
                        };
                        default: {
                            continue;
                        }
                    }
                } else if (is_string($xToken)) {
                    $tmpstr = trim($xToken);
                    if (empty($tmpstr) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
                        $tmpUnsetKeyArray[] = $xKey;
                    }
                    ;
                    continue;
                } else {
                    //should be unreachable..
                    //failed both is_array() and is_string() ???
                    throw new LogicException('Impossible! $xToken fails both is_array() and is_string() !! .. ');
                }
                ;
            }
            ;
            ForEach ($tmpUnsetKeyArray as $toUnset) {
                unset($xTokenArray[$toUnset]);
            }
            ;
            $xTokenArray = array_values($xTokenArray); //fixing the keys..
            //die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,'before:',count(token_get_all($xsource),COUNT_NORMAL),'after',count($xTokenArray,COUNT_NORMAL)));
            unset($tmpstr, $xKey, $xToken, $toUnset, $tmpUnsetKeyArray);
            //</trim$xTokenArray>
            $firstInterestingLineTokenKey = -1;
            $lastInterestingLineTokenKey  = -1;
            //<find$lastInterestingLineTokenKey>
            ForEach ($xTokenArray as $xKey => $xToken) {
                if (!is_array($xToken) || !array_key_exists(2, $xToken) || !is_integer($xToken[2]) || $xToken[2] < $bt['line'])
                    continue;
                $tmpkey = $xKey; //we don't got what we want yet..
                while (true) {
                    if (!array_key_exists($tmpkey, $xTokenArray)) {
                        throw new Exception('1unable to find $lastInterestingLineTokenKey !');
                    }
                    ;
                    if ($xTokenArray[$tmpkey] === ';') {
                        //var_dump(__LINE__.":SUCCESS WITH",$tmpkey,$xTokenArray[$tmpkey]);
                        $lastInterestingLineTokenKey = $tmpkey;
                        break;
                    }
                    //var_dump(__LINE__.":FAIL WITH ",$tmpkey,$xTokenArray[$tmpkey]);
                    
                    //if $xTokenArray has >=PHP_INT_MAX keys, we don't want an infinite loop, do we? ;p
                    //i wonder how much memory that would require though.. over-engineering, err, time-wasting, ftw?
                    if ($tmpkey >= PHP_INT_MAX) {
                        throw new Exception('2unable to find $lastIntperestingLineTokenKey ! (PHP_INT_MAX reached without finding ";"...)');
                    }
                    ;
                    ++$tmpkey;
                }
                break;
            }
            ;
            if ($lastInterestingLineTokenKey <= -1) {
                throw new Exception('3unable to find $lastInterestingLineTokenKey !');
            }
            ;
            unset($xKey, $xToken, $tmpkey);
            //</find$lastInterestingLineTokenKey>
            //<find$firstInterestingLineTokenKey>
            //now work ourselves backwards from $lastInterestingLineTokenKey to the first token where $xTokenArray[$tmpi][1] == "hhb_var_dump"
            //i doubt this is fool-proof but.. cant think of a better way (in userland, anyway) atm..
            $tmpi = $lastInterestingLineTokenKey;
            do {
                if (array_key_exists($tmpi, $xTokenArray) && is_array($xTokenArray[$tmpi]) && array_key_exists(1, $xTokenArray[$tmpi]) && is_string($xTokenArray[$tmpi][1]) && strcasecmp($xTokenArray[$tmpi][1], $bt['function']) === 0) {
                    //var_dump(__LINE__."SUCCESS WITH",$tmpi,$xTokenArray[$tmpi]);
                    if (!array_key_exists($tmpi + 2, $xTokenArray)) { //+2 because [0] is (or should be) "hhb_var_dump" and [1] is (or should be) "("
                        throw new Exception('1unable to find the $firstInterestingLineTokenKey...');
                    }
                    ;
                    $firstInterestingLineTokenKey = $tmpi + 2;
                    break;
                    /**/
                }
                ;
                //var_dump(__LINE__."FAIL WITH ",$tmpi,$xTokenArray[$tmpi]);
                --$tmpi;
            } while (-1 < $tmpi);
            //die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,$tmpi));
            if ($firstInterestingLineTokenKey <= -1) {
                throw new Exception('2unable to find the $firstInterestingLineTokenKey...');
            }
            ;
            unset($tmpi);
            //Note: $lastInterestingLineTokeyKey is likely to contain more stuff than only the stuff we want..
            //</find$firstInterestingLineTokenKey>
            //<rebuildInterestingSourceCode>
            //ok, now we have $firstInterestingLineTokenKey and $lastInterestingLineTokenKey....
            $interestingTokensArray = array_slice($xTokenArray, $firstInterestingLineTokenKey, (($lastInterestingLineTokenKey - $firstInterestingLineTokenKey) + 1));
            unset($addUntil, $tmpi, $tmpstr, $tmpi, $argvsourcestr, $tmpkey, $xTokenKey, $xToken);
            $addUntil      = array();
            $tmpi          = 0;
            $tmpstr        = "";
            $tmpkey        = "";
            $argvsourcestr = "";
            //$argvSourceCode[X]='source code..';
            ForEach ($interestingTokensArray as $xTokenKey => $xToken) {
                if (is_array($xToken)) {
                    $tmpstr = $xToken[1];
                    //var_dump($xToken[1]);
                } else if (is_string($xToken)) {
                    $tmpstr = $xToken;
                    //var_dump($xToken);
                } else {
                    /*should never reach this */
                    throw new LogicException('Impossible situation? $xToken fails is_array() and fails is_string() ...');
                }
                ;
                $argvsourcestr .= $tmpstr;
                
                
                if ($xToken === '(') {
                    $addUntil[] = ')';
                    continue;
                } else if ($xToken === '[') {
                    $addUntil[] = ']';
                    continue;
                }
                ;
                
                if ($xToken === ')' || $xToken === ']') {
                    if (false === ($tmpkey = array_search($xToken, $addUntil, false))) {
                        $argvSourceCode[] = substr($argvsourcestr, 0, -1); //-1 is to strip the ")"
                        if (count($argvSourceCode, COUNT_NORMAL) - 1 === $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
                            break;
                            /*We read em all! :D (.. i hope)*/
                        }
                        ;
                        /*else... oh crap*/
                        throw new Exception('failed to read source code of (what i think is) argv[' . count($argvSourceCode, COUNT_NORMAL) . '] ! sorry..');
                    }
                    unset($addUntil[$tmpkey]);
                    continue;
                }
                
                if (empty($addUntil) && $xToken === ',') {
                    $argvSourceCode[] = substr($argvsourcestr, 0, -1); //-1 is to strip the comma
                    $argvsourcestr    = "";
                }
                ;
            }
            ;
            //die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,
            //$firstInterestingLineTokenKey,$lastInterestingLineTokenKey,$interestingTokensArray,$tmpstr
            //$argvSourceCode));
            if (count($argvSourceCode, COUNT_NORMAL) - 1 != $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
                throw new Exception('failed to read source code of all the arguments! (and idk which ones i missed)! sorry..');
            }
            ;
            //</rebuildInterestingSourceCode>
        }
        catch (Exception $ex) {
            $argvSourceCode     = array(); //clear it
            //TODO: failed to read source code
            //die("TODO N STUFF..".__FILE__.__LINE__);
            $analyze_sourcecode = false; //ERROR..
            if ($settings['debug_hhb_var_dump']) {
                throw $ex;
            } else {
                /*exception ignored, continue as normal without $analyze_sourcecode */
            }
            ;
        }
        unset($xsource, $xToken, $xTokenArray, $firstInterestingLineTokenKey, $lastInterestingLineTokenKey, $xTokenKey, $tmpi, $tmpkey, $argvsourcestr);
    }
    ;
    //</analyzeSourceCode>
    $msg = $settings['hhb_var_dump_prepend'];
    if ($analyze_sourcecode != $settings['analyze_sourcecode']) {
        $msg .= ' (PS: some error analyzing source code)' . $PHP_EOL;
    }
    ;
    $msg .= 'in "' . $bt['file'] . '": on line "' . $bt['line'] . '": ' . $argc . ' variable' . ($argc === 1 ? '' : 's') . $PHP_EOL; //because over-engineering ftw?
    if ($analyze_sourcecode) {
        $msg .= ' hhb_var_dump(';
        $msg .= implode(",", array_slice($argvSourceCode, 1)); //$argvSourceCode[0] is bullshit.
        $msg .= ')' . $PHP_EOL;
    }
    //array_unshift($bt,$msg);
    echo $msg;
    $i = 0;
    foreach ($argv as &$val) {
        echo 'argv[' . ++$i . ']';
        if ($analyze_sourcecode) {
            echo ' >>>' . $argvSourceCode[$i] . '<<<';
        }
        echo ':';
        if ($settings['use_xdebug_var_dump']) {
            xdebug_var_dump($val);
        } else {
            var_dump($val);
        }
        ;
    }
    
    echo $settings['hhb_var_dump_append'];
    //call_user_func_array("var_dump",$args);
}

function hhb_return_var_dump() //works like var_dump, but returns a string instead of printing it.
{
    $args = func_get_args(); //for <5.3.0 support ...
    ob_start();
    call_user_func_array('var_dump', $args);
    return ob_get_clean();
}
;

function hhb_bin2readable($data, $min_text_len = 3, $readable_min = 0x40, $readable_max = 0x7E)
{
    $ret    = "";
    $strbuf = "";
    $i      = 0;
    for ($i = 0; $i < strlen($data); ++$i) {
        if ($min_text_len > 0 && ord($data[$i]) >= $readable_min && ord($data[$i]) <= $readable_max) {
            $strbuf .= $data[$i];
            continue;
        }
        if (strlen($strbuf) >= $min_text_len && $min_text_len > 0) {
            $ret .= " " . $strbuf . " ";
        } elseif (strlen($strbuf) > 0 && $min_text_len > 0) {
            $ret .= bin2hex($strbuf);
        }
        $strbuf = "";
        $ret .= bin2hex($data[$i]);
    }
    if (strlen($strbuf) >= $min_text_len && $min_text_len > 0) {
        $ret .= " " . $strbuf . " ";
    } elseif (strlen($strbuf) > 0 && $min_text_len > 0) {
        $ret .= bin2hex($strbuf);
    }
    $strbuf = "";
    return $ret;
}
function hhb_init()
{
    error_reporting(E_ALL);
    set_error_handler("hhb_exception_error_handler");
    //	ini_set("log_errors",true);
    //	ini_set("display_errors",true);
    //	ini_set("log_errors_max_len",0);
    //	ini_set("error_prepend_string",'<error>');
    //	ini_set("error_append_string",'</error>'.PHP_EOL);
    //	ini_set("error_log",__DIR__.'/error_log.php');
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
function hhb_combine_filepaths( /*...*/ )
{
    $args = func_get_args();
    if (count($args) == 0) {
        return "";
    }
    $ret = "";
    $i   = 0;
    foreach ($args as $arg) {
        ++$i;
        if ($i != 1) {
            $ret .= '/';
        }
        $ret .= str_replace("\\", '/', $arg) . '/';
    }
    while (false !== stripos($ret, '//')) {
        $ret = str_replace('//', '/', $ret);
    }
    if (strlen($ret) < 2) {
        return $ret; //very edge case scenario, a single / or \ empty
    }
    if ($ret[strlen($ret) - 1] == '/') {
        $ret = substr($ret, 0, -1);
    }
    return $ret;
}
