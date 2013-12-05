<?php

require_once(__dir__ . '/querypath/QueryPath.php');
require_once(__dir__ . '/querypath/qp.php');

/**
 * This library generate a list of keywords related to the topics, then parse Wikipedia websites for articles related
 * to those keywords and imported them to the database.
 * @author Patrick Thach.
 */

class Lib_wikipedia extends Lib_default {

  // what website are we working with?
  private $_my_site = FALSE;
  private $_my_topics = Array();
  private $_my_article = FALSE;

  /**
   * Set working domain name..
   * @param $the_domain
   * @return $this
   */
  public function set_domain($the_domain = FALSE) {


    if ($the_domain == FALSE || preg_match('/^[-a-z0-9]+\.[a-z]{2,6}$/', strtolower($the_domain)) == FALSE) {

      throw new Wikipedia_exception('Invalid domain.  Need to define domain and domain must follow the format domain.tld');
    }

    $the_blog_id = $this->_get_blog_id($the_domain);

    if($the_blog_id == false)
    {
      throw new Wikipedia_exception('Domain does not exist in our blogosphere.');
    }

    $this->_my_site = get_blog_details($the_blog_id);
    switch_to_blog($this->_my_site->blog_id);

    // set categories
    $this->_my_data["categories"] = get_categories(array('orderby' => 'name', 'hide_empty' => 0));

    return $this;
  }

  /**
   * Import all articles related to the topic.
   * @return $this
   */
  public function import($the_topics = false) {

    // total count
    $total_count = count($this->_my_data["categories"]);

    if($total_count < 1)
    {
      throw new Wikipedia_exception("No categories found.");
    }

    // import a single article
    if($the_topics != false  && is_string($the_topics))
    {
      $this->_import_article($this->_my_data["categories"][0]->cat_ID, $the_topics);
      //$this->_import_articles($this->_my_data["categories"][0]->cat_ID, $this->_my_data["categories"][0]->name, $the_topics);
      return true;
    }

    for($i=0; $i < $total_count; $i++)
    {
      try {
        $this->_import_articles($this->_my_data["categories"][$i]->cat_ID, $this->_my_data["categories"][$i]->name);
      } catch(Wikipedia_exception $e)
      {
        log_message($this->_my_site->domain . " : Exception : " . $this->_my_data["categories"][$i]->name, $e->getMessage());
        continue;
      }
    }
  }

  /**
   * Look for a list of articles to be imported into the database.
   */
  private function _import_articles($cat_id, $cat_name, $the_topics = false)
  {
    global $et;

    // cached results for 1 hour
    $resource = "http://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=%s&srprop=timestamp&format=json";

    if($the_topics == false)
    {
      $the_url = sprintf($resource, urlencode($cat_name));
    }
    else {
      $the_url = sprintf($resource, urlencode($the_topics));
    }

    // go to wikipedia and parse the contents.
    $et->load("curl")->set_cache(__dir__ . "/cached", 3600)->get($the_url, array($this, "_callback_searched_topics"));

    if(empty($this->_my_topics))
    {
      throw new Wikipedia_exception("No topics found for this category: " . $cat_name);
    }

    // loop through each search topics and import the article
    foreach($this->_my_topics as &$the_topic)
    {
      try {
        $this->_import_article($cat_id, str_replace(" ","_",$the_topic->title));
      } catch(Wikipedia_exception $e)
      {
        log_message($this->_my_site->domain . " : Exception : " . $the_topic->title, $e->getMessage());
        continue;
      }
    }
  }

