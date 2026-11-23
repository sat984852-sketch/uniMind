<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/csrf.php';
require_login();

$page_title = 'گفت‌وگو با دستیار';
$active = 'chat';
require 'includes/app_header.php';
?>

<div class="chat-shell" id="chatShell" data-csrf="<?php echo e(csrf_token()); ?>">

  <aside class="chat-history" id="chatHistory">
    <button type="button" class="btn btn-accent btn-block" id="newChatBtn">+ گفت‌وگوی جدید</button>
    <div class="history-title">گفت‌وگوهای قبلی</div>
    <ul class="history-list" id="historyList">
      <li class="history-empty">در حال بارگذاری…</li>
    </ul>
  </aside>

  <section class="chat-panel">
    <div class="chat-toolbar">
      <button type="button" class="chat-tool" id="historyToggle">تاریخچه</button>
      <div class="chat-title" id="chatTitle">گفت‌وگوی جدید</div>
      <button type="button" class="chat-tool danger" id="clearChatBtn" disabled>پاک کردن گفت‌وگو</button>
    </div>

    <div class="chat-messages" id="chatMessages" aria-live="polite">
      <div class="chat-welcome" id="chatWelcome">
        <div class="brand-mark">UM</div>
        <h2>سلام! چطور می‌تونم کمکت کنم؟</h2>
        <p>درباره‌ی درس‌ها، مفاهیم، برنامه‌نویسی یا هر موضوع دانشگاهی سؤال بپرس.</p>
      </div>
    </div>

    <div class="chat-error" id="chatError" role="alert" hidden></div>

    <form class="chat-form" id="chatForm" autocomplete="off">
      <textarea id="chatInput" rows="1" maxlength="4000" dir="auto"
                placeholder="پیامت رو بنویس… (Enter ارسال، Shift+Enter خط جدید)"></textarea>
      <button type="submit" class="chat-send" id="chatSend" aria-label="ارسال">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="transform:scaleX(-1)"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
      </button>
    </form>
  </section>
</div>

<?php
$extra_scripts = '<script src="assets/js/chat.js"></script>';
require 'includes/app_footer.php';
