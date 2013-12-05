<?php

/**
 * The goal for this project is to build a robust system to automatically launch a website.
 * In order to achieve this project, the system will need to be able
 *    1. register a domain name.
 *    2. create a new ethach website
 *    3. create categories
 *    4. search youtube for videos related to the categories. import the videos in the database.
 *    5. search wikipedia for articles related to the categories. import related articles into the database.
 */

require_once('config.php');
require_once('../wp-load.php');
require_once('libraries/lib_loader.php');
require_once('libraries/fnc_utilities.php');

/**
 * Sweet actions based on call.
 */

$ED->topics = isset($_POST["topics"]) ? $_POST["topics"] : FALSE;
$ED->domain = isset($_POST["domain"]) ? $_POST["domain"] : FALSE;
$ED->title = isset($_POST["title"]) ? $_POST["title"] : FALSE;
$the_callback_email = isset($_POST["email"]) ? $_POST["email"] : "thachp@gmail.com";

switch (get_action()) {
  case "suggest":
    try {
      $ED->results = $et->load("website")->set_topics($ED->topics)->generate_domains()->get_data();

      if(isset($ED->results["keywords"]))
      {
        $ED->keywords = implode(", ", $ED->results["keywords"]);
      }

      if(!empty($ED->results["domains"]))
      {
        $ED->domains = implode(", ", $ED->results["domains"]);
      } else {
        $ED->domains = "No domains found.";
      }

      // unset
      unset($ED->results);

    } catch (Website_exception $e) {
      $ED->errors[] = $e->getMessage();
    }

    break;

  case "create":
    try {
      $ED->results = $et->load("website")->set_topics($ED->topics)->set_domain($ED->domain)->register_domain()->update_domain_hosts()->create_site($ED->title)
        ->map_domains()->generate_keywords()->add_site_keywords()->get_data();
    } catch (Website_exception $e) {
      $ED->errors[] = $e->getMessage();
    }
    break;
  case "quick":
    try {
      $ED->results = $et->load("website")->set_topics($ED->topics)->generate_domains(true)->register_domain()->update_domain_hosts()->create_site($ED->title)
        ->map_domains()->generate_keywords()->add_site_keywords()->get_data();
    } catch (Website_exception $e) {
      $ED->errors[] = $e->getMessage();
    }
    break;

  case "import":
//    // import youtube videos into the database
    try {
      $ED->results = $et->load("youtube")->set_domain($ED->domain)->import();
    } catch (Youtube_exception $e) {
      $ED->errors[] = $e->getMessage();
    }

    // import wikipedia articles into the database
//    try {
//      $ED->results = $et->load("wikipedia")->set_domain($ED->domain)->import();
//      } catch (Wikipedia_exception $e) {
//      $ED->errors[] = $e->getMessage();
//    }

    break;

  case "console" :
    try {

      if(get_action(3) == "404")
      {
        $et->load("console")->fire_forget(get_action(3) . "/" . get_action(4) . "/" . get_action(5));
        // redirect back to homepage
        header("Location: http://www." .get_action(4));
        die();
      }else {
        $et->load("console")->fire_forget(get_action(3));
      }
      $ED->email = " Job received with action: " . get_action(3) . ". ". $the_callback_email . " will be notified once this job is completed.";
    } catch (Console_exception $e) {
      $ED->errors[] = $e->getMessage();
    }
   break;

  case "404" :
    // import wikipedia article
    $ED->domain  = get_action(3);
    $ED->topics = get_action(4);

    try {
      $ED->results = $et->load("wikipedia")->set_domain($ED->domain)->import($ED->topics);
    } catch (Wikipedia_exception $e) {
      $ED->errors[] = $e->getMessage();
    }

    break;
}

/**
 * Notify administrators.
 */

if(in_array(get_action(2), array("create","quick","suggest","import","404")))
{
  if(wp_mail($the_callback_email, "Ethach " . ucfirst(get_action()), json_encode($ED)));
  {
    $ED->email = "Emailed sent.";
  }
}

output_json($ED);

?>
