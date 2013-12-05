<?php

/**
 * Default method for all.
 * Class Lib_website_default
 * @since Patrick Thach
 */

class Lib_website_default extends Lib_default {

  protected $_is_sandbox = true;
  protected $_my_site = FALSE;
  protected $_my_wordtracker_appid = "xxx";
  protected $_my_wordtracker_appkey = "xxxx";
  
  protected $_my_namecheap_credentials = array( 'api_user' => 'xxx','api_key' => 'xxxx','api_ip' => 'xxxx');
  protected $_my_keywords = false;

  /**
   * Set site object.
   * @param $the_site_id
   */
  public function set_site($the_site_id) {
    $this->_my_site = get_blog_details(array("blog_id" => $the_site_id));
    return $this;
  }

  /**
   * Return site info.
   * @return $this
   */
  public function get_site() {
    if ($this->_my_site == FALSE) {
      throw new Website_exception("Invalid website.");
    }

    $this->_my_data["site"] = $this->_my_site;
    return $this;
  }

  /**
   * Add option to a site.
   * @param $option
   * @param $value
   */
  public function update_option($option, $value) {
    update_blog_option($this->_my_site->blog_id, $option, $value);
    $this->_my_site = get_blog_details(array("blog_id" => $this->_my_site->blog_id));
    return $this;
  }

  /**
   * Set site options.
   * @return $this
   */

  public function get_options() {
    switch_to_blog($this->_my_site->blog_id);
    $this->_my_data["options"] = wp_load_alloptions();
    restore_current_blog();
    return $this;
  }

  /**
   * Set categories.
   * @return $this
   */
  public function get_categories() {
    switch_to_blog($this->_my_site->blog_id);
    $this->_my_data["categories"] = get_categories(array('orderby' => 'name', 'order' => 'ASC'));
    restore_current_blog();
    return $this;
  }

  /**
   * Split text into arrays and import then as arrays.
   * @param $the_texts
   */
  public function add_taxonomies($the_text) {
    switch_to_blog($this->_my_site->blog_id);
    $the_categories = explode(",", $the_text);
    if (count($the_categories) == 0) {
      throw new Website_exception("Invalid category name.");
    }

    foreach ($the_categories as &$the_category) {
      $arg = array('description' => trim($the_category));
      wp_insert_term(trim($the_category), "category", $arg);
    }

    return $this;
  }

  /**
   * Create site categories from keywords.
   * @return $this
   * @throws Website_exception
   */
  public function add_site_keywords()
  {

    // get the blog id
    $site_id = $this->_my_data["site_id"];

    if(!isset($site_id))
    {
      Throw new Website_exception("Invalid Site Id");
    }

    switch_to_blog($site_id);

    if (count($this->_my_keywords) == 0) {
      throw new Website_exception("Invalid category name.");
    }

    foreach ($this->_my_keywords as &$the_category) {
      if(strlen($the_category) < 3){ continue; }
      $arg = array('description' => trim($the_category));
      wp_insert_term(trim($the_category), "category", $arg);
    }

    return $this;
  }

  /**
   * Get server IP address.
   * @return mixed
   */
  protected function _get_server_ip()
  {
    $ch = curl_init( 'http://icanhazip.com' );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
    $result = curl_exec( $ch );
    curl_close( $ch );
    return $result;
  }


} // end class default.


/**
 * Create / manage websites.
 */

class Lib_website extends Lib_website_default {

  private $_my_topics = false;
  private $_my_available_domains = false;
  private $_my_domain = false;

  /**
   * Set topics.
   * @param $the_topics
   * @return $this
   */
  public function set_topics($the_topics = false)
  {

    if($the_topics == false || strlen($the_topics) < 4)
    {
      throw new Website_exception('Invalid topics.  Need to define topics and topics must be greater than 4 characters.');
    }
    $this->_my_topics = $the_topics;
    return $this;
  }

  /**
   * Set domain name.  What domain name are we working?
   * @param $the_domain
   * @return $this
   */
  public function set_domain($the_domain = false)
  {

    if($the_domain == false || preg_match('/^[-a-z0-9]+\.[a-z]{2,6}$/', strtolower($the_domain)) == false)
    {
      throw new Website_exception('Invalid domain.  Need to define domain and domain must follow the format domain.tld');
    }

    $this->_my_domain = $the_domain;
    return $this;
  }

