<?php

/**
 * This default class should be extended by all classes.
 *
 * @author Patrick Thach, Ine.com
 * @since September 26, 2012
 *
 */

class Lib_default {

  protected $_my_data;
  protected $_my_html;


  /**
   * Return the HTML string.
   * @param $the_html_file
   */
  protected function _output_html($the_html_file)
  {
    $view = $this->_my_data;
    ob_start();
    include("views/" . $the_html_file . ".php");
    $this->_my_html = ob_get_clean();
  }

  /**
   * Merge array to $m5
   * @param array $the_data
   */
  protected function _output_data(array $the_data)
  {
    $this->_my_data = array_merge($this->_my_data, $the_data);
  }

  /**
   * Return my data.
   * @return array
   */
  public function get_data()
  {
    return $this->_my_data;
  }

  /**
   * Return HTML.
   * @return string
   */
  public function get_html()
  {
    return $this->_my_html;
  }


  /**
   * Get blog id from domain name.
   * @param $the_domain
   */

  public function _get_blog_id($the_domain)
  {
    global $wpdb;
    $the_blog = $wpdb->get_row("SELECT blog_id FROM e_blogs where domain LIKE '%{$the_domain}'" );

    if(isset($the_blog->blog_id))
    {
      return $the_blog->blog_id;
    }

    return false;
  }

} // end default library

class Default_exception extends Exception {
} // end exception