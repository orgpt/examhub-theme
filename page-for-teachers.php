<?php
defined( 'ABSPATH' ) || exit;

get_header();

$status = sanitize_text_field( wp_unslash( $_GET['teacher_submit'] ?? '' ) );
?>

<div class="container-xl py-4">
  <div class="eh-books-hero">
    <div>
      <span class="eh-books-hero__eyebrow">For Teachers</span>
      <h1 class="eh-page-title mb-2">للمدرسين</h1>
      <p class="eh-page-subtitle mb-0">ارفع ورقة الامتحان وسننشرها لك على المنصة ثم نرسل لك الرابط عبر الإيميل.</p>
    </div>
  </div>

  <?php if ( 'success' === $status ) : ?>
    <div class="alert alert-success mt-3">تم استلام طلبك بنجاح، وسيتم مراجعته ثم إرسال الرابط على الإيميل بعد النشر.</div>
  <?php elseif ( 'missing' === $status ) : ?>
    <div class="alert alert-danger mt-3">أكمل البيانات المطلوبة أولاً.</div>
  <?php elseif ( 'missing_file' === $status ) : ?>
    <div class="alert alert-danger mt-3">يجب رفع ورقة الامتحان.</div>
  <?php elseif ( 'upload_failed' === $status || 'failed' === $status ) : ?>
    <div class="alert alert-danger mt-3">حدث خطأ أثناء إرسال الطلب. حاول مرة أخرى.</div>
  <?php endif; ?>

  <div class="row g-4 mt-1">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body p-4">
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field( 'examhub_teacher_submit', 'examhub_teacher_nonce' ); ?>
            <input type="hidden" name="action" value="examhub_teacher_submit">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">اسم المدرس</label>
                <input class="form-control" type="text" name="teacher_name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">العام الدراسي</label>
                <input class="form-control" type="text" name="teacher_academic_year" placeholder="مثال: 2026 / 2027" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">المادة</label>
                <input class="form-control" type="text" name="teacher_subject" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">عنوان الامتحان</label>
                <input class="form-control" type="text" name="teacher_exam_title" placeholder="اختياري">
              </div>
              <div class="col-md-6">
                <label class="form-label">إيميل المدرس</label>
                <input class="form-control" type="email" name="teacher_email" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">رقم الهاتف</label>
                <input class="form-control" type="text" name="teacher_phone" required>
              </div>
              <div class="col-12">
                <label class="eh-check-choice">
                  <input type="checkbox" name="teacher_secret_enabled" value="1">
                  <span>
                    <strong>تفعيل كود امتحان سري</strong>
                    <small class="d-block text-muted">سيتم توليد كود عشوائي تلقائيًا وإرساله لك مع رابط الامتحان بعد النشر.</small>
                  </span>
                </label>
              </div>
              <div class="col-12">
                <label class="form-label">ورقة الامتحان</label>
                <input class="form-control" type="file" name="teacher_exam_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required>
                <div class="form-text">الصيغ المسموحة: PDF, DOC, DOCX, JPG, PNG, WEBP</div>
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">إرسال الطلب</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-body p-4">
          <h2 class="h5 mb-3">كيف تعمل الخدمة؟</h2>
          <div class="text-muted" style="line-height:2;">
            1. ارفع ورقة الامتحان من الفورم.
            <br>2. يراجع الأدمن الملف والبيانات.
            <br>3. يتم نشر الامتحان على المنصة.
            <br>4. يصلك رابط الامتحان النهائي على الإيميل لتشاركه مع الطلاب.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php get_footer(); ?>