  /**
   * Scrape and import a single article into the database.
   * @param $cat_id
   * @param bool $the_topic
   * @throws Wikipedia_exception
   */
  private function _import_article($cat_id, $the_topic = false)
  {

    if ($the_topic == FALSE) {
      throw new Wikipedia_exception("Invalid topics.");
    }

    global $et;

    // cached results for 1 hour
    $resource = "http://en.wikipedia.org/w/api.php?format=json&action=query&prop=revisions&titles=%s&rvprop=content&rvparse=";
    $the_url = sprintf($resource, urlencode($the_topic));

    // don't import the article if it already exist.
    $the_topic = str_replace("_", " ", $the_topic);
    $the_page = get_page_by_title($the_topic, OBJECT, "post");

    if(isset($the_page->ID)){
      throw new Wikipedia_exception($the_topic . " is a duplicate topic.");
    }

    // go to wikipedia and parse the contents.
    $et->load("curl")->set_cache(__dir__ . "/cached", 3600)->get($the_url, array($this, "_callback_related_topics"));

    // save fetch article into the database
    $this->_save($cat_id);
  }

  /**
   * Prepare the article to be imported into the database.
   */
  private function _save($cat_id) {

    if ($this->_my_article == FALSE) {
      throw new Wikipedia_exception("Invalid article. Empty contents.");
    }

    // clean the article
    $this->_clean_article();

    // generate the excerpt
    $this->_generate_excerpt();

    // insert into the database.
    $this->_insert_article($cat_id);
  }

  /**
   * Insert article into the database.
   */

  private function _insert_article($cat_id)
  {
    // insert as post
    $post = array(
      'post_title'    => $this->_my_article->title,
      'post_content'  => $this->_my_article->content,
      'post_excerpt'  => $this->_my_article->excerpt,
      'post_status'   => 'publish',
      'post_category' => array($cat_id),
      'post_author'   => 2);

    $post_id = wp_insert_post($post);

    if(!$post_id)
    {
      throw new Wikipedia_exception("Unable to import article : " . $this->_my_article->title);
    }

    // insert image as attachment.
    $attach_id = $this->_attach_image($post_id);

    if(!$post_id)
    {
      throw new Wikipedia_exception("Unable to attach id {$attach_id} to article : " . $this->_my_article->title);
    }
  }

  /**
   *
   * @param $the_content
   */
  public function _callback_searched_topics($the_content) {

    if (!isset($the_content->raw)) {
      throw new Wikipedia_exception("There were no articles found for this topic.");
    }

    $the_content = json_decode($the_content->raw);
    $the_searches = $the_content->query->search;

    if(count($the_searches) == 0)
    {
      throw new Wikipedia_exception("No articles found for this topic");
    }
    $this->_my_topics = $the_searches;
  }

  /**
   * Callback for parsing Wikipedia articles.
   * @param $content
   */
  public function _callback_related_topics($the_content) {

    $the_article = new StdClass();

    if (!isset($the_content->raw)) {
      throw new Wikipedia_exception("There was an issue at en.wikipedia.org.");
    }

    $the_content = json_decode($the_content->raw);
    $the_content = reset($the_content->query->pages);

    // set the title
    $the_article->title = $the_content->title;

    if(empty($the_content->revisions))
    {
      throw new Wikipedia_exception("Invalid page revisions.");
    }

    $the_content = reset($the_content->revisions);
    $the_property = "*";
    $the_article->content = $the_content->$the_property;

    $this->_my_article = $the_article;
  }

  /**
   * Clean article.  Remove anything we do not need.
   */

  private function _clean_article() {

    // don't take any Redirected URL.

    if(preg_match("/Category:Redirects/", $this->_my_article->content) || preg_match("/REDIRECT/", $this->_my_article->content))
    {
      throw new Wikipedia_exception("Do not parse redirect articles." );
    };

    $remove_classes = array(
      ".navbox",
      ".dablink",
      ".metadata",
      ".vertical-navbox",
      ".portal",
      ".mw-editsection",
      "#toc",
      ".infobox",
      ".reference",
      ".Template-Fact",
      "#coordinates"
    );

    $html = htmlqp($this->_my_article->content, ':root body');
    foreach ($remove_classes as &$remove) {
      $html->find($remove)->remove();
    }

    ob_start();
    $html->writeHTML();
    $this->_my_article->content = ob_get_contents();
    ob_end_clean();

    // replace
    $this->_my_article->content = str_replace("/wiki/", "/", $this->_my_article->content);

    $this->_my_article->content = str_replace("/pages/File:", "http://en.wikipedia.org/wiki/File:", $this->_my_article->content);
    $this->_my_article->content = str_replace('<div class="thumb ', '<div class="et-thumb ', $this->_my_article->content);
    $this->_my_article->content = str_replace('style="background-color: transparent; margin-left: 8px; float: right; clear: right;', 'style="display:none', $this->_my_article->content);
  }

