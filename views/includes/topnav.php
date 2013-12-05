<!-- Static navbar -->
<div class="navbar navbar-default navbar-fixed-top">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="#">ADMIN CPANEL</a>
    </div>
    <div class="navbar-collapse collapse">
      <ul class="nav navbar-nav">
        <li class="active"><a href="#">Home</a></li>
        <li><a href="create.php">Create A Website</a></li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">Manage <b class="caret"></b></a>
          <ul class="dropdown-menu">
            <?php $blogs = get_blog_list(); ?>
            <?php foreach($blogs as &$blog) : ?>
            <li><a href="index.php?w=<?=$blog['blog_id']?>"><?=substr($blog['domain'],4)?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
      </ul>


      <?php if(is_user_logged_in()): ?>
      <ul class="nav navbar-nav navbar-right">
        <li class="active"><a href="./">
              <?php global $current_user; get_currentuserinfo() ?>
              <?=$current_user->user_login?>
          </a></li>
      </ul>
      <?php endif;?>

    </div><!--/.nav-collapse -->
  </div>
</div>

