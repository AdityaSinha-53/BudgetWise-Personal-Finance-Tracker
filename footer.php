    <!-- Page footer -->
    <footer class="page-footer">
        BudgetWise &mdash; Personal Finance Tracker
        &nbsp;|&nbsp;
        <?php
        $display_name = $prof['name'] ?? ($_SESSION['username'] ?? 'Guest');
        $role_label   = is_admin() ? ' (Administrator)' : '';
        ?>
        Signed in as <strong><?= htmlspecialchars($display_name . $role_label, ENT_QUOTES, 'UTF-8') ?></strong>
    </footer>

</main>  <!-- closes <main class="main"> opened in header.php -->
</div>   <!-- closes <div class="app">   opened in header.php -->

<!-- Scripts at end of body so the full DOM is available. -->
<script>
// Theme toggle — saves choice as a cookie that lasts 1 year
function toggleTheme() {
    var html = document.documentElement;
    var cur  = html.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.cookie = 'theme=' + next + ';path=/;max-age=31536000;samesite=lax';

    var icon = document.getElementById('theme-icon');
    var lbl  = document.getElementById('theme-lbl');
    if (icon) icon.textContent = next === 'dark' ? '\u2600' : '\u263e';
    if (lbl)  lbl.textContent  = next === 'dark' ? 'Light'  : 'Dark';
}

// Auto-dismiss success/info banners after 4 seconds.
// Error banners stay visible so the user can read them.
setTimeout(function () {
    document.querySelectorAll('.alert-success, .alert-info').forEach(function (el) {
        el.style.transition = 'opacity .4s ease, transform .4s ease';
        el.style.opacity    = '0';
        el.style.transform  = 'translateY(-8px)';
        setTimeout(function () { el.remove(); }, 450);
    });
}, 4000);
</script>

<?php mysqli_close($conn); ?>
</body>
</html>
