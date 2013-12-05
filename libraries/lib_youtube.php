<?php

require_once(__dir__.'/google/Google_Client.php');
require_once(__dir__.'/google/contrib/Google_YouTubeService.php');

class Lib_youtube extends Lib_default {

  // my developer key.
  private $_my_developer_key = 'xxxxx';
  private $_my_client = false;
  private $_my_site = false;
  
  public function __construct() {
    $client = new Google_Client();
    $client->setDeveloperKey($this->_my_developer_key);
    $this->_my_client = new Google_YoutubeService($client);
  }

  /**
   * Set working domain name..
   * @param $the_domain
   * @return $this
   */
  public function set_domain($the_domain = false)
  {

    if($the_domain == false || preg_match('/^[-a-z0-9]+\.[a-z]{2,6}$/', strtolower($the_domain)) == false)
    {
      throw new Youtube_exception('Invalid domain.  Need to define domain and domain must follow the format domain.tld');
    }

    $this->_my_site = get_blog_details(array("domain" => $the_domain));
    switch_to_blog($this->_my_site->blog_id);
    $this->_my_data["categories"] = get_categories(array('orderby' => 'name', 'hide_empty' => 0));
    return $this;
  }

  /**
   * Loop through each categories, then import each videos at youtube into the database.
   */

  public function import()
  {
    $total_count = count($this->_my_data["categories"]);

    for($i=0; $i < $total_count; $i++)
    {
      try {
        $this->_import_videos($this->_my_data["categories"][$i]->cat_ID, $this->_my_data["categories"][$i]->name);
      } catch(Wikipedia_exception $e)
      {
        log_message($this->_my_site->domain . " : Exception : ", $e->getMessage());
        continue;
      }
    }
  }

  /**
   * Return a list of items by keywords.
   * @param $the_keyword
   * @throws Youtube_exception
   */
  private function _import_videos($cat_id, $the_keyword)
  {

    try
    {
      $query = array('q' => $the_keyword,'maxResults' => 50, 'order' => 'viewCount');
      $the_results = $this->_my_client->search->listSearch('id,snippet', $query);

      for($i = 0; $i < $the_results["pageInfo"]["resultsPerPage"]; $i++)
      {
        try {
          $this->_import_video($cat_id, $the_results["items"][$i]);
        } catch(Wikipedia_exception $e)
        {
          log_message($this->_my_site->domain . " : Exception : ", $e->getMessage());
          continue;
        }
      }

    }catch (Exception $e)
    {
      throw new Youtube_exception($e->getMessage());
    }
  }

  /**
   * Import video into the database.
   * @param array $the_video
   */
  private function _import_video($cat_id, array $the_video)
  {

    // check for duplicate
    $the_page = get_page_by_title($the_video["snippet"]["title"], OBJECT, "post");

    if(isset($the_page->ID)){
      throw new Wikipedia_exception($the_video["snippet"]["title"] . " is a duplicate topic.");
    }

    // insert as post
    $post = array(
      'post_title'    => $the_video["snippet"]["title"],
      'post_content'  => $the_video["snippet"]["description"],
      'post_status'   => 'publish',
      'post_author'   => 2,
      'post_category' => array($cat_id)
    );
    
    $post_id = wp_insert_post($post);

    // insert to postmeta
    add_post_meta($post_id,"_tern_wp_youtube_video", $the_video["id"]["videoId"]);
    add_post_meta($post_id,"_tern_wp_youtube_published", $the_video["snippet"]["publishedAt"]);
    add_post_meta($post_id,"_tern_wp_youtube_author", $the_video["snippet"]["channelTitle"]);
  }

}

class Youtube_exception extends Exception {
} // end exception
