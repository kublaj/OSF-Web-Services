<?php

/*! @ingroup WsAuth Authentication / Registration Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\lister\index.php
    @brief Entry point of a query for the Auth Validator web service
 */
 
include_once("../../../../SplClassLoader.php"); 
  
use \StructuredDynamics\structwsf\ws\revision\lister\RevisionLister;
use \StructuredDynamics\structwsf\ws\framework\Logger; 
 
// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

// Check if the HTTP method used by the requester is the good one
if ($_SERVER['REQUEST_METHOD'] != 'GET') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// Interface to use for this query
$interface = "default";

if(isset($_GET['interface']))
{
  $interface = $_GET['interface'];
}

// Version of the requested interface to use for this query
$version = "";

if(isset($_GET['version']))
{
  $version = $_GET['version'];
}

// Verbosity of the revision record to return with the endpoint
$mode = "short";
 
if(isset($_GET['mode']))
{
  $mode = $_GET['mode'];
}

$registered_ip = "";

if(isset($_GET['registered_ip']))
{
  $registered_ip = $_GET['registered_ip'];
}

$dataset = "";

if(isset($_GET['dataset']))
{
  $dataset = $_GET['dataset'];
}

$uri = "";

if(isset($_GET['uri']))
{
  $uri = $_GET['uri'];
}


$mtime = microtime();
$mtime = explode(' ', $mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

$start_datetime = date("Y-m-d h:i:s");

$requester_ip = "0.0.0.0";

if(isset($_SERVER['REMOTE_ADDR']))
{
  $requester_ip = $_SERVER['REMOTE_ADDR'];
}

$parameters = "";

if(isset($_SERVER['REQUEST_URI']))
{
  $parameters = $_SERVER['REQUEST_URI'];

  $pos = strpos($parameters, "?");

  if($pos !== FALSE)
  {
    $parameters = substr($parameters, $pos, strlen($parameters) - $pos);
  }
}
elseif(isset($_SERVER['PHP_SELF']))
{
  $parameters = $_SERVER['PHP_SELF'];
}

$ws_rl = new RevisionLister($uri, $dataset, $mode, $registered_ip, $requester_ip, $interface, $version);

$ws_rl->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_rl->process();

$ws_rl->ws_respond($ws_rl->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_rl->isLoggingEnabled())
{  
  
}

//@}

?>