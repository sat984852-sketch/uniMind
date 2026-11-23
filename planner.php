<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/csrf.php';
require_login();
$uid   = (int)current_user_id();
$today = date('Y-m-d');

function owned_subject(PDO $pdo, int $uid, int $id): bool {
    if ($id <= 0) { return false; }
    $st = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    return (bool)$st->fetch();
}

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid_post()) {
        flash_set('err', 'نشست منقضی شده. دوباره امتحان کن.');
        redirect('planner.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_subject') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            flash_set('err', 'اسم درس باید بین ۱ تا ۱۵۰ کاراکتر باشه.');
            redirect('planner.php');
        }
        $st = $pdo->prepare('SELECT id FROM subjects WHERE user_id = ? AND name = ?');
        $st->execute([$uid, $name]);
        if ($st->fetch()) {
            flash_set('err', 'این درس قبلاً اضافه شده.');
            redirect('planner.php');
        }
        $pdo->prepare('INSERT INTO subjects (user_id, name) VALUES (?, ?)')->execute([$uid, $name]);
        flash_set('ok', 'درس اضافه شد.');
        redirect('planner.php');
    }

    if ($action === 'delete_subject') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM subjects WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        flash_set('ok', 'درس حذف شد. جزوه‌ها و برنامه‌های قبلیِ اون درس سر جاشون موندن، فقط بدون درس شدن.');
        redirect('planner.php');
    }

    if ($action === 'add_plan') {
        $title = trim((string)($_POST['title'] ?? ''));
        $date  = (string)($_POST['plan_date'] ?? '');
        $start = (string)($_POST['start_time'] ?? '');
        $end   = (string)($_POST['end_time'] ?? '');
        $sid   = (int)($_POST['subject_id'] ?? 0);

        $err = '';
        if ($title === '' || mb_strlen($title) > 190) {
            $err = 'عنوان برنامه رو بنویس (حداکثر ۱۹۰ کاراکتر).';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || $date > date('Y-m-d', strtotime('+365 day'))) {
            $err = 'روز انتخاب‌شده معتبر نیست.';
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            $err = 'ساعت شروع و پایان رو درست وارد کن.';
        } elseif ($end <= $start) {
            $err = 'ساعت پایان باید بعد از ساعت شروع باشه.';
        } elseif ($sid > 0 && !owned_subject($pdo, $uid, $sid)) {
            $err = 'درس انتخاب‌شده معتبر نیست.';
        }

        if ($err === '') {
            // همپوشانی با برنامه‌ی دیگه‌ی همون روز
            $st = $pdo->prepare(
                'SELECT title, start_time, end_time FROM study_plans
                  WHERE user_id = ? AND plan_date = ? AND start_time < ? AND end_time > ? LIMIT 1'
            );
            $st->execute([$uid, $date, $end . ':00', $start . ':00']);
            if ($clash = $st->fetch()) {
                $err = 'این بازه با «' . $clash['title'] . '» (' . format_time($clash['start_time'])
                     . ' تا ' . format_time($clash['end_time']) . ') همپوشانی داره.';
            }
        }
        if ($err !== '') {
            flash_set('err', $err);
            redirect('planner.php');
        }

        $pdo->prepare(
            'INSERT INTO study_plans (user_id, subject_id, title, plan_date, start_time, end_time)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $sid ?: null, $title, $date, $start . ':00', $end . ':00']);
        flash_set('ok', 'برنامه اضافه شد.');
        redirect('planner.php');
    }

    if ($action === 'toggle_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare(
            "UPDATE study_plans SET status = IF(status = 'done', 'pending', 'done') WHERE id = ? AND user_id = ?"
        )->execute([$id, $uid]);
        redirect('planner.php');
    }

    if ($action === 'delete_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM study_plans WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        flash_set('ok', 'برنامه حذف شد.');
        redirect('planner.php');
    }
    redirect('planner.php');
}

// ---------------------------------------------------------------- data
$st = $pdo->prepare('SELECT id, name FROM subjects WHERE user_id = ? ORDER BY name');
$st->execute([$uid]);
$subjects = $st->fetchAll();

$sel = 'SELECT p.id, p.title, p.plan_date, p.start_time, p.end_time, p.status, s.name AS subject
          FROM study_plans p LEFT JOIN subjects s ON s.id = p.subject_id
         WHERE p.user_id = ? AND p.plan_date ';

$st = $pdo->prepare($sel . '>= ? ORDER BY p.plan_date ASC, p.start_time ASC');
$st->execute([$uid, $today]);
$byDay = [];
foreach ($st->fetchAll() as $row) { $byDay[$row['plan_date']][] = $row; }

$st = $pdo->prepare($sel . '< ? ORDER BY p.plan_date DESC, p.start_time DESC LIMIT 10');
$st->execute([$uid, $today]);
$past = $st->fetchAll();

