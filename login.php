<?php
require_once 'includes/auth.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
$old   = $_SESSION['login_old']   ?? [];
unset($_SESSION['login_error'], $_SESSION['login_old']);

$page_title = 'ورود';
require 'includes/header.php';
?>

<main>
  <div class="auth-wrap">
    <div class="auth-panel">
      <h2>خوش برگشتی</h2>
      <p>وارد حسابت شو تا گفت‌وگوها، جزوه‌ها و برنامه‌ی مطالعه‌ت رو از همون‌جایی که گذاشته بودی ادامه بدی.</p>
    </div>
    <div class="auth-form-side">
      <form class="auth-card" method="post" action="api/login_handler.php" novalidate>
        <h1>ورود به حساب</h1>
        <p class="auth-sub">هنوز حساب نساختی؟ <a href="register.php">ثبت‌نام کن</a></p>

        <?php if ($error): ?>
          <div class="form-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="field">
          <label for="email">ایمیل</label>
          <input id="email" name="email" type="email" required autocomplete="email"
                 value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="password">رمز عبور</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-accent btn-block">ورود</button>

        <p class="auth-switch">حساب نداری؟ <a href="register.php">همین الان بساز</a></p>
      </form>
    </div>
  </div>
</main>

<?php require 'includes/footer.php'; ?>
