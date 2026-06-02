<?php
namespace MRBS\Session;
use MRBS\User;

/**
 * Shibboleth Session handler for MRBS.
 * 
 * Add the following items into config.inc.php
 * $auth['shibboleth']['base_uri'] = "https://example.com";
 * $auth['shibboleth']['site_uri'] = "https://example.com/mrbs/";
 * $auth['shibboleth']['name_attribute'] = "nickname";
 * $auth['shibboleth']['username_attribute'] = "uid";
 * $auth['shibboleth']['handler_uri'] = "Shibboleth.sso";
 * 
 * 
 * @author Ondřej Votava <ondrej.votava@cvut.cz>
 * @author Martin Samek <martin.samek@cvut.cz>
 * @version 1.3
 * @copyright (c) 2022, Ondřej Votava, Martin Samek
 */

class SessionShibboleth extends SessionWithLogin
{
  private string $base_uri;
  private string $site_uri;
  private string $handler_uri;

  public function __construct()
  {
    global $auth;

    $this->checkTypeMatchesSession();

    $required = array(
      'base_uri', 'site_uri', 'handler_uri', 
      'name_attribute', 'username_attribute', 'email_attribute',
      'user_roles', 'admin_roles'
    );

    foreach($required as $attr)
    {
      if (!isset($auth['shibboleth'][$attr]))
      {
        throw new \Exception('$auth["shibboleth"]["'.$attr.'"] must be set in the config file.');
      }
      if (in_array($attr, array('base_uri', 'site_uri', 'handler_uri')))
      {
        $val = $auth['shibboleth'][$attr];
        if (in_array($attr, array('base_uri', 'handler_uri'))) 
        {
          if (strrpos($val, '/') == strlen($val) - 1) $val = substr($val, 0, strlen($val) - 1);
        }
        $this->$attr = $val;
      }
    }

    parent::__construct();
  }

  /*
   * Prompt the user for username/password. 
   * This is not necessary since this step is done by IDP.
   */
  public function authGet(?string $target_url=null, ?string $returl=null, ?string $error=null, bool $raw=false) : void
  {
    header("Location: $this->base_uri/$this->handler_uri/Login?target=$this->site_uri");
  }

  /**
   * Get the username of the currently logged in user.
   * 
   * @return username or NULL if no user is logged in
   * 
   */
  public function getCurrentUser() : ?User
  {
    global $auth;

    $current_user = $this->getUsername();
    if($current_user)
    {
      return \MRBS\auth()->getUser($current_user);
    }
    else
    {
      return NULL;
    }
  }

  public function getLogonFormParams() : ?array
  {
    $result = array(
      'action' => "$this->base_uri/$this->handler_uri/Login",
      'method' => 'get',
      'hidden_inputs' => array("target" => $this->site_uri)
    );
    return $result;
  }


  public function getLogoffFormParams() : ?array
  {
    $result = array(
      'action' => "$this->base_uri/$this->handler_uri/Logout",
      'method' => 'get',
      'hidden_inputs' => array ("return" => $this->site_uri)
    );

    return $result;
  }


  public function processForm() : void
  {
    // No need to do anything - all handled by SAML
  }

  public function getUsername() : ?string
  {
    global $auth;

    $shib_username_attr = $auth['shibboleth']['username_attribute'];
    if(isset($_SERVER[$shib_username_attr]))
    {
      return ($_SERVER[$shib_username_attr]);
    }
    else
    {
      return NULL;
    }
  }

 }
