<?php
  /* Please see wp-eolin.php for more information. */

  require_once('../../../wp-config.php');
  require_once('wp-eolin-core.php');

  $error   = EOLIN_ERROR_PARAMETER;
  $message = __('Operation', 'eolin');

  if (isset($_GET['mode']))
  {
    $syndicate = (1 == $_GET['mode']) ? TRUE : FALSE;

    if ($syndicate)
    {
      $message = __('Syndication', 'eolin');
    }
    else
    {
      $message = __('Unsyndication', 'eolin');
    }

    if (isset($_GET['id']))
    {
      $error = eolin_syndicate($_GET['id'], $syndicate);
    }
  }

  if (EOLIN_NO_ERROR != $error)
  {
    $message = $message.' '.__('Failure', 'eolin').': '.eolin_error_string($error);
  }
  else
  {
    $message = $message.' '.__('Success', 'eolin');
  }

  header('Content-type: text/xml; charset='.get_option('blog_charset'));
  echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?>'."\n";
?>
<response>
  <error><?php echo $error; ?></error>
  <message><?php echo $message; ?></message>
</response>
