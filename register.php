<?php
require_once 'includes/auth.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['register_error'] ?? null;
$old   = $_SESSION['register_old']   ?? [];
unset($_SESSION['register_error'], $_SESSION['register_old']);

$page_title = 'ثبت‌نام';
require 'includes/header.php';
?>

<main>
  <div class="auth-wrap">
    <div class="auth-panel">
      <h2>شروع کن</h2>
      <p>یه حساب بساز و جزوه‌هاتو آپلود کن تا دستیار هوشمند آماده‌ی کمک به تو بشه.</p>
    </div>
    <div class="auth-form-side">
      <form class="auth-card" method="post" action="api/register_handler.php" novalidate>
        <h1>ساخت حساب</h1>
        <p class="auth-sub">از قبل حساب داری؟ <a href="login.php">وارد شو</a></p>

        <?php if ($error): ?>
          <div class="form-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="field">
          <label for="fullname">نام و نام خانوادگی</label>
          <input id="fullname" name="fullname" type="text" required autocomplete="name"
                 value="<?php echo htmlspecialchars($old['fullname'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="email">ایمیل</label>
          <input id="email" name="email" type="email" required autocomplete="email"
                 value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="password">رمز عبور</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-accent btn-block">ساخت حساب</button>

        <p class="auth-switch">قبلاً ثبت‌نام کردی؟ <a href="login.php">ورود</a></p>
      </form>
    </div>
  </div>
</main>

<?php require 'includes/footer.php'; ?>
