<?php
  require_once('../../../wp-config.php');

  header('Content-type: text/xml');
  echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?>'."\n";
  echo '<response>'."\n";
  echo '  <version>1.1</version>'."\n";

  $error = TRUE;
  if (isset($_GET['id']))
  {
    $id   = $_GET['id'];
    $post = get_post($id);

    setup_postdata($post);

    $m         = $post->post_date_gmt;
    $post_date = mktime(substr($m, 11, 2), substr($m, 14, 2), substr($m, 17, 2),
                        substr($m,  5, 2), substr($m,  8, 2), substr($m,  0, 4));
    $m         = current_time('mysql', 0);
    $now       = mktime(substr($m, 11, 2), substr($m, 14, 2), substr($m, 17, 2),
                        substr($m,  5, 2), substr($m,  8, 2), substr($m,  0, 4));

    if (('publish' == $post->post_status) and ($post_date < $now) and
        ('' != get_post_meta($id, EOLIN_META_NAME, TRUE)))
    {
      $error = FALSE;
    }
  }

  if ($error) :
?>
  <status>0</status>
<?
  else :
    $language        = substr(get_locale(), 2);
    $language        = 'ko';
    $blog_comments   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type != 'trackback'");
    $blog_trackbacks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'trackback'");
    $more            = TRUE;
    $content         = get_the_content();
    $content         = apply_filters('the_content', $content);
    $content         = str_replace(']]>', ']]&gt;', $content);
    $categories      = get_the_category();
    $tag_displays    = '';

    foreach ($categories as $category)
    {
      $tag_displays .= "\n".'    <tag>'.htmlspecialchars($category->cat_name).'</tag>';
    }

    if (function_exists('utw_show_tags_for_current_post'))
    {
      $tags = $utw->getTagsForPost($id);
      foreach ($tags as $tag)
      {
        $tag           = $utw->formatTag($tag, '%tag_display%');
        $tag_displays .= "\n".'    <tag>'.htmlspecialchars($tag).'</tag>';
      }
    }
    elseif (function_exists('UTW_ShowTagsForCurrentPost'))
    {
      $tags = $utw->GetTagsForPost($id);
      foreach ($tags as $tag)
      {
        $tag           = $utw->FormatTag($tag, '%tagdisplay%');
        $tag_displays .= "\n".'    <tag>'.htmlspecialchars($tag).'</tag>';
      }
    }

    $category    = (empty($categories[0])) ? __('Uncategorized') : convert_chars($categories[0]->cat_name);
    $comments    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = {$id} AND comment_type != 'trackback'");
    $trackbacks  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = {$id} AND comment_type = 'trackback'");
    $attachments = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_parent = {$id} AND post_status = 'attachment'");
?>
  <status>1</status>
  <blog>
    <generator>WordPress/<?php bloginfo('version'); ?></generator>
    <language><?php echo $language; ?></language>
    <url><?php bloginfo('url'); ?></url>
    <title><?php echo htmlspecialchars(get_bloginfo('name')); ?></title>
    <description><?php echo htmlspecialchars(get_bloginfo('description')); ?></description>
    <comments><?php echo $blog_comments; ?></comments>
    <trackbacks><?php echo $blog_trackbacks; ?></trackbacks>
  </blog>
  <entry>
    <permalink><?php echo htmlspecialchars(get_permalink()); ?></permalink>
    <title><?php echo htmlspecialchars(get_the_title()); ?></title>
    <content><?php echo htmlspecialchars($content); ?></content>
    <author><?php echo htmlspecialchars(get_the_author()); ?></author>
    <category><?php echo htmlspecialchars($category); ?></category>
    <?php echo $tag_displays; ?>
    <location>/</location>
    <comments><?php echo $comments; ?></comments>
    <trackbacks><?php echo $trackbacks; ?></trackbacks>
    <written><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', TRUE), FALSE); ?></written>
    <?php foreach ($attachments as $attachment) : ?>
    <?php $file = get_post_meta($attachment->ID, '_wp_attached_file', true); ?>
    <attachment>
      <mimeType><?php echo $attachment->post_mime_type; ?></mimeType>
      <filename><?php echo basename($file); ?></filename>
      <length><?php echo filesize($file); ?></length>
      <url><?php echo $attachment->guid; ?></url>
    </attachment>
    <?php endforeach; ?>
  </entry>
<?
  endif;

  echo '</response>'."\n";
?>
