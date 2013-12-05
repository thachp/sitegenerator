<?php

/**
 * This default class provide abstraction to subclasses.
 *
 * @author Patrick Thach
 * @since August 25, 2013
 *
 */

class Lib_Loader {

  private $my_class_prefix;

  // object instances
  private static $my_registry = array();

  // Constructor
  public function __construct() {
    $this->_set_prefix();
    $this->_include_lib();
  }

  /**
   * Load singleton sub objects.
   * @param $the_object_name
   * @param $the_object_params
   * @return mixed
   */

  public function load($the_object_name, $the_object_params = NULL) {

    $the_object_name = $this->my_class_prefix . "_" . $the_object_name;
    try {
      if (array_key_exists($the_object_name, self::$my_registry)) {
        return self::$my_registry[$the_object_name];
      }
      else {


        self::$my_registry[$the_object_name] = new $the_object_name($the_object_params);
        return self::$my_registry[$the_object_name];
      }
    } catch (Exception $e) {
      throw new Load_exception("Could not load object : " . $the_object_name);
    }
  }

  /**
   * Set class name.
   */
  private function _set_prefix() {
    $this->my_class_prefix = "Lib";
  }

  /**
   * Load all libraries
   */

  private function _include_lib()
  {
    // load default
    include_once(__dir__ . "/lib_default.php");

    $the_dir_path = __dir__ . "/*.php";
    foreach (glob($the_dir_path) as $filename)
    {
      if($filename == "lib_default")
      {
        continue;
      }

      include_once($filename);
    }
  }


} // end default library

class Load_exception extends Exception {
} // end exception

$et = new Lib_Loader();
$ED = new StdClass();
