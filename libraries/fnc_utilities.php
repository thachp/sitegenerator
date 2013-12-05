<?php

/**
 * Contain general functions.
 * @author Patrick Thach, Ine.com
 * Date: 8/26/13
 * Time: 10:44 PM
 */

/**
 * Print Data for Debugging Purposes.
 * @param string $the_data
 */
function p($the_data = "got here") {
  echo("<pre>");

  if (is_array($the_data) || is_object($the_data)) {
    print_r($the_data);
  }
  else {
    echo($the_data);
  }
  echo("</pre>");
}

/**
 * Print and DIE data.
 * @param string $the_data
 */
function g($the_data = "got here") {
  p($the_data);
  die();
}

/**
 * Log message.
 * @param string $message
 * @param null $the_data
 */
function log_message($message = "got here", $the_data = null)
{
  $the_file = fopen(__DIR__ . "/logs/".date("Y-m-d").".log", "a+");
  $data["timestamp"] = date("Y-m-d H:i:s");
  $data["message"] = $message;

  if(isset($the_data))
  {
    $data["data"] = $the_data;
  }
  fwrite($the_file,json_encode($data) . "\n");
  fclose($the_file);
}

/**
 * Return actions.
 * @return mixed
 */
function get_action($the_param = 2) {
  $actions = explode("/", $_SERVER["REQUEST_URI"]);

  if (isset($actions[$the_param])) {
    return $actions[$the_param];
  }

  return false;
}

/**
 * Output to JSON
 */
function output_json() {
  global $ED;
  header('Content-Type: application/json');

  $properties = get_object_vars($ED);

  foreach($properties as $name => $value)
  {
    if($ED->$name == false)
    {
      unset($ED->$name);
    }
  }

  if(isset($ED->email))
  {
    echo json_encode($ED->email);
  } else {
    echo json_encode($ED);
  }
}