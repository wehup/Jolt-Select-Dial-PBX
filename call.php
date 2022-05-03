<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

define("AMI","/etc/asterisk/manager.conf");

define("SNEP2","/var/www/snep2/includes/setup.conf");
define("ELASTIX","/etc/elastix.conf");
define("ISSABEL","/etc/issabel.conf");

define("SPOOL_DIR","/var/spool/asterisk/outgoing/");

$strUser = "admin"; #specify the asterisk manager username you want to login with
$strSecret = "pass123"; #specify the password for the above user
$strHost = "127.0.0.1";
$port = 5038;

$ami = file_exists(AMI) ? @parse_ini_file(AMI) : false;
$elastix = file_exists(ELASTIX) ? @parse_ini_file(ELASTIX) : false;
$issabel = file_exists(ISSABEL) ? @parse_ini_file(ISSABEL) : false;
$snep2 = file_exists(SNEP2) ? @parse_ini_file(SNEP2) : false;

# Tring to detect AMI parameters
if ($ami) {
    $strUser = $snep2 ? $snep2['user_sock'] : ($elastix || $issabel ? "admin" : $strUser);
    $strSecret = $snep2 ? $snep2['pass_sock'] : ($elastix ? $elastix['amiadminpwd'] : ($issabel ? $issabel['amiadminpwd'] : (isset($ami['secret']) ? $ami['secret'] : $strSecret)));

    $strHost = $snep2 ? $snep2['ip_sock'] : '127.0.0.1';
    $port = $ami ? (isset($ami['port']) ? intval($ami['port']) : 5038) : 5038;
}

//Clean up EXT
$src = preg_replace("/[^0-9,.]/","",filter_var(isset($_GET['source']) ? $_GET['source'] : $_GET['exten'], FILTER_SANITIZE_NUMBER_INT));
$dst = preg_replace("/[^0-9,.]/","",filter_var(isset($_GET['number']) ? $_GET['number'] : (isset($_GET['phone']) ? $_GET['phone'] : $_GET['destination']), FILTER_SANITIZE_NUMBER_INT));

if (empty($src) || empty($dst)) {
    echo "400";
    exit();
}

$strChannel = "SIP/".$src; # @TODO: For SNEP we can check the channek in 'peers' table
$strContext = $snep2 ? "default" : "from-internal";

$strWaitTime = "30"; #Wait Time before hangin up
$strPriority = "1";
$strMaxRetry = "2"; #maximum amount of retries

$errno=0;
$errstr=0 ;

//OPEN CNAM LOOKUP
$callerid = @file_get_contents("https://api.opencnam.com/v2/phone/".$dst) or $callerid = "Web Call";
$strCallerId = $callerid." <$dst>";

$content = "Channel: " . $strChannel . "\n";
$content .= "Callerid: " . $strCallerId . "\n";
$content .= "WaitTime: " . $strWaitTime . "\n";
$content .= "MaxRetries: " . $strMaxRetry . "\n";
$content .= "Priority: " . $strPriority . "\n";
$content .= "Context: " . $strContext . "\n";
$content .= "Extension: " . $dst . "\n";
$content .= "Archive: no" . "\n";

$archive = SPOOL_DIR . time() . "-" . $src . "_" . $dst;

if (file_put_contents($archive, $content)) {
    echo  (isset($_GET['source']) && isset($_GET['destination'])) ? "200" : "Extension $src should be calling $dst.";
} else {
    $oSocket = fsockopen ($strHost, $port, $errno, $errstr, 20);

    if (!$oSocket) {
        echo (isset($_GET['source']) && isset($_GET['destination'])) ? "500" : "$errstr ($errno)<br>\n";
    } else {
        fputs($oSocket, "Action: login\r\n");
        fputs($oSocket, "Events: off\r\n");
        fputs($oSocket, "Username: $strUser\r\n");
        fputs($oSocket, "Secret: $strSecret\r\n\r\n");
        fputs($oSocket, "Action: originate\r\n");
        fputs($oSocket, "Channel: $strChannel\r\n");
        fputs($oSocket, "WaitTime: $strWaitTime\r\n");
        fputs($oSocket, "CallerId: $strCallerId\r\n");
        fputs($oSocket, "Exten: $number\r\n");
        fputs($oSocket, "Context: $strContext\r\n");
        fputs($oSocket, "Priority: $strPriority\r\n\r\n");
        fputs($oSocket, "Action: Logoff\r\n\r\n");

        sleep(2);
        fclose($oSocket);

        echo  (isset($_GET['source']) && isset($_GET['destination'])) ? "200" : "Extension $src should be calling $dst.";
    }
}
?>
