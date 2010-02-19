<?php
  /* Please see wp-eolin.php for more information. */

  load_plugin_textdomain('eolin');

  define(EOLIN_DEFAULT, TRUE);
  define(EOLIN_META_NAME, '_eolin_syndicated');
  define(EOLIN_COLUMN_NAME, 'control_eolin');

  define(EOLIN_SYNC_HOST, 'ping.eolin.com');
  define(EOLIN_SYNC_PATH, '/');
  define(EOLIN_SYNC_PORT, 80);
  define(EOLIN_SYNC_TIMEOUT, 30);

  define(EOLIN_IMAGES_URI, get_option('siteurl').'/wp-content/plugins/wp-eolin/images');
  define(EOLIN_AJAX_URI, get_option('siteurl').'/wp-content/plugins/wp-eolin/wp-eolin-ajax.php');
  define(EOLIN_SYNC_URI, get_option('siteurl').'/wp-content/plugins/wp-eolin/wp-eolin-sync.php');

  define(EOLIN_NO_ERROR,          0);
  define(EOLIN_ERROR_PARAMETER,   1);
  define(EOLIN_ERROR_PUBLISH,     2);
  define(EOLIN_ERROR_PERMISSION,  4);
  define(EOLIN_ERROR_CONNECT,     8);
  define(EOLIN_ERROR_RESPONSE,   16);

  function eolin_error_string($error)
  {
    $string = '';

    switch ($error)
    {
      case EOLIN_ERROR_PARAMETER:
        $string = __('The required parameters are invalid or missing.', 'eolin');
        break;
      case EOLIN_ERROR_PUBLISH:
        $string = __('The entry is not published.', 'eolin');
        break;
      case EOLIN_ERROR_PERMISSION:
        $string = __('You do not have appropriate permissions to perform this operation.', 'eolin');
        break;
      case EOLIN_ERROR_CONNECT:
        $string = __('The WordPress can not connect to the Eolin server.', 'eolin');
        break;
      case EOLIN_ERROR_RESPONSE:
        $string = __('The Eolin server does not response.', 'eolin');
        break;
    }

    return $string;
  }

  function eolin_xmlrpc_encode($value)
  {
    if (is_array($value))
    {
      for ($i = 0; $i < count($value); $i++)
      {
        if (!isset($value[$i])) break;
      }

      if ($i < count($value))
      {
        $encoded = '<struct>';
        foreach ($value as $name => $data)
        {
          $encoded .= '<member>';
          $encoded .= '<name>'.$name.'</name>';
          $encoded .= '<value>'.eolin_xmlrpc_encode($data).'</value>';
          $encoded .= '</member>';
        }
        $encoded .= '</struct>';
      }
      else
      {
        $encoded = '<array><data>';
        for ($i = 0; $i < count($value); $i++)
        {
          $encoded .= '<value>'.eolin_xmlrpc_encode($value[$i]).'</value>';
        }
        $encoded .= '</data></array>';
      }
    }
    else
    {
      $encoded = '<string>'.htmlspecialchars($value).'</string>';
    }

    return $encoded;
  }

  function eolin_xmlrpc($id, $syndicate)
  {
    $method = ($syndicate) ? 'sync.create' : 'sync.delete';
    $params = array ('blogURL' => get_bloginfo('url'),
                     'syncURL' => EOLIN_SYNC_URI.'?id='.$id);

    if ($syndicate)
    {
      global $post;

      $post = get_post($id);
      setup_postdata($post);

      $params['blogTitle'] = htmlspecialchars(get_bloginfo('name'));
      $params['language']  = substr(get_locale(), 2);
      $params['language']  = 'ko';
      $params['permalink'] = htmlspecialchars(get_permalink());
      $params['title']     = htmlspecialchars(get_the_title());
      $params['content']   = get_the_content();
      $params['content']   = apply_filters('the_content', $params['content']);
      $params['content']   = str_replace(']]>', ']]&gt;', $params['content']);
      $params['content']   = strip_tags($params['content']);
      $params['content']   = convert_chars($params['content']);
      $params['content']   = ent2ncr($params['content']);
      $params['content']   = str_replace('&nbsp;', ' ', $params['content']);
      $params['content']   = ereg_replace('[[:space:]]+', ' ', $params['content']);
      $params['content']   = htmlspecialchars($params['content']);
      $params['author']    = htmlspecialchars(get_the_author());
      $params['tags']      = array ();
      $params['location']  = '/';
      $params['written']   = mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', TRUE), FALSE);

      $categories = get_the_category();
      foreach ($categories as $category)
      {
        array_push($params['tags'], htmlspecialchars($category->cat_name));
      }

      if (function_exists('utw_show_tags_for_current_post'))
      {
        global $utw;

        $tags = $utw->getTagsForPost($id);
        foreach ($tags as $tag)
        {
          $tag = $utw->formatTag($tag, '%tag_display%');
          array_push($params['tags'], htmlspecialchars($tag));
        }
      }
      elseif (function_exists('UTW_ShowTagsForCurrentPost'))
      {
        global $utw;

        $tags = $utw->GetTagsForPost($id);
        foreach ($tags as $tag)
        {
          $tag = $utw->FormatTag($tag, '%tagdisplay%');
          array_push($params['tags'], htmlspecialchars($tag));
        }
      }
    }

    $content  = '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?>'."\n";
    $content .= '<methodCall>'."\n";
    $content .= '  <methodName>'.$method.'</methodName>'."\n";
    $content .= '  <params><param><value>'.eolin_xmlrpc_encode($params).'</value></param></params>'."\n";
    $content .= '</methodCall>'."\n";

    return $content;
  }

  function eolin_syndicate($id, $syndicate)
  {
    $error = EOLIN_ERROR_PARAMETER;

    if (!empty($id) and is_numeric($id))
    {
      $error = EOLIN_ERROR_PUBLISH;
      $post  = get_post($id);

      $m         = $post->post_date_gmt;
      $post_date = mktime(substr($m, 11, 2), substr($m, 14, 2), substr($m, 17, 2),
                          substr($m,  5, 2), substr($m,  8, 2), substr($m,  0, 4));
      $m         = current_time('mysql', 0);
      $now       = mktime(substr($m, 11, 2), substr($m, 14, 2), substr($m, 17, 2),
                          substr($m,  5, 2), substr($m,  8, 2), substr($m,  0, 4));

      if (($syndicate and ('publish' == $post->post_status) and ($post_date < $now)) or !$syndicate)
      {
        $error = EOLIN_ERROR_PERMISSION;

        if (current_user_can('edit_post', $id))
        {
          for ($trial = 0; $trial < 5; $trial++)
          {
            $error = EOLIN_ERROR_CONNECT;

            $sock = @fsockopen(EOLIN_SYNC_HOST, EOLIN_SYNC_PORT, $errno, $errstr, EOLIN_SYNC_TIMEOUT);
            if (FALSE !== $sock)
            {
              $error   = EOLIN_ERROR_RESPONSE;
              $content = eolin_xmlrpc($id, $syndicate);

              fwrite($sock, 'POST '.EOLIN_SYNC_PATH.' HTTP/1.1'."\r\n");
              fwrite($sock, 'Host: '.EOLIN_SYNC_HOST."\r\n");
              fwrite($sock, 'User-Agent: Mozilla/4.0 (compatible; Eolin)'."\r\n");
              fwrite($sock, 'Content-Type: text/xml'."\r\n");
              fwrite($sock, 'Content-Length: '.strlen($content)."\r\n");
              fwrite($sock, 'Connection: close'."\r\n");
              fwrite($sock, "\r\n");
              fwrite($sock, $content);
              fwrite($sock, "\r\n");

              while ($trial < 5)
              {
                $line = fgets($sock);
                if ((FALSE === $line) or (FALSE === ereg('^HTTP/([0-9.]+)[ \t]+([0-9]+)[ \t]+', $line, $match)))
                {
                  fclose($sock);
                  break;
                }

                $response['status'] = $match[2];

                if (100 != $response['status'])
                {
                  while ($line = fgets($sock))
                  {
                    $line = rtrim($line);
                    if (empty($line)) break;

                    $header = explode(': ', $line, 2);
                    if (2 != count($header)) continue;

                    $header[0] = strtolower($header[0]);
                    switch ($header[0])
                    {
                      case 'content-length':
                      case 'content-type':
                      case 'transfer-encoding':
                        $response[$header[0]] = trim($header[1]);
                        break;
                    }
                  }
 
                  break;
                }

                unset($response);
                $trial++;
              }

              if (empty($response) or (($response['status'] >= 300) and ($response['status'] <= 302)))
              {
                fclose($sock);
                continue;
              }

              $responseText = '';
              if ('chunked' == $response['transfer-encoding'])
              {
                while ($line = fgets($sock))
                {
                  $chunk_size = hexdec(trim($line));
                  if (0 == $chunk_size) break;

                  $read_buffer = '';
                  while(strlen($read_buffer) < ($chunk_size + 2))
                  {
                    $read_buffer .= fread($sock, $chunk_size + 2 - strlen($read_buffer));
                  }

                  $responseText .= substr($read_buffer, 0, strlen($read_buffer) - 2);
                }
              }
              else if (!empty($response['content-length']))
              {
                while (strlen($responseText) < $response['content-length'])
                {
                  $responseText .= fread($sock, $response['content-length'] - strlen($responseText));
                }
              }
              else if (!empty($response['content-type']))
              {
                while (!feof($sock))
                {
                  $responseText .= fread($sock, 10240);
                }
              }

              if (FALSE !== strpos($responseText, 'Success'))
              {
                $error = EOLIN_NO_ERROR;
              }

              fclose($sock);
              break;
            }
          }
        }
      }
    }

    if (EOLIN_NO_ERROR == $error)
    {
      if ($syndicate)
      {
        if ('' != (get_post_meta($id, EOLIN_META_NAME, TRUE)))
        {
          update_post_meta($id, EOLIN_META_NAME, TRUE);
        }
        else
        {
          add_post_meta($id, EOLIN_META_NAME, TRUE, TRUE);
        }
      }
      else
      {
        delete_post_meta($id, EOLIN_META_NAME);
      }
    }

    return $error;
  }
?>
