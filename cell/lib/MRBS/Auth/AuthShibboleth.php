<?php
namespace MRBS\Auth;
use MRBS\User;

/** 
 * Shibboleth Authentication handler for MRBS.
 * 
 * 
 * @author Ondřej Votava <ondrej.votava@cvut.cz>
 * @author Martin Samek <martin.samek@cvut.cz>
 * @version 1.3
 * @copyright (c) 2022, Ondřej Votava, Martin Samek
 */


class AuthShibboleth extends Auth 
{
  public function __construct()
  {
    $this->checkSessionMatchesType();
  }

    /* validateUser($user, $pass)
   *
   * Checks if the specified username/password pair are valid
   *
   * $user  - The user name
   * $pass  - The password
   *
   * Returns:
   *   false    - The pair are invalid or do not exist
   *   string   - The validated username
   */
  public function validateUser(?string $user, ?string $pass)
  {
    $current_username = \MRBS\session()->getUsername();

    if (isset($current_username) && $current_username === $user)
    {
      return $user;
    }

    return false;
  }

  public function getUser(string $username) : ?User
  {
    $user = new User($username);
    $user->level = $this->getLevel($username);
    $user->email = $this->getEmail($username);

    return $user;
  }

  private function shibMatches($roles)
  {
    foreach ($roles as $attr => $vals)
    {
      if (isset($_SERVER[$attr]))
      {
        $server_vals = explode(";", $_SERVER[$attr]);
        foreach ($vals as $v)
        {
          if (is_array($v))
          {
            if (isset($v['type']))
            {
              if ($v['type'] == 'regex' && isset($v['value']))
              {
                foreach ($server_vals as $sv)
                {
                  if (preg_match($v['value'], $sv))
                  {
                    return true;
                  }
                }
              }
            }
          }
          else
          {
            if (in_array($v, $server_vals))
            {
              return true;
            }
          }
        }
      }
    }
    return false;
  }

  /**
   * Determines the user access level.
   *
   * The affilitation roles have to be set in config.inc.php file as follows:
   * 
   * $auth['shibboleth']['user_roles'] = array(
   *   'unscoped-affiliation' => array('student'),
   *   'RolesTypeB2' => array('13000:30','0:362')
   * );
   * 
   * $auth['shibboleth']['admin_roles'] = array(
   *   'unscoped-affiliation' => array('faculty','staff','employee'),
   *   'RolesTypeB2' => array('13000:75')
   * );
   * 
   * @param string $user username to check, not used in shibboleth
   * @return int 0 ... unknown, 1 ... user, 2 ... administrator
   */
  private function getLevel(string $username)
  {
    global $auth;
    $current_username = \MRBS\session()->getUsername();

    // the set of Shibboleth attributes that define a valid user
    // only one item is needed, not all of them at once
    $user_roles = $auth['shibboleth']['user_roles'];

    // the set of Shibboleth attributes that define a valid administrator
    // only one item is needed, not all of them at once
    $admin_roles = $auth['shibboleth']['admin_roles'];

    if (isset($current_username) && $current_username === $username)
    {
      if ($this->shibMatches($admin_roles)) return 2;
      if ($this->shibMatches($user_roles)) return 1;
    }
    return 0;
  }

  // Gets the user's email address.   Returns an empty
  // string if one can't be found
  private function getEmail(string $username)
  {
    global $auth;
    $current_username = \MRBS\session()->getUsername();
    if (isset($current_username) && $current_username === $username)
    {
      $email = isset($_SERVER[$auth['shibboleth']['email_attribute']]) ? $_SERVER[$auth['shibboleth']['email_attribute']] : -1;
      return ($email == -1) ? '' : $email;
    }
    return '';
  }
}
