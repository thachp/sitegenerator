<?php
class Lib_console extends Lib_default {

  private $_my_url = "http://www.example.com/network/";

  /**
   * Send async Request
   * @param $the_action
   */
  public function fire_forget($the_action = false)
  {

    if($the_action == false)
    {
      throw new Console_exception("Invalid action");
    }
    
    $the_url = $this->_my_url. $the_action;
    $this->_curl_post_async($the_url, $_POST);
  }

  /**
   * Send Curl Async
   * @param $url
   * @param array $params
   */
  private function _curl_post_async($url, $params = array())
  {
    // create POST string
    $post_params = array();
    foreach ($params as $key => &$val)
    {
      $post_params[] = $key . '=' . urlencode($val);
    }
    $post_string = implode('&', $post_params);

    // get URL segments
    $parts = parse_url($url);

    // workout port and open socket
    $port = isset($parts['port']) ? $parts['port'] : 80;
    $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);

    // create output string
    $output  = "POST " . $parts['path'] . " HTTP/1.1\r\n";
    $output .= "Host: " . $parts['host'] . "\r\n";
    $output .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $output .= "Content-Length: " . strlen($post_string) . "\r\n";
    $output .= "Connection: Close\r\n\r\n";
    $output .= isset($post_string) ? $post_string : '';

    // send output to $url handle
    fwrite($fp, $output);
    fclose($fp);
  }
}

class Console_exception extends Exception {
} // end exception
