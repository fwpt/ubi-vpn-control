<?php

// basic http auth; need to improve this later
// for now, ensure this is only available over https
$user = filter_input(INPUT_SERVER, 'PHP_AUTH_USER', FILTER_SANITIZE_STRING);
$pass = filter_input(INPUT_SERVER, 'PHP_AUTH_PW', FILTER_SANITIZE_STRING);

if (! (($user === "set your username here") && ($pass === "set your random and strong password here . . !"))) {
    header('WWW-Authenticate: Basic realm="Login"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentcation required';
    exit;
}

$action = filter_input(INPUT_GET, 'a', FILTER_SANITIZE_STRING);

switch ($action) {

    case 'getnewservicestatus':

        // request the new requested service status
        logRequest('getNewServiceStatus request (scheduled)', 'server', 3);
        echo getCurrentStatus();
        break;

    case 'servicereport':

        if (!setCurrentStatus('reset', 'server')) logRequest('setCurrentStatus failed', 'app', 3);

        $log = '';
        $status = filter_input(INPUT_GET, 's', FILTER_SANITIZE_STRING);
        switch ($status) {
            case 'started': $log = 'STARTED'; break;
            case 'stopped_forced': $log = 'FORCED STOP (forced by dashboard)'; break;
            case 'stopped_disconnect': $log = 'STOPPED (client disconnected)'; break;
            default: $log = 'INVALID servicereport'; 
        }
        logRequest($log, 'server', 1);
        echo 'ok';

        break;

    case 'controlstatus':
        // dashboard
        showDashboard();
        break;

    case 'dbupdatestatus':

        // update status via form on dashboard
        $newstatus = filter_input(INPUT_POST, 'newstatus', FILTER_SANITIZE_STRING);
        if (!setCurrentStatus($newstatus, 'dashboard')) echo 'Error updating status.';
        else {
            header('Location: status.php?a=controlstatus&ts=' . time());
            exit;
        }
        break;

    default:

        invalidRequest();
        break;
}

function logRequest($txt, $actor, $level = 1) {

    // 1: high, 2: low, 3: debug
    //if (true) echo 'Log (' . $level . '): ' . $txt . '<br>' . PHP_EOL;
    if ($level > 2) return;

    $ts = date('d-m-Y H:i:s');
    $ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_URL);

    $sep = '    '; // tab separator
    $log = $ts . $sep . $ip . $sep . '[' . $actor . ']' . $sep . $txt . PHP_EOL;

    $res = file_put_contents('logstatus.txt', $log, FILE_APPEND);
    if ($res === false) echo 'Log error!';
}

function getLogData($maxLines = 100) {

    // @TODO: improve to not read whole file in memory
    $data = file_get_contents('logstatus.txt');
    $lines = explode(PHP_EOL, $data);
    $lineCount = count($lines);
    if ($lineCount == 0) return array();
    if ($lineCount < $maxLines) $maxLines = $lineCount;
    return array_slice($lines, -$maxLines);
}

function getCurrentStatus() {

    // 0: reset/do nothing, 1: start VPN service, 2: stop VPN service (force kill)    
    $status = file_get_contents('curstatus.txt');
    if ($status === false) $status = '0';

    return $status;
}

function getCurrentStatusText() {

    $status = getCurrentStatus();
    $statusText = 'None';
    if ($status == '1') $statusText = 'Start VPN';
    elseif ($status == '2') $statusText = 'Stop/kill VPN';

    return $status . ' - ' . $statusText;
}

function setCurrentStatus($statusReq, $actor) {

    $status = 0; // reset/stop
    $statusReq = filter_var($statusReq, FILTER_SANITIZE_STRING);
    if ($statusReq == 'start') $status = 1;
    elseif ($statusReq == 'kill') $status = 2;

    logRequest('setCurrentStatus to ' . $status . ' (' . $statusReq . ')', $actor, 2);

    $res = file_put_contents('curstatus.txt', $status);
    return $res !== false;
}

function invalidRequest() {

    exit('400: Bad request');
}

function showDashboard() {

    echo '<html><head><title>VPN Dashboard</title></head><body>';

    echo '<div id="curstatus">';
    echo 'Current status requested: ' . getCurrentStatusText();
    echo '<br><br>';
    echo '</div>';

    echo '<div id="controlstatus">';
    echo '<form method="POST" action="?a=dbupdatestatus"><select name="newstatus">';
    echo '<option value="0">Reset</option>';
    echo '<option value="start" selected="selected">Start VPN</option>';
    echo '<option value="kill">Stop/kill VPN</option>';
    echo '</select><input type="submit" name="submit" value="Update"></form>';
    echo '<br><br>';
    echo '</div>';

    echo '<div id="logstatus">';
    $lines = getLogData(16);
    $lines = array_reverse($lines);
    foreach ($lines as $line) echo htmlentities($line) . '<br>';
    echo '<br><a href="logstatus.txt">Full log</a><br><br>';
    echo '</div>';

    echo '</body></html>';
}