  /**
   * Create new blog.
   * @param $domain
   * @param $title
   * @return $this
   * @throws Website_exception
   */
  public function create_site($title = false) {

    if($title == false)
    {
      throw new Website_exception("Unable to create new site network.  Title is required.");
    }

    // create a new log.
    $new_site = wpmu_create_blog($this->_my_domain, "/", $title, 1, array("public" => 1));

    if (is_wp_error($new_site)) {
      throw new Website_exception($new_site->get_error_message());
    }

    $this->_my_data["sites"][$new_site] = $this->_my_domain;
    $this->_my_data["site_id"]=$new_site;
    return $this;
  }

  /**
   * Map domains to newly created blog.
   * @return $this
   * @throws Website_exception
   */
  public function map_domains() {
    global $wpdb;

    if (isset($this->_my_data["sites"]) && (count($this->_my_data["sites"]) == 0)) {
      throw new Website_exception("No sites found.");
    }

    $table_name = $wpdb->prefix . 'domain_mapping';

    foreach ($this->_my_data["sites"] as $sid => $domain) {
      $wpdb->insert($table_name, array("blog_id" => $sid, "domain" => $domain, "active" => 1), array('%d', '%s', '%d'));
    }

    return $this;
  }


  /**
   * Register a domain name.
   * @return $this
   * @throws Website_exception
   * @throws Website_exeption
   */

  public function register_domain() {
    global $_config, $et;

    if($this->_my_domain == false)
    {
      throw new Website_exeption("Invalid domain name.  Domain is empty");
    }

    // generate contacts
    $contacts = array("Registrant","Admin","Tech", "AuxBilling");
    $registration_data = array();

    foreach($contacts as &$contact)
    {
      foreach($_config["domain_contacts"] as $dkey => $dcontact)
      {
        $registration_data[$contact . $dkey] = $dcontact;
      }
    }

    // add years
    $registration_data["Years"] = 1;

    $created = $et->load("namecheap")->set_config($this->_my_namecheap_credentials, $this->_is_sandbox);

    if($created->domainsCreate($this->_my_domain, $registration_data) == false)
    {
      throw new Website_exception("There was an error registering the domain name : " . $this->_my_domain. " \n" . $created->Error);
    }
    return $this;
  }

  /**
   * Update domain entries. Point the domain name to
   */

  public function update_domain_hosts()
  {
    global $et, $_config;

    // use namecheap default settings
    $updated = $et->load("namecheap")->set_config($this->_my_namecheap_credentials, $this->_is_sandbox);

    if($updated->dnsSetDefault($this->_my_domain) == false)
    {
      throw new Website_exception("There was an error updating the domain name DNS for : " . $this->_my_domain." \n" . $updated->Error);
    }

    // update host entries
    $domain_data = $_config["domain_hosts"];
    $domain_data["Address1"] = $this->_get_server_ip();
    $domain_data["Address3"] = "http://www." . $this->_my_domain;

    if($updated->dnsSetHosts($this->_my_domain, $domain_data) == false)
    {
      throw new Website_exception("There was an error updating the host entries for : " . $this->_my_domain." \n" . $updated->Error);
    }
    return $this;
  }

  /**
   * Generate keywords from topics.
   */
  public function generate_keywords()
  {

    if($this->_my_topics == false)
    {
      throw new Website_exception("Invalid topics.");
    }

    global $et;

    // cached results for 1 hour
    $resource = "http://api3.wordtracker.com/search?keyword=%s&app_id=%s&app_key=%s&limit=500&terms=2&metric=volume&sort=volume";
    $the_url = sprintf($resource, urlencode($this->_my_topics), $this->_my_wordtracker_appid, $this->_my_wordtracker_appkey);

    // go to wordtracker to
    $et->load("curl")->set_cache(__dir__ . "/cached", 3600)->get($the_url, array($this, "_callback_keywords"));
    return $this;
  }

  /**
   * Clean list of keywords.
   * @param $result
   */

