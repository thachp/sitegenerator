<div class="container">

  <div class="page-header">
    <h1>Manage <small>Subtext for header</small></h1>
  </div>

  <?php
  $blog_details = get_blog_details($_GET['w']);

  print_r($blog_details);
  ?>
  <form class="form-horizontal" role="form">
    <div class="form-group">
      <label for="inputEmail1" class="col-lg-2 control-label">Domain</label>
      <div class="col-lg-10">
        <input type="email" class="form-control" id="inputEmail1" placeholder="Domain" value="<?=substr($blog_details->domain,4)?>">
      </div>
    </div>
    <div class="form-group">
      <label for="inputPassword1" class="col-lg-2 control-label">Title</label>
      <div class="col-lg-10">
        <input class="form-control" id="inputPassword1" placeholder="Title" value="<?=$blog_details->blogname?>">
      </div>
    </div>

    <div class="form-group">
      <label for="inputPassword1" class="col-lg-2 control-label">Short Description</label>
      <div class="col-lg-10">
        <input type="password" class="form-control" id="inputPassword1" placeholder="Password">
      </div>
    </div>

    <div class="form-group">
      <label for="inputPassword1" class="col-lg-2 control-label">Long Description</label>
      <div class="col-lg-10">
        <textarea class="form-control" rows="3"></textarea>
      </div>
    </div>

    <div class="form-group">
      <label for="inputPassword1" class="col-lg-2 control-label">Tags</label>
      <div class="col-lg-10">
        <textarea class="form-control" rows="2"></textarea>
      </div>
    </div>


    <div class="form-group">
      <div class="col-lg-offset-2 col-lg-10">
        <button type="submit" class="btn btn-default">Submit</button>
      </div>
    </div>
  </form>

</div>