<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/csrf.php';
require_login();
$uid = (int)current_user_id();

const NOTE_MAX_BYTES = 10 * 1024 * 1024;   // 10MB — اگه هاست محدودیت کمتری داره، اون اعمال می‌شه

/** پسوندهای مجاز و نوع واقعی فایل (با finfo چک می‌شه، نه فقط اسم فایل). */
$ALLOWED = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'txt'  => ['text/'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
];

function mime_allowed(string $mime, array $okList): bool {
    foreach ($okList as $ok) {
        if ($mime === $ok || (substr($ok, -1) === '/' && strpos($mime, $ok) === 0)) { return true; }
    }
    return false;
}

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid_post()) {
        flash_set('err', 'نشست منقضی شده. دوباره امتحان کن.');
        redirect('notes.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['note_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            flash_set('err', 'یه فایل انتخاب کن.');
            redirect('notes.php');
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > NOTE_MAX_BYTES) {
            flash_set('err', 'حجم فایل زیاده (حداکثر ۱۰ مگابایت).');
            redirect('notes.php');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash_set('err', 'آپلود انجام نشد. دوباره امتحان کن.');
            redirect('notes.php');
        }

        $orig = (string)$file['name'];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset($ALLOWED[$ext])) {
            flash_set('err', 'فرمت مجاز نیست. فقط PDF، عکس (JPG/PNG)، متن، Word و PowerPoint.');
            redirect('notes.php');
        }
        $fi   = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$fi->file($file['tmp_name']);
        if (!mime_allowed($mime, $ALLOWED[$ext])) {
            flash_set('err', 'محتوای فایل با پسوندش نمی‌خونه.');
            redirect('notes.php');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') { $title = pathinfo($orig, PATHINFO_FILENAME); }
        $title = mb_substr($title, 0, 190);

        $subjectId = (int)($_POST['subject_id'] ?? 0);
        if ($subjectId > 0) {
            $st = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
            $st->execute([$subjectId, $uid]);
            if (!$st->fetch()) { $subjectId = 0; }
        }

        $dir = __DIR__ . '/uploads/' . $uid;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            flash_set('err', 'ساخت پوشه‌ی ذخیره‌سازی ممکن نشد.');
            redirect('notes.php');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
            flash_set('err', 'ذخیره‌ی فایل روی سرور انجام نشد.');
            redirect('notes.php');
        }

        try {
            $ins = $pdo->prepare(
                'INSERT INTO notes (user_id, subject_id, title, original_filename, file_path) VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$uid, $subjectId ?: null, $title, mb_substr($orig, 0, 255), 'uploads/' . $uid . '/' . $stored]);
            flash_set('ok', 'جزوه ذخیره شد.');
        } catch (PDOException $ex) {
            @unlink($dir . '/' . $stored);
            flash_set('err', 'ذخیره در پایگاه داده خطا داد.');
        }
        redirect('notes.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT file_path FROM notes WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if ($row = $st->fetch()) {
            $full = __DIR__ . '/' . $row['file_path'];
            if (is_file($full)) { @unlink($full); }
            $pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
            flash_set('ok', 'جزوه حذف شد.');
        }
        redirect('notes.php');
    }
    redirect('notes.php');
}

// ---------------------------------------------------------------- data
$st = $pdo->prepare('SELECT id, name FROM subjects WHERE user_id = ? ORDER BY name');
$st->execute([$uid]);
$subjects = $st->fetchAll();

$filter = (int)($_GET['subject'] ?? 0);
$sql = 'SELECT n.id, n.title, n.original_filename, n.file_path, n.uploaded_at, s.name AS subject
          FROM notes n LEFT JOIN subjects s ON s.id = n.subject_id
         WHERE n.user_id = ?';
$args = [$uid];
if ($filter > 0) { $sql .= ' AND n.subject_id = ?'; $args[] = $filter; }
$sql .= ' ORDER BY n.uploaded_at DESC, n.id DESC';
$st = $pdo->prepare($sql);
$st->execute($args);
$notes = $st->fetchAll();

