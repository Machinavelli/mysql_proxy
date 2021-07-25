<?php
require 'result.php';
require 'logger.php';
require 'connectors/mysql.php';

$settings_loc = parse_ini_file ('settings.ini');
$LOGFILE = $settings_loc['LOGFILE'] or die('Please set up LOGFILE value in settings.ini');
$CREDENTIALS_FILE = $settings_loc['CREDENTIALS'] or die('Please set up CREDENTIALS value in settings.ini');

define("logfile", $LOGFILE);
define("DB_EXTENSION", "mysqli");
define( "charset", 'utf8');
define( "DEBUG", 1 );

ini_set('display_startup_errors', 0);
ini_set('display_errors', 0);


function require_auth() {
  Logger::info( "Remote = " . get_user_ip() );

  $has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));
  $is_not_authenticated = (
    !$has_supplied_credentials ||
    $_SERVER['PHP_AUTH_USER'] != auth_user ||
    $_SERVER['PHP_AUTH_PW']   != auth_pw
  );
  if ($is_not_authenticated) {
    Logger::warn( "Refusing to serve (unauthorized) ip = " . get_user_ip() . 
      " User = " . $_SERVER['PHP_AUTH_USER'] );
    return false;
  } else {
    return true;
  }
}

function is_secure_link() {
  if(is_localhost()){
    return true;
  }
  return
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;
}

function get_user_ip(){
  if(!empty($_SERVER['HTTP_CLIENT_IP'])){
    //ip from share internet
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
    //ip pass from proxy
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }else{
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  return $ip;
}

function is_localhost(){
  $addr = get_user_ip();
  return ($addr == 'localhost' || $addr == '127.0.0.1' || $addr == '::1') 
    && !array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER);
}

function is_allowed_ip($list) {
  $addr = get_user_ip();
  if($list == null){
    return true;
  } else if (is_localhost()) {
    return true;
  } else if (in_array($addr, explode(',', $list))) {
    return true;
  } else {
    return false;
  }
}

function refine_value($str){
  if(get_magic_quotes_gpc())
    return stripslashes($str);
  return $str;
}

function get_request_value($name){
  if(array_key_exists($name,$_POST)){
    $value=$_POST[$name];
  } else if(array_key_exists($name,$_GET)){
    $value=$_GET[$name];
  } else {
    return null;
  }

  if(!is_array($value))
    return refine_value($value);
  $ret=array();
  foreach($value as $key=>$val)
    $ret[$key]=refine_value($val);
  return $ret;
}


function parse_query($query){
  $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $query);
  if(count($queries) > 1){
    Logger::warn("Multiple queries in single request not supported!");
  }
  return $queries[0];
}

/* 
  Handling of mysql & custom errors
  Return error response
*/
function handle_error($errno, $error, $query = null){
  $res = new Result('array');
  $res->error_number = $errno;
  $res->error_desc = $error;
  if ($query) {
    $res->query = $query;  
  }
  return $res;
}


function process_request ($host, $port, $dbname, $username, $password, $query){
  $par = array(
    'host' => $host,
    'port' => $port,
    'username' => $username,
    'password' => $password,
    'dbname' => $dbname
  );
  $conn_wrap = new MysqlConnector($par, null);

  $mysqli = $conn_wrap->connect();

  if ( $mysqli == null){
    return handle_error ( 0, 'Failed to connect to DB');
  } else {
    $query = parse_query($query);
    $result_object = $conn_wrap->query($query);
    
    $conn_wrap->disconnect();
    return $result_object;
  }
}

function process_inputs(){
  global $CREDENTIALS_FILE;
  $opts = array();

  $credentials = parse_ini_file ( $CREDENTIALS_FILE );
  $opts['allow_ips'] = array_key_exists ( "allowed_ips" , $credentials ) ? $credentials["allowed_ips"] : null;

  $opts['host'] = $credentials["hostname"];
  $opts['port'] = $credentials["port"];
  $opts['dbname'] =  $credentials["dbname"];
  $opts['username']   = $credentials["username"];
  $opts['password']   = $credentials["password"];
  $opts['secret']     = array_key_exists ( "key" , $credentials ) ? $credentials["key"] : null;
  $opts['query']      = get_request_value("query") ?: null;
  $opts['out_format']  = get_request_value('format')?: null;
  define("auth_pw", $credentials["proxy_pass"]);
  define("auth_user", $credentials["proxy_user"]);

  return $opts;
}

// DO IT!
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Content-type:application/json;charset=utf-8');

try {
  $result = null;

  if (!is_secure_link()) {
    Logger::info( "Request was sent over unsecured link (" . $_SERVER['HTTP_HOST'] . ", " . get_user_ip() . ")" );
    $result = handle_error(400, 'Insecure link.');
  }

  $opts = process_inputs();
  //Convert Query to defined charset
  if (charset <> "") {
    $opts['query'] = mb_convert_encoding($opts['query'], charset, "UTF-8");
  } else {
    Logger::info( "Query =" . $opts['query'] );
  }

  if ( strlen ( $opts['query'] ) == 0 ) {
    Logger::info( "Query was blank" );
    $result = handle_error(400, '');
  } else {
    Logger::info( "Query = " . $opts['query']);
  }

  if (!require_auth()){
    $result = handle_error(401, 'Unauthorized');
  }

  if(!is_allowed_ip($opts['allow_ips'])){
    Logger::info( "Refusing to serve (forbidden ip)= " . get_user_ip() );
    $result = handle_error(403, '');
  }
  
  if ($result){
    $result->print_result();
    die;
  } else {
    $result = process_request($opts['host'], 
                              $opts['port'], 
                              $opts['dbname'],
                              $opts['username'],
                              $opts['password'],
                              $opts['query']);
    $result->print_result();
  }

  
} catch (Exception| Error $e) {
  Logger::error('There was an error', $e);
  $result = handle_error(500, 'Internal error');
  $result->print_result();
} finally {
  Logger::close();
}
