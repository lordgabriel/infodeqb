<?php
$_logoutUrl = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])
    ? HTTP_DIR . '/infodeqb/local_logout.php'
    : 'https://deq.fe.up.pt/Shibboleth.sso/Logout';

$_initials = strtoupper(mb_substr(
    trim(strstr($_SESSION['DisplayName'] ?? '', ' ', true) ?: ($_SESSION['DisplayName'] ?? '?')), 0, 1
) . mb_substr(trim(strrchr($_SESSION['DisplayName'] ?? '', ' ')), 0, 1));
if (!$_initials) $_initials = '?';
?>
<nav class="iq-navbar">

  <button class="iq-nav-toggle" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
  </button>

  <a class="iq-nav-brand" href="<?php echo HTTP_DIR; ?>/infodeqb/">
    <img src="<?php echo HTTP_DIR; ?>/infodeqb/img/logo_deq_black.png" alt="DEQB">
    <span class="iq-nav-brand-label d-none d-md-block">InfoDEQB</span>
  </a>

  <div class="iq-nav-spacer"></div>

  <div class="iq-nav-user" id="iqNavUser">
    <button class="iq-nav-user-btn" type="button" onclick="iqToggleUser()">
      <span class="iq-avatar"><?php echo htmlspecialchars($_initials); ?></span>
      <span class="iq-nav-user-name d-none d-sm-block"><?php echo htmlspecialchars($_SESSION['DisplayName'] ?? ''); ?></span>
      <i class="fas fa-chevron-down iq-nav-chevron"></i>
    </button>
    <div class="iq-nav-dropdown">
      <div class="iq-nav-dropdown-head">
        <div class="name"><?php echo htmlspecialchars($_SESSION['DisplayName'] ?? ''); ?></div>
        <div class="email"><?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?></div>
      </div>
      <a class="iq-nav-dropdown-item" href="<?php echo htmlspecialchars($_logoutUrl); ?>">
        <i class="fas fa-sign-out-alt"></i> Terminar sessão
      </a>
    </div>
  </div>

</nav>
<script>
function iqToggleUser() {
  document.getElementById('iqNavUser').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var m = document.getElementById('iqNavUser');
  if (m && !m.contains(e.target)) m.classList.remove('open');
});
</script>
