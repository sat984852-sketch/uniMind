<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/csrf.php';
require_login();
$uid = (int)current_user_id();

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid_post()) {
        flash_set('err', 'نشست منقضی شده. دوباره امتحان کن.');
        redirect('profile.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_name') {
        $name = trim((string)($_POST['fullname'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            flash_set('err', 'نام باید بین ۳ تا ۱۵۰ کاراکتر باشه.');
            redirect('profile.php');
        }
        $pdo->prepare('UPDATE users SET fullname = ? WHERE id = ?')->execute([$name, $uid]);
        $_SESSION['fullname'] = $name;
        flash_set('ok', 'نامت به‌روز شد.');
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $cur  = (string)($_POST['current_password'] ?? '');
        $new  = (string)($_POST['new_password'] ?? '');
        $new2 = (string)($_POST['new_password2'] ?? '');

        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($cur, $hash)) {
            flash_set('err', 'رمز فعلی درست نیست.');
        } elseif (strlen($new) < 8) {
            flash_set('err', 'رمز جدید باید حداقل ۸ کاراکتر باشه.');
        } elseif ($new !== $new2) {
            flash_set('err', 'رمز جدید و تکرارش یکی نیستن.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash_set('ok', 'رمز عبور تغییر کرد.');
        }
        redirect('profile.php');
    }
    redirect('profile.php');
}

// ---------------------------------------------------------------- data
$st = $pdo->prepare('SELECT fullname, email, created_at FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();
if (!$user) { redirect('api/logout.php'); }

function count_rows(PDO $pdo, string $sql, int $uid): int {
    $st = $pdo->prepare($sql);
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}
$stats = [
    'جزوه'              => count_rows($pdo, 'SELECT COUNT(*) FROM notes WHERE user_id = ?', $uid),
    'درس'               => count_rows($pdo, 'SELECT COUNT(*) FROM subjects WHERE user_id = ?', $uid),
    'گفت‌وگو'           => count_rows($pdo, 'SELECT COUNT(*) FROM conversations WHERE user_id = ?', $uid),
    'جلسه‌ی انجام‌شده'  => count_rows($pdo, "SELECT COUNT(*) FROM study_plans WHERE user_id = ? AND status = 'done'", $uid),
];

// حرف اول اسم برای آواتار
$initial = mb_substr(trim($user['fullname']), 0, 1);

$page_title = 'پروفایل';
$active = 'profile';
require 'includes/app_header.php';
?>

<div class="page-head"><h1>پروفایل</h1></div>

<?php flash_render(); ?>

<section class="panel profile-card">
  <div class="avatar" aria-hidden="true"><?php echo e($initial); ?></div>
  <div class="profile-info">
    <div class="profile-name" dir="auto"><?php echo e($user['fullname']); ?></div>
    <div class="profile-email" dir="ltr"><?php echo e($user['email']); ?></div>
    <div class="profile-since">عضو از <?php echo e(jalali_date($user['created_at'])); ?></div>
  </div>
</section>

<div class="stat-row profile-stats">
  <?php foreach ($stats as $label => $value): ?>
    <div class="stat">
      <div class="stat-value"><?php echo fa_digits($value); ?></div>
      <div class="stat-label"><?php echo e($label); ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="two-col">
  <section class="panel">
    <h3>ویرایش نام</h3>
    <form method="post" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_name">
      <div class="field">
        <label for="fullname">نام و نام خانوادگی</label>
        <input id="fullname" name="fullname" type="text" required maxlength="150" dir="auto"
               value="<?php echo e($user['fullname']); ?>">
      </div>
      <button type="submit" class="btn btn-accent">ذخیره</button>
    </form>
  </section>

  <section class="panel">
    <h3>تغییر رمز عبور</h3>
    <form method="post" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="change_password">
      <div class="field">
        <label for="cp">رمز فعلی</label>
        <input id="cp" name="current_password" type="password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="np">رمز جدید (حداقل ۸ کاراکتر)</label>
        <input id="np" name="new_password" type="password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="np2">تکرار رمز جدید</label>
        <input id="np2" name="new_password2" type="password" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-accent">تغییر رمز</button>
    </form>
  </section>
</div>

<?php require 'includes/app_footer.php'; ?>