  /**
   * Generate excerpt for post.
   */

  private function _generate_excerpt()
  {
    $the_exerpt = htmlqp($this->_my_article->content, 'first:p')->text();
    $this->_my_article->excerpt = $the_exerpt;
  }

  /**
   * Attach image to the post id.
   */

  private function _attach_image($the_post_id) {
    // get the image source
    $image_src = htmlqp($this->_my_article->content, '.thumbinner img')->attr("src");

    // download the image and put in the
    if(isset($image_src))
    {
      $image_src = str_replace("220px-", '640px-', $image_src);

      if (file_exists($image_src)) {
        $this->_save_image("http:" . $image_src, $the_post_id);
      } else {
        $image_src = str_replace("640px-", '220px-', $image_src);
        $this->_save_image("http:" . $image_src, $the_post_id);
      }
    }
  }

  /**
   * Helper :: Download image to the library.
   * @param $image_url
   * @param $post_id
   * @return int|string|WP_Error
   * @throws Wikipedia_exception
   */

  private function _save_image($image_url, $post_id) {

    $response = wp_remote_get($image_url, array('sslverify' => FALSE));

    if (is_wp_error($response)) {
      throw new Wikipedia_exception("Unable to download image: " . $image_url);
    }

    $image_contents = $response['body'];
    $image_type = wp_remote_retrieve_header($response, 'content-type');


    $image_extension = FALSE;
    // Translate MIME type into an extension
    if ($image_type == 'image/jpeg') {
      $image_extension = '.jpg';
    }
    elseif ($image_extension == 'image/png') {
      $image_extension = '.png';
    }

    if ($image_contents == FALSE) {
      throw new Wikipedia_exception("Invalid image extension.");
    }

    // Construct a file name using post slug and extension
    $new_filename = urldecode(basename(get_permalink($post_id))) . $image_extension;

    // Save the image bits using the new filename
    $upload = wp_upload_bits($new_filename, NULL, $image_contents);

    // Stop for any errors while saving the data or else continue adding the image to the media library
    if ($upload['error']) {
      throw new Wikipedia_exception('Error saving data: ' . $upload['error']);
    }

    $filename = $upload['file'];

    $wp_filetype = wp_check_filetype(basename($filename), NULL);
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => get_the_title($post_id),
      'post_content' => '',
      'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $filename, $post_id);
    // you must first include the image.php file
    // for the function wp_generate_attachment_metadata() to work

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Add field to mark image as a video thumbnail
    update_post_meta($attach_id, 'article_thumbnail', '1');

     $new_thumbnail = wp_get_attachment_image_src( $attach_id, 'full' );

      // Add hidden custom field with thumbnail URL
      if ( !update_post_meta( $post_id, "_article_thumbnail", $new_thumbnail ) ) add_post_meta( $post_id, "_article_thumbnail", $new_thumbnail, true );

      // Set attachment as featured image if enabled
      // Make sure there isn't already a post thumbnail

      if ( !ctype_digit( get_post_thumbnail_id( $post_id ) ) ) {
        set_post_thumbnail( $post_id, $attach_id );
      }

  } // End of save to media library function

} // end wikipedia

class Wikipedia_exception extends Exception {
} // end exception