$page_title = 'جزوه‌ها';
$active = 'notes';
require 'includes/app_header.php';
?>

<div class="page-head">
  <h1>جزوه‌ها</h1>
  <p>جزوه‌هات رو اینجا نگه دار تا هر وقت خواستی دوباره بازشون کنی.</p>
</div>

<?php flash_render(); ?>

<section class="panel">
  <h3>آپلود جزوه‌ی جدید</h3>
  <form method="post" enctype="multipart/form-data" class="form-grid" autocomplete="off">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo NOTE_MAX_BYTES; ?>">

    <div class="field span-2">
      <label for="note_file">فایل (PDF، عکس، متن، Word، PowerPoint — حداکثر ۱۰ مگابایت)</label>
      <input id="note_file" name="note_file" type="file" required
             accept=".pdf,.jpg,.jpeg,.png,.txt,.docx,.pptx">
    </div>
    <div class="field">
      <label for="title">عنوان (اختیاری)</label>
      <input id="title" name="title" type="text" maxlength="190" placeholder="مثلاً: جزوه‌ی فصل ۳ ساختمان داده">
    </div>
    <div class="field">
      <label for="subject_id">درس</label>
      <select id="subject_id" name="subject_id">
        <option value="0">— بدون درس —</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$subjects): ?>
        <small class="hint">هنوز درسی نداری. از <a href="planner.php">برنامه‌ی مطالعه</a> درس‌هات رو اضافه کن.</small>
      <?php endif; ?>
    </div>
    <div class="span-2"><button type="submit" class="btn btn-accent">آپلود</button></div>
  </form>
</section>

<section class="panel list-panel">
  <div class="list-head">
    <h3>جزوه‌های من <small>(<?php echo fa_digits(count($notes)); ?>)</small></h3>
    <?php if ($subjects): ?>
      <div class="chips">
        <a class="chip<?php echo $filter === 0 ? ' on' : ''; ?>" href="notes.php">همه</a>
        <?php foreach ($subjects as $s): ?>
          <a class="chip<?php echo $filter === (int)$s['id'] ? ' on' : ''; ?>"
             href="notes.php?subject=<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$notes): ?>
    <p class="empty">هنوز جزوه‌ای نداری. اولین فایلت رو بالا آپلود کن.</p>
  <?php else: ?>
    <ul class="note-list">
      <?php foreach ($notes as $n):
          $full = __DIR__ . '/' . $n['file_path'];
          $size = is_file($full) ? format_bytes((int)filesize($full)) : 'فایل پیدا نشد';
          $ext  = strtolower(pathinfo($n['file_path'], PATHINFO_EXTENSION));
          $canView = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
      ?>
        <li class="note-item">
          <span class="file-ext"><?php echo e(strtoupper($ext)); ?></span>
          <div class="note-body">
            <div class="note-title" dir="auto"><?php echo e($n['title']); ?></div>
            <div class="note-meta">
              <?php if ($n['subject']): ?><span><?php echo e($n['subject']); ?></span> · <?php endif; ?>
              <span><?php echo e($size); ?></span> ·
              <span><?php echo e(jalali_date($n['uploaded_at'])); ?></span>
            </div>
          </div>
          <div class="note-actions">
            <?php if ($canView): ?>
              <a class="link-btn" target="_blank" rel="noopener" href="api/note_download.php?id=<?php echo (int)$n['id']; ?>&view=1">باز کردن</a>
            <?php endif; ?>
            <a class="link-btn" href="api/note_download.php?id=<?php echo (int)$n['id']; ?>">دانلود</a>
            <form method="post" onsubmit="return confirm('این جزوه حذف بشه؟');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
              <button type="submit" class="link-btn danger">حذف</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require 'includes/app_footer.php'; ?>
