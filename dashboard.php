<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_login();

$uid = current_user_id();

// ---- stats ---------------------------------------------------------------
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE user_id = ?');
$stmt->execute([$uid]);
$notes_count = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE user_id = ?');
$stmt->execute([$uid]);
$chats_count = (int)$stmt->fetchColumn();

// Study time = total length of study-plan sessions marked "done".
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))), 0)
       FROM study_plans
      WHERE user_id = ? AND status = 'done'
        AND start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time"
);
$stmt->execute([$uid]);
$study_seconds = (int)$stmt->fetchColumn();

// ---- upcoming study plan -------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT p.title, p.plan_date, p.start_time, p.end_time, p.status, s.name AS subject
       FROM study_plans p
       LEFT JOIN subjects s ON s.id = p.subject_id
      WHERE p.user_id = ? AND p.plan_date >= CURDATE()
      ORDER BY p.plan_date ASC, p.start_time ASC
      LIMIT 5'
);
$stmt->execute([$uid]);
$plans = $stmt->fetchAll();

// ---- recent activity (chats, uploaded notes, quizzes taken) --------------
$stmt = $pdo->prepare(
    "(SELECT 'chat' AS type, title, created_at AS at FROM conversations WHERE user_id = ?)
     UNION ALL
     (SELECT 'note' AS type, title, uploaded_at AS at FROM notes WHERE user_id = ?)
     UNION ALL
     (SELECT 'quiz' AS type, q.title AS title, r.taken_at AS at
        FROM quiz_results r JOIN quizzes q ON q.id = r.quiz_id WHERE r.user_id = ?)
     ORDER BY at DESC
     LIMIT 6"
);
$stmt->execute([$uid, $uid, $uid]);
$activities = $stmt->fetchAll();

$activity_labels = ['chat' => 'گفت‌وگو', 'note' => 'جزوه', 'quiz' => 'آزمون'];

$page_title = 'داشبورد';
$active = 'dashboard';
require 'includes/app_header.php';
?>

<div class="dash-welcome">
  <h1>سلام، <?php echo e(current_user_name()); ?> 👋</h1>
  <p><?php echo e(jalali_date(date('Y-m-d'))); ?></p>
</div>

<div class="stat-row">
  <div class="stat">
    <div class="stat-value"><?php echo fa_digits($notes_count); ?></div>
    <div class="stat-label">جزوه‌ی ذخیره‌شده</div>
  </div>
  <div class="stat">
    <div class="stat-value"><?php echo fa_digits($chats_count); ?></div>
    <div class="stat-label">گفت‌وگو با دستیار</div>
  </div>
  <div class="stat">
    <div class="stat-value stat-value-sm"><?php echo e(format_duration($study_seconds)); ?></div>
    <div class="stat-label">زمان مطالعه‌ی ثبت‌شده</div>
  </div>
</div>

<div class="dash-grid">
  <section class="panel">
    <h3>برنامه‌ی مطالعه‌ی پیش رو</h3>
    <?php if (!$plans): ?>
      <p class="empty">هنوز برنامه‌ای ثبت نکردی. بعد از ساخته‌شدن بخش برنامه‌ی مطالعه، اینجا نمایش داده می‌شه.</p>
    <?php else: ?>
      <ul class="plan-list">
        <?php foreach ($plans as $plan): ?>
          <li class="plan-item<?php echo $plan['status'] === 'done' ? ' done' : ''; ?>">
            <div class="plan-date"><?php echo e(jalali_date($plan['plan_date'])); ?></div>
            <div class="plan-body">
              <div class="plan-title"><?php echo e($plan['title']); ?></div>
              <div class="plan-meta">
                <?php
                  $meta = [];
                  if ($plan['subject']) { $meta[] = $plan['subject']; }
                  if ($plan['start_time']) {
                      $meta[] = format_time($plan['start_time']) . ($plan['end_time'] ? ' تا ' . format_time($plan['end_time']) : '');
                  }
                  echo e(implode(' · ', $meta));
                ?>
              </div>
            </div>
            <?php if ($plan['status'] === 'done'): ?><span class="plan-done">انجام شد</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h3>فعالیت‌های اخیر</h3>
    <?php if (!$activities): ?>
      <p class="empty">هنوز فعالیتی نداشتی. وقتی جزوه آپلود کنی یا با دستیار گفت‌وگو کنی، اینجا می‌بینی.</p>
    <?php else: ?>
      <ul class="activity-list">
        <?php foreach ($activities as $a): ?>
          <li>
            <span class="activity-type"><?php echo e($activity_labels[$a['type']] ?? ''); ?></span>
            <span class="activity-title"><?php echo e($a['title']); ?></span>
            <span class="activity-date"><?php echo e(jalali_date($a['at'])); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<h3 class="quick-title">دسترسی سریع</h3>
<div class="quick">
  <?php foreach (nav_items() as $item): ?>
    <?php if ($item['key'] === 'dashboard') { continue; } ?>
    <?php if ($item['ready']): ?>
      <a class="quick-item" href="<?php echo e($item['href']); ?>"><?php echo e($item['label']); ?></a>
    <?php else: ?>
      <span class="quick-item disabled" aria-disabled="true">
        <?php echo e($item['label']); ?> <small class="soon">به‌زودی</small>
      </span>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<?php require 'includes/app_footer.php'; ?>
