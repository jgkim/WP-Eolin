<?php
  /*
   * Plugin Name: WP-Eolin
   * Plugin URI: http://jayg.org/projects/wp-eolin/
   * Description: With this plugin you can syndicate posts to Eolin.
   * Version: 0.13.2
   * Author: James G. Kim
   * Author URI: http://jayg.org/
   */

  require_once('wp-eolin-core.php');

  function eolin_update($id)
  {
    $syndicate = ('true' == $_POST[EOLIN_META_NAME]);

    if (!isset($_REQUEST['publish']) and (('private' == $_REQUEST['post_status']) or ('draft' == $_REQUEST['post_status'])))
    {
      $syndicate = FALSE;
    }

    if ($syndicate and ('' != get_post_meta($id, EOLIN_META_NAME, TRUE)))
    {
      eolin_syndicate($id, FALSE);
    }

    eolin_syndicate($id, $syndicate);
  }

  function eolin_delete($id)
  {
    if ('' != get_post_meta($id, EOLIN_META_NAME, TRUE))
    {
      eolin_syndicate($id, FALSE);
    }
  }

  function eolin_posts_columns($column_name)
  {
    $column_name[EOLIN_COLUMN_NAME] = '';

    return $column_name;
  }

  function eolin_posts_custom_column($column_name, $id)
  {
    if (EOLIN_COLUMN_NAME == $column_name)
    {
      if ('' != get_post_meta($id, EOLIN_META_NAME, TRUE))
      {
        echo '<div style="text-align:center;"><image src="'.EOLIN_IMAGES_URI.'/syndicated.gif'.'" alt="'.__('Syndicated', 'eolin').'" class="eolin" onclick="syndicate_with_eolin('.$id.', false, this);" /></div>';
      }
      else
      {
        echo '<div style="text-align:center;"><image src="'.EOLIN_IMAGES_URI.'/unsyndicated.gif'.'" alt="'.__('Unsyndicated', 'eolin').'" class="eolin" onclick="syndicate_with_eolin('.$id.', true, this);" /></div>';
      }
    }
  }

  function eolin_inner_custom_box()
  {
    global $id, $post;

    if (!isset($id))   $id   = $_REQUEST['post'];
    if (!isset($post)) $post = get_post($id);

    $checked = '';
    if ((empty($id) and EOLIN_DEFAULT) or
        (!empty($id) and EOLIN_DEFAULT and ('draft' == $post->post_status)) or
        (!empty($id) and ('' != get_post_meta($id, EOLIN_META_NAME, TRUE))))
    {
      $checked = 'checked="checked" ';
    }
    ?>
      <label class="selectit">
        <input type="checkbox" name="<?php echo EOLIN_META_NAME; ?>" value="true" <?php echo $checked; ?>/>
        <?php _e('Syndicate with Eolin', 'eolin'); ?>
      </label>
    <?php
  }

  function eolin_admin_head()
  {
    global $id, $post;

    if (!isset($id))   $id   = $_REQUEST['post'];
    if (!isset($post)) $post = get_post($id);

    if (preg_match('/(edit\.php)/i', $_SERVER['SCRIPT_NAME']))
    {
?>
      <script type="text/javascript">
      //<![CDATA[
        function syndicate_with_eolin(id, syndicate, image)
        {
          var http        = null;
          var ms_xml_http = new Array ('Msxml2.XMLHTTP.7.0',
                                       'Msxml2.XMLHTTP.6.0',
                                       'Msxml2.XMLHTTP.5.0',
                                       'Msxml2.XMLHTTP.4.0',
                                       'Msxml2.XMLHTTP.3.0',
                                       'Msxml2.XMLHTTP',
                                       'Microsoft.XMLHTTP');

          for (var i = 0; i < ms_xml_http.length; i++)
          {
            try
            {
              http = new ActiveXObject(ms_xml_http[i]);
              break;
            }
            catch (e)
            {
              http = null;
            }
          }

          if ((null == http) && window.XMLHttpRequest)
          {
            try
            {
              http = new XMLHttpRequest();
            }
            catch (e)
            {
              http = null;
            }
          }

          if (null != http)
          {
            var mode = 0;

            if (syndicate) mode = 1;

            http.open('GET', '<?php echo EOLIN_AJAX_URI; ?>?id='+id+'&mode='+mode, true);
            http.onreadystatechange =
              function()
              {
                if ((4 == http.readyState) && (200 == http.status))
                {
                  var response = http.responseXML;
                  var error    = <?php echo EOLIN_ERROR_PARAMETER; ?>;
                  var message  = '<?php _e('Operation Failure', 'eolin'); ?>';

                  if (null != response)
                  {
                    var errors   = response.getElementsByTagName('error');
                    var messages = response.getElementsByTagName('message');

                    if (0 != errors.length)   error   = errors[0].firstChild.nodeValue;
                    if (0 != messages.length) message = messages[0].firstChild.nodeValue;
                  }

                  if (<?php echo EOLIN_NO_ERROR; ?> != error)
                  {
                    alert(message);
                  }
                  else
                  {
                    if (syndicate)
                    {
                      image.setAttribute('alt', '<?php _e('Syndicated', 'eolin'); ?>');
                      image.setAttribute('src', '<?php echo EOLIN_IMAGES_URI; ?>/syndicated.gif');
                      image.setAttribute('title', '<?php _e('This post is syndicated. Click to unsyndicate this.', 'eolin'); ?>');
                      image.onclick = function() { syndicate_with_eolin(id, false, image); };
                    }
                    else
                    {
                      image.setAttribute('alt', '<?php _e('Unsyndicated', 'eolin'); ?>');
                      image.setAttribute('src', '<?php echo EOLIN_IMAGES_URI; ?>/unsyndicated.gif');
                      image.setAttribute('title', '<?php _e('This post is unsyndicated. Click to syndicate this.', 'eolin'); ?>');
                      image.onclick = function() { syndicate_with_eolin(id, true, image); };
                    }
                  }
                }
              };
            http.send(null);
          }
        }
      //]]>
      </script>
<?php
    }

    if (preg_match('/(post\.php|post-new\.php)/i', $_SERVER['SCRIPT_NAME']) and ('static' != $post->post_status))
    {
      add_meta_box('eolin-box', 'Eolin', 'eolin_inner_custom_box', 'post', 'side' );
      
    }
  }

  function eolin_admin_footer()
  {
    global $id, $post;

    if (!isset($id))   $id   = $_REQUEST['post'];
    if (!isset($post)) $post = get_post($id);

    if (preg_match('/(edit\.php)/i', $_SERVER['SCRIPT_NAME']))
    {
?>
      <script type="text/javascript">
      //<![CDATA[
        var images = document.getElementById('posts-filter').getElementsByTagName('*');
        var regex  = new RegExp('\\beolin\\b');

        for (i = 0; i < images.length; i++)
        {
          if (regex.test(images[i].getAttribute('class')))
          {
            images[i].style.cursor = 'pointer';
            if ('<?php echo EOLIN_IMAGES_URI; ?>/syndicated.gif' == images[i].getAttribute('src'))
            {
              images[i].setAttribute('title', '<?php _e('This post is syndicated. Click to unsyndicate this.', 'eolin'); ?>');
            }
            else
            {
              images[i].setAttribute('title', '<?php _e('This post is unsyndicated. Click to syndicate this.', 'eolin'); ?>');
            }
          }
        }
      //]]>
      </script>
<?php
    }
  }

  add_filter('manage_posts_columns',       'eolin_posts_columns'             );
  add_action('save_post',                  'eolin_update'                    );
  add_action('delete_post',                'eolin_delete'                    );
  add_action('manage_posts_custom_column', 'eolin_posts_custom_column', 10, 2);
  add_action('admin_head',                 'eolin_admin_head'                );
  add_action('admin_footer',               'eolin_admin_footer'              );
?>
