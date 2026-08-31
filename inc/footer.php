
</main><!-- /.iq-main -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables (tema Bootstrap 5; jQuery já carregado no header) -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- Mobile — nível 1: navbar hambúrguer + wrap automático de tabelas -->
<script>
(function () {
  var ham = document.getElementById('iqHamburger');
  var nav = document.querySelector('.iq-tn-links');

  // ── Hambúrguer toggle ──
  if (ham && nav) {
    ham.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      ham.setAttribute('aria-expanded', String(open));
      ham.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.iq-topnav') && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        ham.setAttribute('aria-expanded', 'false');
        ham.querySelector('i').className = 'fas fa-bars';
      }
    });
  }

  // ── Submenus: toggle por clique em mobile ──
  document.querySelectorAll('.iq-tn-has-menu > a').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (window.innerWidth > 768) return;
      e.preventDefault();
      var menu = this.parentElement.querySelector('.iq-tn-menu');
      if (menu) menu.classList.toggle('is-open');
    });
  });

  // ── Tabelas: wrap automático em overflow-x: auto ──
  document.querySelectorAll('.iq-main table.table').forEach(function (tbl) {
    if (tbl.closest('.table-responsive')) return;
    var wrap = document.createElement('div');
    wrap.className = 'table-responsive';
    tbl.parentNode.insertBefore(wrap, tbl);
    wrap.appendChild(tbl);
  });
}());
</script>

</body>
</html>