$days = upcoming_days(21);

$page_title = 'برنامه‌ی مطالعه';
$active = 'planner';
require 'includes/app_header.php';

function plan_row(array $p): void { ?>
  <li class="plan-row<?php echo $p['status'] === 'done' ? ' done' : ''; ?>">
    <form method="post" class="plan-check">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="toggle_plan">
      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
      <button type="submit" class="check<?php echo $p['status'] === 'done' ? ' on' : ''; ?>"
              aria-label="<?php echo $p['status'] === 'done' ? 'برگردوندن به انجام‌نشده' : 'علامت‌گذاری به‌عنوان انجام‌شده'; ?>">✓</button>
    </form>
    <div class="plan-time"><?php echo e(format_time($p['start_time'])); ?> – <?php echo e(format_time($p['end_time'])); ?></div>
    <div class="plan-body">
      <div class="plan-title" dir="auto"><?php echo e($p['title']); ?></div>
      <?php if ($p['subject']): ?><div class="plan-meta"><?php echo e($p['subject']); ?></div><?php endif; ?>
    </div>
    <form method="post" onsubmit="return confirm('این برنامه حذف بشه؟');">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="delete_plan">
      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
      <button type="submit" class="link-btn danger">حذف</button>
    </form>
  </li>
<?php } ?>

<div class="page-head">
  <h1>برنامه‌ی مطالعه</h1>
  <p>درس‌هات رو اضافه کن و برای هر کدوم روز و ساعت مطالعه‌ت رو مشخص کن.</p>
</div>

<?php flash_render(); ?>

<div class="two-col">
  <section class="panel">
    <h3>درس‌های من</h3>
    <form method="post" class="inline-form" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_subject">
      <input type="text" name="name" maxlength="150" required placeholder="مثلاً: ساختمان داده" dir="auto">
      <button type="submit" class="btn btn-accent">افزودن</button>
    </form>
    <?php if (!$subjects): ?>
      <p class="empty" style="margin-top:14px">هنوز درسی اضافه نکردی.</p>
    <?php else: ?>
      <ul class="subject-list">
        <?php foreach ($subjects as $s): ?>
          <li>
            <span dir="auto"><?php echo e($s['name']); ?></span>
            <form method="post" onsubmit="return confirm('درس «<?php echo e(addslashes($s['name'])); ?>» حذف بشه؟');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete_subject">
              <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
              <button type="submit" class="link-btn danger">حذف</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h3>برنامه‌ی جدید</h3>
    <form method="post" class="form-grid" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_plan">
      <div class="field span-2">
        <label for="p_title">چی می‌خوای بخونی؟</label>
        <input id="p_title" name="title" type="text" maxlength="190" required dir="auto" placeholder="مثلاً: مرور فصل ۲">
      </div>
      <div class="field span-2">
        <label for="p_subject">درس</label>
        <select id="p_subject" name="subject_id">
          <option value="0">— بدون درس —</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field span-2">
        <label for="p_date">روز</label>
        <select id="p_date" name="plan_date" required>
          <?php foreach ($days as $d): ?>
            <option value="<?php echo e($d['value']); ?>"><?php echo e($d['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="p_start">از ساعت</label>
        <input id="p_start" name="start_time" type="time" required value="16:00">
      </div>
      <div class="field">
        <label for="p_end">تا ساعت</label>
        <input id="p_end" name="end_time" type="time" required value="17:30">
      </div>
      <div class="span-2"><button type="submit" class="btn btn-accent">افزودن به برنامه</button></div>
    </form>
  </section>
</div>

<section class="panel list-panel">
  <h3>برنامه‌ی پیش رو</h3>
  <?php if (!$byDay): ?>
    <p class="empty">هنوز برنامه‌ای نداری. از فرم بالا اولین جلسه‌ی مطالعه‌ت رو اضافه کن.</p>
  <?php else: foreach ($byDay as $date => $rows): ?>
    <div class="day-block">
      <div class="day-title">
        <?php echo $date === $today ? 'امروز — ' : ''; ?><?php echo e(weekday_fa($date) . ' ' . jalali_date($date)); ?>
      </div>
      <ul class="plan-rows"><?php foreach ($rows as $p) { plan_row($p); } ?></ul>
    </div>
  <?php endforeach; endif; ?>
</section>

<?php if ($past): ?>
<section class="panel list-panel">
  <h3>گذشته <small>(۱۰ مورد آخر)</small></h3>
  <ul class="plan-rows">
    <?php foreach ($past as $p): ?>
      <li class="day-inline"><span class="plan-date"><?php echo e(jalali_date($p['plan_date'])); ?></span></li>
      <?php plan_row($p); ?>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php require 'includes/app_footer.php'; ?>