  public function _callback_keywords($result)
  {
    // include blacklist
    global $_config;

    if(!isset($result->raw))
    {
      throw new Website_exception("There was an issue at wordtracker.com.  Check tokens.");
    }

    $the_keywords = json_decode($result->raw);

    if(! isset($the_keywords->results))
    {
      throw new Website_exception("There was an error generating keywords.");
    }

    $the_keywords = $the_keywords->results;

    $the_total_count = count($the_keywords);

    $blacklists = explode(",",$_config["blacklists"]);
    $trademarks = explode(",",$_config["trademarks"]);

    // merge blacklists and trademarks
    $blacklists = array_merge($trademarks, $blacklists);


    /**
     * Loop through each keywords and remove any bad keywords.
     */

    for($i=0;  $i < $the_total_count; $i++)
    {

      // remove any keyword with less than 1000 searches.
      if($the_keywords[$i]->volume < 100)
      {
        unset($the_keywords[$i]);
        continue;
      }

      // remove any keywords with .
      if (preg_match("/([\.]+)/i", $the_keywords[$i]->keyword)) {
        unset($the_keywords[$i]);
        continue;
      }


      // remove any keywords with numbers
      if (preg_match("/([0-9]+)/i", $the_keywords[$i]->keyword)) {
        unset($the_keywords[$i]);
        continue;
      }

      // remove any keywords having more than 4 words.
      if(str_word_count($the_keywords[$i]->keyword) > 3)
      {
        unset($the_keywords[$i]);
        continue;
      }

      // remove any articles and contains blacklisted keywords.
      $the_keys = explode(" ", $the_keywords[$i]->keyword);
      for($a=0; $a < count($the_keys); $a++)
      {
        if(strlen(trim($the_keys[$a])) <= 2)
        {
          unset($the_keys[$a]);
        }

        // remove keywords that has blacklisted keywords.
        if(isset($the_keys[$a]) && in_array(strtolower($the_keys[$a]), $blacklists))
        {
          unset($the_keys[$a]);
        }

      }

      $the_keywords[$i]->keyword = trim(implode(" ", $the_keys));

      // remove any keyword  > 32 characters
      if(strlen($the_keywords[$i]->keyword) > 32)
      {
        unset($the_keywords[$i]);
        continue;
      }

      unset($the_keywords[$i]->searches);
      unset($the_keywords[$i]->competition);
      unset($the_keywords[$i]->cost_per_click);
      unset($the_keywords[$i]->in_anchor_and_title);
      unset($the_keywords[$i]->kei);
      unset($the_keywords[$i]->targeted);

      $this->_my_keywords[] = $the_keywords[$i]->keyword;

    }

    // make domain name unique
    if(is_array($this->_my_keywords))
    {
      $this->_my_keywords = array_unique($this->_my_keywords);
      $this->_my_data["keywords"] = $this->_my_keywords;
    }

  }

  /**
   * Helper :: Generate domains based on topics.
   * Register only .com and .org domains.
   * @param if $auto is true, then we will select the first available domain.
   * @throws Website_exception
   */

  public function generate_domains($auto = false) {


    // generate keywords
    $this->generate_keywords();

    if($this->_my_keywords == false || count($this->_my_keywords) == 0)
    {
      throw new Website_exception("No keywords found.");
    }

    // check available domains
    $this->_check_domains($auto);

    // throw error
    if(count($this->_my_available_domains) == 0)
    {
      throw new Website_exception("No decent domain name found for this topic.");
    }

    // if auto is enabled. We will select the first available domain.
    if($auto == true)
    {
      $this->_my_domain = $this->_my_available_domains[0];
    }

    $this->_my_data["domains"] = $this->_my_available_domains;
    return $this;
  }




  /**
   * Return a list of available domains.
   */

  public function get_available_domains()
  {
    if($this->_my_keywords == false || count($this->_my_keywords) == 0)
    {
      throw new Website_exception("No domains found.");
    }
    $this->my_data["available_domains"] = $this->_my_available_domains;
    return $this;
  }

  /**
   * Return a list of keywords based on topics.
   */
  public function get_keywords()
  {
    if($this->_my_keywords == false || count($this->_my_keywords) == 0)
    {
      throw new Website_exception("No keywords found.");
    }
    $this->my_data["keywords"] = $this->_my_keywords;
    return $this;
  }


  /**
   * Helper :: Check domains availability.
   */
  private function _check_domains($auto, $the_article = false)
  {
    global $et;

    $tlds = array(".com",".org");

    foreach($tlds as $tld)
    {
      foreach($this->_my_keywords as &$the_keyword)
      {
        //turn keyword to domain name.
        $the_domain = str_replace(' ', '',$the_keyword) . $tld;

        if(is_string($the_article) && $the_article != false)
        {
          $the_domain .= $the_article.$the_keyword;
        }

        $is_available = $et->load("whois")->look($the_domain)->is_available();

        if($is_available == true)
        {
          $this->_my_available_domains[] = $the_domain;

          if($auto == true)
          {
            break 2;
          }

          if(count($this->_my_available_domains) > 10)
          {
            break 2;
          }
        }
      }
    }

    // use the article.
    if(count($this->_my_available_domains) == 0)
    {
      return $this->_check_domains($auto, "the");
    }

  }



} // end website


class Website_exception extends Exception {
} // end exception
