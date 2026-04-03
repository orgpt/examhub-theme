<?php
/**
 * ExamHub — PDF Import & Question Bank
 * Upload PDF → extract text → AI parse → review → insert to ACF.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN PAGE: PDF IMPORTER
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'examhub_register_pdf_import_page' );
function examhub_register_pdf_import_page() {
    $core_caps = function_exists( 'examhub_get_core_capabilities' )
        ? examhub_get_core_capabilities()
        : [
            'access_content' => 'examhub_access_content',
            'import_content' => 'examhub_import_content',
        ];
    add_submenu_page(
        'examhub-content',
        __( 'استيراد PDF', 'examhub' ),
        __( '📄 استيراد PDF', 'examhub' ),
        $core_caps['import_content'],
        'examhub-pdf-import',
        'examhub_render_json_import_page'
    );

    add_submenu_page(
        'examhub-content',
        __( 'بنك الأسئلة — مراجعة', 'examhub' ),
        __( '✅ مراجعة المستورَد', 'examhub' ),
        $core_caps['import_content'],
        'examhub-review-questions',
        'examhub_render_review_page'
    );

    add_submenu_page(
        'examhub-content',
        __( 'Automatic Exams', 'examhub' ),
        __( 'Automatic Exams', 'examhub' ),
        $core_caps['import_content'],
        'examhub-auto-exams',
        'examhub_render_auto_exams_page'
    );
}

function examhub_render_json_import_page() {
    $systems = get_posts( [
        'post_type'      => 'eh_education_system',
        'posts_per_page' => 20,
    ] );
    ?>
    <div class="wrap">
      <h1><?php esc_html_e( 'استيراد أسئلة JSON', 'examhub' ); ?></h1>
      <p class="description"><?php esc_html_e( 'ارفع ملف JSON جاهز، راجع المعاينة، ثم ابدأ الاستيراد والنشر مباشرة بدون AI.', 'examhub' ); ?></p>

      <div class="card" style="max-width:960px; padding:20px; margin:20px 0;">
        <h2><?php esc_html_e( 'رفع الملف وربط التصنيف', 'examhub' ); ?></h2>

        <form id="eh-json-import-form" enctype="multipart/form-data">
          <?php wp_nonce_field( 'examhub_admin_ajax', 'nonce' ); ?>

          <table class="form-table">
            <tr>
              <th><?php esc_html_e( 'ملف JSON', 'examhub' ); ?></th>
              <td>
                <input type="file" name="import_file" id="import_file" accept=".json,application/json,text/json" required class="regular-text">
                <p class="description"><?php esc_html_e( 'الصيغة الموصى بها: questions -> question_text / type / difficulty / answers / is_correct', 'examhub' ); ?></p>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'النظام التعليمي', 'examhub' ); ?></th>
              <td>
                <select name="education_system" id="json-edu-system" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option>
                  <?php foreach ( $systems as $sys ) : ?>
                    <option value="<?php echo esc_attr( $sys->ID ); ?>"><?php echo esc_html( $sys->post_title ); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المرحلة *', 'examhub' ); ?></th>
              <td>
                <select name="stage_id" id="json-stage" class="regular-text" required>
                  <option value=""><?php esc_html_e( '-- اختر --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'الصف', 'examhub' ); ?></th>
              <td>
                <select name="grade_id" id="json-grade" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المادة', 'examhub' ); ?></th>
              <td>
                <select name="subject_id" id="json-subject" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'مجموعة الأسئلة', 'examhub' ); ?></th>
              <td>
                <select name="question_group_id" id="json-group" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'مصدر الكتاب', 'examhub' ); ?></th>
              <td>
                <select name="book_source" id="json-book-source" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر --', 'examhub' ); ?></option>
                  <option value="moasir"><?php esc_html_e( 'المعاصر', 'examhub' ); ?></option>
                  <option value="imtihan"><?php esc_html_e( 'الامتحان', 'examhub' ); ?></option>
                  <option value="selah_tilmeed"><?php esc_html_e( 'سلاح التلميذ', 'examhub' ); ?></option>
                  <option value="ministry"><?php esc_html_e( 'منهج الوزارة', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
          </table>

          <p>
            <button type="submit" class="button button-primary" id="eh-json-preview-btn"><?php esc_html_e( 'تحليل الملف وعرض المعاينة', 'examhub' ); ?></button>
          </p>
        </form>

        <div id="eh-json-progress" style="display:none;">
          <h3><?php esc_html_e( 'جاري تحليل الملف...', 'examhub' ); ?></h3>
          <div style="background:#ddd;border-radius:4px;height:20px;margin:10px 0;">
            <div id="eh-json-progress-bar" style="background:#2271b1;height:100%;border-radius:4px;width:0%;transition:width .3s;"></div>
          </div>
          <p id="eh-json-progress-text"><?php esc_html_e( 'قراءة الملف...', 'examhub' ); ?></p>
        </div>

        <div id="eh-json-results" style="display:none;">
          <h3><?php esc_html_e( 'معاينة الأسئلة', 'examhub' ); ?></h3>
          <div id="eh-json-summary" style="margin-bottom:12px;"></div>
          <div id="eh-json-preview-list"></div>
          <p>
            <button type="button" class="button button-primary" id="eh-json-import-btn" style="display:none;"><?php esc_html_e( 'بدء الاستيراد والنشر', 'examhub' ); ?></button>
          </p>
        </div>
      </div>
    </div>
    <script>
    jQuery(function($){
      function nonce() { return $('#eh-json-import-form input[name="nonce"]').val() || ''; }
      function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
      function resetSelect($select, placeholderText) { $select.html(`<option value="">${placeholderText}</option>`).prop('disabled', false); }
      function setSelectOptions($select, items, placeholderText) {
        let html = `<option value="">${placeholderText}</option>`;
        (items || []).forEach(function(item){
          html += `<option value="${item.id || ''}">${escapeHtml(item.label || item.name || '')}</option>`;
        });
        $select.html(html).prop('disabled', false);
      }
      function bindHierarchy(prefix) {
        const $system = $(`#${prefix}-edu-system`);
        const $stage = $(`#${prefix}-stage`);
        const $grade = $(`#${prefix}-grade`);
        const $subject = $(`#${prefix}-subject`);
        const $group = $(`#${prefix}-group`);

        $system.on('change', function(){
          const systemId = $(this).val();
          resetSelect($stage, '-- اختر --');
          resetSelect($grade, '-- اختر (اختياري) --');
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر (اختياري) --');
          if (!systemId) return;
          $stage.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_stages_by_system', nonce: nonce(), system_id: systemId }, function(res){
            if (res && res.success) setSelectOptions($stage, res.data, '-- اختر --');
            else resetSelect($stage, '-- لا توجد مراحل --');
          }).fail(function(){ resetSelect($stage, '-- Error --'); });
        });

        $stage.on('change', function(){
          const stageId = $(this).val();
          resetSelect($grade, '-- اختر (اختياري) --');
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر (اختياري) --');
          if (!stageId) return;
          $grade.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_grades_by_stage', nonce: nonce(), stage_id: stageId }, function(res){
            if (res && res.success) setSelectOptions($grade, res.data, '-- اختر (اختياري) --');
            else resetSelect($grade, '-- لا توجد صفوف --');
          }).fail(function(){ resetSelect($grade, '-- Error --'); });
        });

        $grade.on('change', function(){
          const gradeId = $(this).val();
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر (اختياري) --');
          if (!gradeId) return;
          $subject.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_subjects_by_grade', nonce: nonce(), grade_id: gradeId }, function(res){
            if (res && res.success) setSelectOptions($subject, res.data, '-- اختر (اختياري) --');
            else resetSelect($subject, '-- لا توجد مواد --');
          }).fail(function(){ resetSelect($subject, '-- Error --'); });
        });

        $subject.on('change', function(){
          const subjectId = $(this).val();
          resetSelect($group, '-- اختر (اختياري) --');
          if (!subjectId) return;
          $group.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_question_groups_by_subject', nonce: nonce(), subject_id: subjectId }, function(res){
            if (res && res.success) setSelectOptions($group, res.data, '-- اختر (اختياري) --');
            else resetSelect($group, '-- لا توجد مجموعات --');
          }).fail(function(){ resetSelect($group, '-- Error --'); });
        });
      }

      function updateProgress(pct, text) {
        $('#eh-json-progress-bar').css('width', pct + '%');
        $('#eh-json-progress-text').text(text);
      }

      function renderPreview(data) {
        const questions = data.questions || [];
        const invalid = data.invalid || [];
        const typeLabels = { mcq: 'اختيار من متعدد', true_false: 'صح وخطأ', fill_blank: 'أكمل الفراغ', essay: 'مقالي', matching: 'مطابقة', ordering: 'ترتيب', correct: 'إجابة صحيحة', math: 'رياضيات' };
        const diffLabels = { easy: 'سهل', medium: 'متوسط', hard: 'صعب' };

        $('#eh-json-results').show();
        $('#eh-json-summary').html(
          `<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;">
            <strong>${questions.length}</strong> سؤال صالح
            <span style="margin-right:12px;"><strong>${invalid.length}</strong> سؤال غير صالح</span>
            <span style="margin-right:12px;">إجمالي الملف: <strong>${data.total || questions.length}</strong></span>
          </div>`
        );

        let html = '<label style="display:block;margin-bottom:10px;"><input type="checkbox" id="eh-json-select-all" checked> تحديد/إلغاء الكل</label>';

        questions.forEach(function(q, index){
          const answers = (q.answers || []).map(function(answer, answerIndex){
            return `<div>${answer.is_correct ? '<strong style="color:#008a20;">✓</strong> ' : ''}${answerIndex + 1}. ${escapeHtml(answer.answer_text || '')}</div>`;
          }).join('');
          const body = String(q.body || '').trim();
          const warnings = (q._warnings || []).map(function(item){
            return `<li>${escapeHtml(item)}</li>`;
          }).join('');

          html += `
            <div style="border:1px solid #dcdcde;border-radius:6px;padding:12px;margin:10px 0;background:#fff;">
              <label style="display:flex;gap:10px;align-items:flex-start;">
                <input type="checkbox" class="eh-json-question" value="${index}" checked style="margin-top:3px;">
                <div style="flex:1;">
                  <div style="font-weight:700;margin-bottom:8px;">${escapeHtml(q.question_text || '')}</div>
                  ${body ? `<div style="margin-bottom:8px;line-height:1.8;color:#50575e;">${escapeHtml(body)}</div>` : ''}
                  <div style="margin-bottom:8px;">
                    <span style="background:#eef2ff;padding:2px 8px;border-radius:3px;font-size:12px;">${escapeHtml(typeLabels[q.type] || q.type || '')}</span>
                    <span style="background:#fef3c7;padding:2px 8px;border-radius:3px;font-size:12px;margin-right:4px;">${escapeHtml(diffLabels[q.difficulty] || q.difficulty || '')}</span>
                  </div>
                  ${answers ? `<div style="line-height:1.8;font-size:13px;">${answers}</div>` : ''}
                  ${q.explanation ? `<div style="margin-top:8px;color:#50575e;"><strong>الشرح:</strong> ${escapeHtml(q.explanation)}</div>` : ''}
                  ${warnings ? `<ul style="margin:8px 18px 0;color:#996800;">${warnings}</ul>` : ''}
                </div>
              </label>
            </div>`;
        });

        if (invalid.length) {
          html += '<div style="margin-top:16px;border:1px solid #f0b849;background:#fff8e5;padding:12px;border-radius:6px;"><strong>عناصر غير صالحة:</strong><ul style="margin:8px 18px 0;">';
          invalid.forEach(function(item){
            html += `<li>العنصر ${item.index}: ${escapeHtml(item.reason || 'بيانات غير مكتملة')}</li>`;
          });
          html += '</ul></div>';
        }

        $('#eh-json-preview-list').html(html);
        $('#eh-json-import-btn').show().data('questions', questions).prop('disabled', false).text('بدء الاستيراد والنشر');

        $('#eh-json-select-all').off('change').on('change', function(){
          $('.eh-json-question').prop('checked', this.checked);
        });
      }

      bindHierarchy('json');

      $('#eh-json-import-form').on('submit', function(e){
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'eh_import_json_preview');
        $('#eh-json-preview-btn').prop('disabled', true);
        $('#eh-json-progress').show();
        $('#eh-json-results').hide();
        updateProgress(20, 'Uploading file...');

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false
        }).done(function(res){
          if (res && res.success) {
            updateProgress(100, 'Preview ready');
            renderPreview(res.data);
          } else {
            alert(res?.data?.message || 'تعذر تحليل الملف');
          }
        }).fail(function(){
          alert('تعذر الاتصال بالخادم');
        }).always(function(){
          $('#eh-json-preview-btn').prop('disabled', false);
        });
      });

      $('#eh-json-import-btn').on('click', function(){
        const questions = $(this).data('questions') || [];
        const selectedIndexes = $('.eh-json-question:checked').map((_, el) => parseInt(el.value, 10)).get();
        const selectedQuestions = selectedIndexes.map(index => questions[index]).filter(Boolean);
        if (!selectedQuestions.length) {
          alert('اختر سؤالًا واحدًا على الأقل');
          return;
        }

        $(this).prop('disabled', true).text('جاري الاستيراد والنشر...');

        $.post(ajaxurl, {
          action: 'eh_import_json_publish',
          nonce: nonce(),
          questions: JSON.stringify(selectedQuestions),
          extra: JSON.stringify({
            education_system: $('#json-edu-system').val(),
            stage_id: $('#json-stage').val(),
            grade_id: $('#json-grade').val(),
            subject_id: $('#json-subject').val(),
            question_group_id: $('#json-group').val(),
            book_source: $('#json-book-source').val()
          })
        }).done(function(res){
          if (res && res.success) {
            alert(res.data.message || 'تم الاستيراد بنجاح');
            window.location.reload();
          } else {
            alert(res?.data?.message || 'تعذر تنفيذ الاستيراد');
            $('#eh-json-import-btn').prop('disabled', false).text('بدء الاستيراد والنشر');
          }
        }).fail(function(){
          alert('تعذر الاتصال بالخادم');
          $('#eh-json-import-btn').prop('disabled', false).text('بدء الاستيراد والنشر');
        });
      });
    });
    </script>
    <?php
}

function examhub_render_auto_exams_page() {
    $systems = get_posts( [
        'post_type'      => 'eh_education_system',
        'posts_per_page' => 20,
    ] );
    ?>
    <div class="wrap">
      <h1><?php esc_html_e( 'إنشاء امتحانات تلقائية', 'examhub' ); ?></h1>
      <p class="description"><?php esc_html_e( 'اختر مجموعة الأسئلة، راجع المعاينة، ثم أنشئ امتحانات Draft بعدد أسئلة ثابت وعشوائية توزيع.', 'examhub' ); ?></p>

      <div class="card" style="max-width:960px; padding:20px; margin:20px 0;">
        <form id="eh-auto-exams-form">
          <?php wp_nonce_field( 'examhub_admin_ajax', 'nonce' ); ?>
          <table class="form-table">
            <tr>
              <th><?php esc_html_e( 'النظام التعليمي', 'examhub' ); ?></th>
              <td>
                <select name="education_system" id="auto-edu-system" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option>
                  <?php foreach ( $systems as $sys ) : ?>
                    <option value="<?php echo esc_attr( $sys->ID ); ?>"><?php echo esc_html( $sys->post_title ); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المرحلة *', 'examhub' ); ?></th>
              <td><select name="stage_id" id="auto-stage" class="regular-text" required><option value=""><?php esc_html_e( '-- اختر --', 'examhub' ); ?></option></select></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'الصف', 'examhub' ); ?></th>
              <td><select name="grade_id" id="auto-grade" class="regular-text"><option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option></select></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المادة', 'examhub' ); ?></th>
              <td><select name="subject_id" id="auto-subject" class="regular-text"><option value=""><?php esc_html_e( '-- اختر (اختياري) --', 'examhub' ); ?></option></select></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'مجموعة الأسئلة *', 'examhub' ); ?></th>
              <td><select name="question_group_id" id="auto-group" class="regular-text" required><option value=""><?php esc_html_e( '-- اختر --', 'examhub' ); ?></option></select></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'العنوان الأساسي', 'examhub' ); ?></th>
              <td><input type="text" name="base_title" id="auto-base-title" class="regular-text" value="<?php echo esc_attr__( 'المراجعة النهائية', 'examhub' ); ?>" required></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'عدد الامتحانات', 'examhub' ); ?></th>
              <td><input type="number" name="exam_count" id="auto-exam-count" class="small-text" value="1" min="1" required></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'عدد الأسئلة بكل امتحان', 'examhub' ); ?></th>
              <td><input type="number" name="questions_per_exam" id="auto-questions-per-exam" class="small-text" value="30" min="1" required></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'الإعدادات', 'examhub' ); ?></th>
              <td>
                <label><input type="checkbox" name="random_questions" id="auto-random-questions" value="1" checked> <?php esc_html_e( 'عشوائية ترتيب الأسئلة', 'examhub' ); ?></label><br>
                <label><input type="checkbox" name="random_answers" id="auto-random-answers" value="1" checked> <?php esc_html_e( 'عشوائية ترتيب الإجابات', 'examhub' ); ?></label>
              </td>
            </tr>
          </table>
          <p>
            <button type="button" class="button" id="eh-auto-exams-preview-btn"><?php esc_html_e( 'معاينة الإنشاء', 'examhub' ); ?></button>
            <button type="button" class="button button-primary" id="eh-auto-exams-create-btn" style="display:none;"><?php esc_html_e( 'بدء إنشاء الامتحانات', 'examhub' ); ?></button>
          </p>
        </form>

        <div id="eh-auto-exams-preview" style="display:none;"></div>
      </div>
    </div>
    <script>
    jQuery(function($){
      function nonce() { return $('#eh-auto-exams-form input[name="nonce"]').val() || ''; }
      function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
      function resetSelect($select, placeholderText) { $select.html(`<option value="">${placeholderText}</option>`).prop('disabled', false); }
      function setSelectOptions($select, items, placeholderText) {
        let html = `<option value="">${placeholderText}</option>`;
        (items || []).forEach(function(item){
          html += `<option value="${item.id || ''}">${escapeHtml(item.label || item.name || '')}</option>`;
        });
        $select.html(html).prop('disabled', false);
      }
      function bindHierarchy(prefix) {
        const $system = $(`#${prefix}-edu-system`);
        const $stage = $(`#${prefix}-stage`);
        const $grade = $(`#${prefix}-grade`);
        const $subject = $(`#${prefix}-subject`);
        const $group = $(`#${prefix}-group`);

        $system.on('change', function(){
          const systemId = $(this).val();
          resetSelect($stage, '-- اختر --');
          resetSelect($grade, '-- اختر (اختياري) --');
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر --');
          if (!systemId) return;
          $stage.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_stages_by_system', nonce: nonce(), system_id: systemId }, function(res){
            if (res && res.success) setSelectOptions($stage, res.data, '-- اختر --');
            else resetSelect($stage, '-- لا توجد مراحل --');
          });
        });
        $stage.on('change', function(){
          const stageId = $(this).val();
          resetSelect($grade, '-- اختر (اختياري) --');
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر --');
          if (!stageId) return;
          $grade.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_grades_by_stage', nonce: nonce(), stage_id: stageId }, function(res){
            if (res && res.success) setSelectOptions($grade, res.data, '-- اختر (اختياري) --');
            else resetSelect($grade, '-- لا توجد صفوف --');
          });
        });
        $grade.on('change', function(){
          const gradeId = $(this).val();
          resetSelect($subject, '-- اختر (اختياري) --');
          resetSelect($group, '-- اختر --');
          if (!gradeId) return;
          $subject.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_subjects_by_grade', nonce: nonce(), grade_id: gradeId }, function(res){
            if (res && res.success) setSelectOptions($subject, res.data, '-- اختر (اختياري) --');
            else resetSelect($subject, '-- لا توجد مواد --');
          });
        });
        $subject.on('change', function(){
          const subjectId = $(this).val();
          resetSelect($group, '-- اختر --');
          if (!subjectId) return;
          $group.html('<option value="">Loading...</option>').prop('disabled', true);
          $.post(ajaxurl, { action: 'eh_admin_get_question_groups_by_subject', nonce: nonce(), subject_id: subjectId }, function(res){
            if (res && res.success) setSelectOptions($group, res.data, '-- اختر --');
            else resetSelect($group, '-- لا توجد مجموعات --');
          });
        });
      }

      function formPayload() {
        return {
          nonce: nonce(),
          education_system: $('#auto-edu-system').val(),
          stage_id: $('#auto-stage').val(),
          grade_id: $('#auto-grade').val(),
          subject_id: $('#auto-subject').val(),
          question_group_id: $('#auto-group').val(),
          base_title: $('#auto-base-title').val(),
          exam_count: $('#auto-exam-count').val(),
          questions_per_exam: $('#auto-questions-per-exam').val(),
          random_questions: $('#auto-random-questions').is(':checked') ? 1 : 0,
          random_answers: $('#auto-random-answers').is(':checked') ? 1 : 0
        };
      }

      bindHierarchy('auto');

      $('#eh-auto-exams-preview-btn').on('click', function(){
        $.post(ajaxurl, Object.assign({ action: 'eh_preview_auto_exams' }, formPayload()), function(res){
          if (!(res && res.success)) {
            alert(res?.data?.message || 'تعذر تجهيز المعاينة');
            return;
          }
          const data = res.data || {};
          const names = (data.sample_names || []).map(item => `<li>${escapeHtml(item)}</li>`).join('');
          $('#eh-auto-exams-preview').show().html(`
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
              <p><strong>${data.available_questions}</strong> سؤال متاح داخل المجموعة.</p>
              <p>سيتم إنشاء <strong>${data.exam_count}</strong> امتحان، كل امتحان يحتوي على <strong>${data.questions_per_exam}</strong> سؤالًا.</p>
              <p>سيتم حفظ جميع الامتحانات كـ <strong>Draft</strong> مع السماح بإعادة استخدام الأسئلة بين الامتحانات.</p>
              ${names ? `<p><strong>أمثلة الأسماء:</strong></p><ul style="margin:8px 18px 0;">${names}</ul>` : ''}
            </div>
          `);
          $('#eh-auto-exams-create-btn').show().prop('disabled', false);
        });
      });

      $('#eh-auto-exams-create-btn').on('click', function(){
        $(this).prop('disabled', true).text('جاري إنشاء الامتحانات...');
        $.post(ajaxurl, Object.assign({ action: 'eh_create_auto_exams' }, formPayload()), function(res){
          if (res && res.success) {
            alert(res.data.message || 'تم إنشاء الامتحانات بنجاح');
            window.location.reload();
          } else {
            alert(res?.data?.message || 'تعذر إنشاء الامتحانات');
            $('#eh-auto-exams-create-btn').prop('disabled', false).text('بدء إنشاء الامتحانات');
          }
        }).fail(function(){
          alert('تعذر الاتصال بالخادم');
          $('#eh-auto-exams-create-btn').prop('disabled', false).text('بدء إنشاء الامتحانات');
        });
      });
    });
    </script>
    <?php
}

function examhub_render_pdf_import_page() {
    $systems  = get_posts( [ 'post_type' => 'eh_education_system', 'posts_per_page' => 20 ] );
    ?>
    <div class="wrap">
      <h1><?php esc_html_e( '📄 استيراد أسئلة من PDF', 'examhub' ); ?></h1>
      <p class="description"><?php esc_html_e( 'ارفع ملف PDF واستخرج الأسئلة تلقائياً باستخدام الذكاء الاصطناعي مع دعم العربية OCR.', 'examhub' ); ?></p>

      <div class="card" style="max-width:800px; padding:20px; margin:20px 0;">
        <h2><?php esc_html_e( 'رفع ملف PDF', 'examhub' ); ?></h2>

        <form id="eh-pdf-upload-form" enctype="multipart/form-data">
          <?php wp_nonce_field( 'examhub_admin_ajax', 'nonce' ); ?>

          <table class="form-table">
            <tr>
              <th><?php esc_html_e( 'ملف PDF', 'examhub' ); ?></th>
              <td>
                <input type="file" name="import_file" id="import_file" accept=".pdf,.json,application/pdf,application/json,text/json" required class="regular-text">
                <p class="description"><?php esc_html_e( 'الحد الأقصى: 10MB. يدعم العربية والإنجليزية.', 'examhub' ); ?></p>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'النظام التعليمي', 'examhub' ); ?></th>
              <td>
                <select name="education_system" id="pdf-edu-system" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري)', 'examhub' ); ?></option>
                  <?php foreach ( $systems as $sys ) : ?>
                    <option value="<?php echo $sys->ID; ?>"><?php echo esc_html( $sys->post_title ); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المرحلة *', 'examhub' ); ?></th>
              <td>
                <select name="stage_id" id="pdf-stage" class="regular-text" required>
                  <option value=""><?php esc_html_e( '-- اختر --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'الصف', 'examhub' ); ?></th>
              <td>
                <select name="grade_id" id="pdf-grade" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري)', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'المادة', 'examhub' ); ?></th>
              <td>
                <select name="subject_id" id="pdf-subject" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر (اختياري)', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'Question Group (Optional)', 'examhub' ); ?></th>
              <td>
                <select name="question_group_id" id="pdf-group" class="regular-text">
                  <option value=""><?php esc_html_e( '-- Select (Optional) --', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'مصدر الكتاب', 'examhub' ); ?></th>
              <td>
                <select name="book_source" class="regular-text">
                  <option value=""><?php esc_html_e( '-- اختر', 'examhub' ); ?></option>
                  <option value="moasir"><?php esc_html_e( 'المعاصر', 'examhub' ); ?></option>
                  <option value="imtihan"><?php esc_html_e( 'الامتحان', 'examhub' ); ?></option>
                  <option value="selah_tilmeed"><?php esc_html_e( 'سلاح التلميذ', 'examhub' ); ?></option>
                  <option value="ministry"><?php esc_html_e( 'منهج الوزارة', 'examhub' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'خيارات', 'examhub' ); ?></th>
              <td>
                <label><input type="checkbox" name="auto_detect" value="1" checked> <?php esc_html_e( 'تحديد المادة والصف تلقائياً', 'examhub' ); ?></label><br>
                <label><input type="checkbox" name="detect_duplicates" value="1" checked> <?php esc_html_e( 'كشف التكرار', 'examhub' ); ?></label><br>
                <label><input type="checkbox" name="auto_explain" value="1"> <?php esc_html_e( 'توليد الشرح تلقائياً (أبطأ)', 'examhub' ); ?></label>
              </td>
            </tr>
          </table>

          <p>
            <button type="submit" class="button button-primary" id="eh-pdf-submit">
              <?php esc_html_e( '🤖 استخراج الأسئلة بالذكاء الاصطناعي', 'examhub' ); ?>
            </button>
          </p>
        </form>

        <div id="pdf-progress" style="display:none;">
          <h3><?php esc_html_e( 'جاري المعالجة...', 'examhub' ); ?></h3>
          <div class="eh-progress-bar" style="background:#ddd;border-radius:4px;height:20px;margin:10px 0;">
            <div id="pdf-progress-bar" style="background:#2271b1;height:100%;border-radius:4px;width:0%;transition:width .3s;"></div>
          </div>
          <p id="pdf-status-text"><?php esc_html_e( 'استخراج النص...', 'examhub' ); ?></p>
        </div>

        <div id="pdf-results" style="display:none;">
          <h3><?php esc_html_e( 'الأسئلة المستخرجة', 'examhub' ); ?></h3>
          <p><?php esc_html_e( 'راجع الأسئلة وتأكد من صحتها قبل الحفظ.', 'examhub' ); ?></p>
          <div id="extracted-questions-list"></div>
          <p>
            <button type="button" class="button button-primary" id="save-all-questions" style="display:none;">
              <?php esc_html_e( '💾 حفظ الأسئلة المحددة', 'examhub' ); ?>
            </button>
          </p>
        </div>
      </div>
    </div>

    <script>
jQuery(function($){
  function getAdminNonce() {
    return $('#eh-pdf-upload-form input[name="nonce"]').val() || '';
  }

  function resetSelect($select, placeholderText) {
    $select.html(`<option value="">${placeholderText}</option>`).prop('disabled', false);
  }

  function setSelectOptions($select, items, placeholderText) {
    let html = `<option value="">${placeholderText}</option>`;
    (items || []).forEach(function(item){
      const id = item.id || '';
      const label = item.label || item.name || '';
      html += `<option value="${id}">${$('<div>').text(label).html()}</option>`;
    });
    $select.html(html).prop('disabled', false);
  }

  $('#pdf-edu-system').on('change', function(){
    const systemId = $(this).val();
    const $stage   = $('#pdf-stage');
    const $grade   = $('#pdf-grade');
    const $subject = $('#pdf-subject');
    const $group   = $('#pdf-group');

    resetSelect($stage, '-- اختر --');
    resetSelect($grade, '-- Select (Optional) --');
    resetSelect($subject, '-- Select (Optional) --');
    resetSelect($group, '-- Select (Optional) --');
    if (!systemId) return;

    $stage.html('<option value="">Loading...</option>').prop('disabled', true);
    $.post(ajaxurl, {
      action: 'eh_admin_get_stages_by_system',
      nonce: getAdminNonce(),
      system_id: systemId
    }, function(res){
      if (res && res.success) {
        setSelectOptions($stage, res.data, '-- اختر --');
      } else {
        resetSelect($stage, '-- لا توجد مراحل --');
      }
    }).fail(function(){
      resetSelect($stage, '-- Error --');
    });
  });

  $('#pdf-stage').on('change', function(){
    const stageId  = $(this).val();
    const $grade   = $('#pdf-grade');
    const $subject = $('#pdf-subject');
    const $group   = $('#pdf-group');
    resetSelect($grade, '-- Select (Optional) --');
    resetSelect($subject, '-- Select (Optional) --');
    resetSelect($group, '-- Select (Optional) --');
    if (!stageId) return;

    $grade.html('<option value="">Loading...</option>').prop('disabled', true);
    $.post(ajaxurl, {
      action: 'eh_admin_get_grades_by_stage',
      nonce: getAdminNonce(),
      stage_id: stageId
    }, function(res){
      if (res && res.success) {
        setSelectOptions($grade, res.data, '-- Select (Optional) --');
      } else {
        resetSelect($grade, '-- No grades --');
      }
    }).fail(function(){
      resetSelect($grade, '-- Error --');
    });
  });

  $('#pdf-grade').on('change', function(){
    const gradeId  = $(this).val();
    const $subject = $('#pdf-subject');
    const $group   = $('#pdf-group');
    resetSelect($subject, '-- Select (Optional) --');
    resetSelect($group, '-- Select (Optional) --');
    if (!gradeId) return;

    $subject.html('<option value="">Loading...</option>').prop('disabled', true);
    $.post(ajaxurl, {
      action: 'eh_admin_get_subjects_by_grade',
      nonce: getAdminNonce(),
      grade_id: gradeId
    }, function(res){
      if (res && res.success) {
        setSelectOptions($subject, res.data, '-- Select (Optional) --');
      } else {
        resetSelect($subject, '-- No subjects --');
      }
    }).fail(function(){
      resetSelect($subject, '-- Error --');
    });
  });

  $('#pdf-subject').on('change', function(){
    const subjectId = $(this).val();
    const $group    = $('#pdf-group');
    resetSelect($group, '-- Select (Optional) --');
    if (!subjectId) return;

    $group.html('<option value="">Loading...</option>').prop('disabled', true);
    $.post(ajaxurl, {
      action: 'eh_admin_get_question_groups_by_subject',
      nonce: getAdminNonce(),
      subject_id: subjectId
    }, function(res){
      if (res && res.success) {
        setSelectOptions($group, res.data, '-- Select (Optional) --');
      } else {
        resetSelect($group, '-- No groups --');
      }
    }).fail(function(){
      resetSelect($group, '-- Error --');
    });
  });

  $('#eh-pdf-upload-form').on('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'eh_import_pdf');

    $('#pdf-progress').show();
    $('#eh-pdf-submit').prop('disabled', true);
    updateProgress(10, 'Uploading file...');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      xhr: function(){
        const xhr = new window.XMLHttpRequest();
        xhr.upload.addEventListener('progress', function(e){
          if(e.lengthComputable){ updateProgress(Math.round(e.loaded/e.total*30)+10, 'Uploading...'); }
        });
        return xhr;
      },
      success: function(res){
        if(res.success){
          updateProgress(100, 'Done');
          renderExtractedQuestions(res.data.questions || []);
        } else {
          alert('Error: ' + (res.data?.message || 'Unexpected error'));
          resetForm();
        }
      },
      error: function(){ alert('Connection error'); resetForm(); }
    });

    let prog = 30;
    const interval = setInterval(function(){
      prog = Math.min(90, prog + Math.random()*5);
      const msgs = ['Processing text...','Parsing questions...','Preparing preview...','Almost done...'];
      updateProgress(prog, msgs[Math.floor(prog/25)] || msgs[0]);
    }, 1500);
    setTimeout(() => clearInterval(interval), 60000);
  });

  function updateProgress(pct, msg){
    $('#pdf-progress-bar').css('width', pct+'%');
    $('#pdf-status-text').text(msg);
  }

  function resetForm(){
    $('#eh-pdf-submit').prop('disabled', false);
    $('#pdf-progress').hide();
  }

  function renderExtractedQuestions(questions){
    $('#pdf-results').show();
    let html = `<p><strong>${questions.length} question(s) extracted</strong> - select what to save:</p>`;
    html += '<label style="margin-bottom:10px;display:block;"><input type="checkbox" id="select-all-q"> Select all</label>';

    questions.forEach(function(q, i){
      const typeLabel = {mcq:'MCQ',true_false:'True/False',fill_blank:'Fill Blank',essay:'Essay'}[q.type] || q.type || 'mcq';
      const diffLabel = {easy:'Easy',medium:'Medium',hard:'Hard'}[q.difficulty] || q.difficulty || 'medium';
      html += `
        <div class="eh-extracted-q" style="border:1px solid #ccc;border-radius:4px;padding:12px;margin:8px 0;background:#fff;">
          <label style="display:flex;align-items:flex-start;gap:10px;">
            <input type="checkbox" class="q-select" value="${i}" checked style="margin-top:3px;flex-shrink:0;">
            <div style="flex:1;">
              <div style="font-weight:bold;margin-bottom:6px;">${q.question_text || ''}</div>
              <span style="background:#e5e7eb;padding:2px 8px;border-radius:3px;font-size:12px;">${typeLabel}</span>
              <span style="background:#fef3c7;padding:2px 8px;border-radius:3px;font-size:12px;margin-right:4px;">${diffLabel}</span>
              ${q.answers ? `<div style="margin-top:6px;font-size:13px;">${q.answers.map((a,j)=>`<span style="margin-left:8px;">${a.is_correct?'?':''}${j+1}. ${a.answer_text||a}</span>`).join('')}</div>` : ''}
            </div>
          </label>
        </div>`;
    });

    $('#extracted-questions-list').html(html);
    $('#save-all-questions').show().data('questions', questions);

    $('#select-all-q').on('change', function(){
      $('.q-select').prop('checked', this.checked);
    });
  }

  $('#save-all-questions').on('click', function(){
    const questions    = $(this).data('questions') || [];
    const selected_idx = $('.q-select:checked').map((_,el) => parseInt(el.value, 10)).get();
    const to_save      = selected_idx.map(i => questions[i]);

    if(!to_save.length){ alert('Select at least one question first'); return; }

    $(this).prop('disabled', true).text('Saving...');

    const extra = {
      stage_id:   $('#pdf-stage').val(),
      grade_id:   $('#pdf-grade').val(),
      subject_id: $('#pdf-subject').val(),
      question_group_id: $('#pdf-group').val(),
      book_source: $('select[name=book_source]').val(),
      education_system: $('#pdf-edu-system').val(),
    };

    $.post(ajaxurl, {
      action: 'eh_save_imported_questions',
      nonce: $('input[name=nonce]').val(),
      questions: JSON.stringify(to_save),
      extra: JSON.stringify(extra),
    }, function(res){
      if(res.success){
        alert(`Saved ${res.data.saved} question(s) successfully`);
        window.location.href = '?page=examhub-review-questions';
      } else {
        alert('Error: ' + (res.data?.message || ''));
        $('#save-all-questions').prop('disabled', false).text('?? Save selected questions');
      }
    });
  });
});
</script>
    <?php
}

function examhub_render_review_page() {
    ?>
    <div class="wrap">
      <h1><?php esc_html_e( '✅ مراجعة الأسئلة المستوردة', 'examhub' ); ?></h1>
      <?php
      $pending = get_posts( [
          'post_type'      => 'eh_question',
          'posts_per_page' => 50,
          'meta_query'     => [ [ 'key' => 'ai_generated', 'value' => 1 ] ],
          'post_status'    => 'draft',
      ] );
      if ( empty( $pending ) ) {
          echo '<p>' . esc_html__( 'لا توجد أسئلة معلقة للمراجعة.', 'examhub' ) . '</p>';
          return;
      }
      echo '<p>' . sprintf( esc_html__( '%d سؤال معلق للمراجعة.', 'examhub' ), count( $pending ) ) . '</p>';
      echo '<table class="wp-list-table widefat striped"><thead><tr>';
      echo '<th>السؤال</th><th>المادة</th><th>الصعوبة</th><th>الحالة</th><th>إجراءات</th>';
      echo '</tr></thead><tbody>';
      foreach ( $pending as $q ) {
          $subject_id = get_field( 'subject', $q->ID );
          $subject    = $subject_id ? get_the_title( $subject_id ) : '-';
          $difficulty = get_field( 'difficulty', $q->ID ) ?: '-';
          $text       = wp_trim_words( get_field( 'question_text', $q->ID ) ?: $q->post_title, 12 );
          echo "<tr>";
          echo "<td>{$text}</td><td>{$subject}</td><td>{$difficulty}</td><td>" . esc_html__('مسودة','examhub') . "</td>";
          echo "<td><a href='" . get_edit_post_link( $q->ID ) . "' class='button'>" . esc_html__('تعديل','examhub') . "</a> ";
          echo "<a href='" . wp_nonce_url( admin_url("post.php?post={$q->ID}&action=edit&publish=1"), "publish_{$q->ID}" ) . "' class='button button-primary' onclick='return confirm(\"نشر؟\")'>" . esc_html__('نشر','examhub') . "</a></td>";
          echo "</tr>";
      }
      echo '</tbody></table>';
      ?>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX: PDF IMPORT
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_import_pdf', 'examhub_ajax_import_pdf' );
function examhub_ajax_import_pdf() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    if ( empty( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => __( 'Upload failed.', 'examhub' ) ] );
    }

    $file = $_FILES['import_file'];
    if ( $file['size'] > 10 * MB_IN_BYTES ) {
        wp_send_json_error( [ 'message' => __( 'File size exceeds 10MB.', 'examhub' ) ] );
    }

    $ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'pdf', 'json' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'Unsupported file type. Use PDF or JSON.', 'examhub' ) ] );
    }

    $stage_id          = (int) ( $_POST['stage_id'] ?? 0 );
    $grade_id          = (int) ( $_POST['grade_id'] ?? 0 );
    $subject_id        = (int) ( $_POST['subject_id'] ?? 0 );
    $question_group_id = (int) ( $_POST['question_group_id'] ?? 0 );

    if ( 'json' === $ext ) {
        $json_raw = file_get_contents( $file['tmp_name'] );
        if ( ! is_string( $json_raw ) || '' === trim( $json_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'JSON file is empty or invalid.', 'examhub' ) ] );
        }

        $json = json_decode( $json_raw, true );
        if ( ! is_array( $json ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid JSON format.', 'examhub' ) ] );
        }

        $questions = examhub_parse_questions_from_import_json( $json );
        if ( empty( $questions ) ) {
            wp_send_json_error( [ 'message' => __( 'No valid questions found in JSON.', 'examhub' ) ] );
        }

        wp_send_json_success( [
            'questions'    => $questions,
            'text_preview' => '',
            'total'        => count( $questions ),
            'source_check' => [ 'passed' => true, 'matched' => count( $questions ), 'total' => count( $questions ), 'ratio' => 1 ],
        ] );
    }

    $text = examhub_extract_pdf_text( $file['tmp_name'] );
    if ( is_wp_error( $text ) || empty( $text ) ) {
        $msg = is_wp_error( $text ) ? $text->get_error_message() : __( 'Failed to extract text from PDF.', 'examhub' );
        wp_send_json_error( [ 'message' => $msg ] );
    }

    $text = examhub_clean_ocr_text( $text );

    $context = [
        'stage'   => $stage_id ? get_the_title( $stage_id ) : '',
        'grade'   => $grade_id ? get_the_title( $grade_id ) : '',
        'subject' => $subject_id ? get_the_title( $subject_id ) : '',
        'lesson'  => $question_group_id ? get_the_title( $question_group_id ) : '',
    ];

    $questions = examhub_ai_extract_questions( $text, $context );
    if ( is_wp_error( $questions ) ) {
        wp_send_json_error( [ 'message' => $questions->get_error_message() ] );
    }

    $source_check = examhub_validate_questions_against_source_text( $questions, $text );
    if ( empty( $source_check['passed'] ) ) {
        wp_send_json_error( [
            'message' => __( 'Could not match extracted questions with uploaded PDF text.', 'examhub' ),
            'debug'   => $source_check,
        ] );
    }

    wp_send_json_success( [
        'questions'    => $questions,
        'text_preview' => substr( $text, 0, 500 ),
        'total'        => count( $questions ),
        'source_check' => $source_check,
    ] );
}

/**
 * Parse questions from import JSON (DeepSeek-style).
 *
 * @param array $json
 * @return array
 */
function examhub_parse_questions_from_import_json( $json ) {
    $all_blocks = [];

    if ( ! empty( $json['questions'] ) && is_array( $json['questions'] ) ) {
        $all_blocks = array_merge( $all_blocks, $json['questions'] );
    }

    if ( ! empty( $json['multiple_choice_answers_from_page4'] ) && is_array( $json['multiple_choice_answers_from_page4'] ) ) {
        $all_blocks = array_merge( $all_blocks, $json['multiple_choice_answers_from_page4'] );
    }

    $normalized = [];
    foreach ( $all_blocks as $q ) {
        if ( ! is_array( $q ) ) {
            continue;
        }

        $question_text = sanitize_textarea_field( $q['question_text'] ?? '' );
        if ( '' === trim( $question_text ) ) {
            continue;
        }

        $type = sanitize_text_field( $q['type'] ?? '' );
        $answers = [];
        $correct = $q['correct_answer'] ?? '';

        if ( empty( $type ) ) {
            $type = ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) ? 'mcq' : 'essay';
        }

        if ( 'calculation' === $type || 'explanation' === $type ) {
            $type = 'essay';
        }

        if ( 'mcq' === $type && ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
            foreach ( $q['options'] as $opt ) {
                $opt_text = sanitize_text_field( is_string( $opt ) ? $opt : '' );
                if ( '' === $opt_text ) {
                    continue;
                }
                $is_correct = ( is_string( $correct ) && $opt_text === sanitize_text_field( $correct ) );
                $answers[] = [
                    'answer_text' => $opt_text,
                    'is_correct'  => $is_correct,
                ];
            }
        }

        $normalized[] = [
            'question_text' => $question_text,
            'type'          => $type,
            'difficulty'    => 'medium',
            'answers'       => $answers,
            'correct_answer'=> is_string( $correct ) ? sanitize_text_field( $correct ) : '',
        ];
    }

    return $normalized;
}

/**
 * Extract text from PDF using pdftotext (if available) or fallback.
 */
function examhub_extract_pdf_text( $path ) {
    // 0) Preferred: send file to provider OCR API when enabled.
    if ( function_exists( 'examhub_ai_extract_pdf_text_remote' ) && get_field( 'ai_ocr_enabled', 'option' ) ) {
        $remote = examhub_ai_extract_pdf_text_remote( $path );
        if ( is_string( $remote ) && strlen( trim( $remote ) ) >= 40 ) {
            return $remote;
        }
        if ( is_wp_error( $remote ) ) {
            // Do not hide API errors behind local OCR fallback errors.
            return $remote;
        }
    }

    // Try pdftotext first (Linux/macOS + Windows).
    $output = [];
    $status = 1;
    $null   = ( 'Windows' === PHP_OS_FAMILY ) ? 'NUL' : '/dev/null';

    if ( 'Windows' === PHP_OS_FAMILY ) {
        $where = trim( (string) shell_exec( 'where pdftotext 2>NUL' ) );
        $bin   = $where ? preg_split( '/\r\n|\r|\n/', $where )[0] : 'pdftotext';
        $cmd   = sprintf(
            '%s -layout -enc UTF-8 %s - 2>%s',
            escapeshellarg( $bin ),
            escapeshellarg( $path ),
            $null
        );
        exec( $cmd, $output, $status );
    } else {
        $bin = trim( (string) shell_exec( 'command -v pdftotext 2>/dev/null' ) );
        if ( $bin ) {
            $cmd = sprintf(
                '%s -layout -enc UTF-8 %s - 2>%s',
                escapeshellarg( $bin ),
                escapeshellarg( $path ),
                $null
            );
            exec( $cmd, $output, $status );
        }
    }

    if ( 0 === $status ) {
        $extracted = trim( implode( "\n", $output ) );
        if ( '' !== $extracted ) {
            return $extracted;
        }
    }

    // Fallback: pure-PHP extraction from PDF content streams.
    $content = file_get_contents( $path );
    $text    = examhub_extract_text_from_pdf_streams( $content );
    if ( is_string( $text ) && strlen( trim( $text ) ) >= 120 ) {
        return $text;
    }

    // OCR fallback for scanned/image PDFs when tools are available.
    $ocr_text = examhub_extract_pdf_text_via_ocr( $path );
    if ( is_string( $ocr_text ) && strlen( trim( $ocr_text ) ) >= 40 ) {
        return $ocr_text;
    }

    if ( is_wp_error( $ocr_text ) ) {
        return $ocr_text;
    }

    return new WP_Error(
        'no_text',
        __( 'تعذر استخراج النص من الملف. يبدو أن الملف ممسوح ضوئيا/صوري ويحتاج OCR. ثبّت أدوات OCR على السيرفر: tesseract + pdftoppm (poppler) أو ImageMagick.', 'examhub' )
    );
}

/**
 * OCR fallback for scanned PDFs.
 *
 * @param string $path
 * @return string|WP_Error
 */
function examhub_extract_pdf_text_via_ocr( $path ) {
    $tesseract = examhub_find_binary( 'tesseract' );
    if ( ! $tesseract ) {
        return new WP_Error( 'ocr_missing_tesseract', __( 'تعذر استخراج النص: أداة OCR (tesseract) غير مثبتة على السيرفر.', 'examhub' ) );
    }

    $pdftoppm = examhub_find_binary( 'pdftoppm' );
    $magick   = examhub_find_binary( 'magick' );
    if ( ! $pdftoppm && ! $magick ) {
        return new WP_Error( 'ocr_missing_renderer', __( 'تعذر استخراج النص: لا يوجد pdftoppm أو ImageMagick لتحويل صفحات PDF إلى صور.', 'examhub' ) );
    }

    $tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'examhub-ocr-' . wp_generate_password( 8, false, false );
    if ( ! wp_mkdir_p( $tmp_dir ) ) {
        return new WP_Error( 'ocr_tmp', __( 'تعذر إنشاء مجلد مؤقت للـ OCR.', 'examhub' ) );
    }

    $prefix = $tmp_dir . DIRECTORY_SEPARATOR . 'page';
    $images = [];

    if ( $pdftoppm ) {
        // Render up to first 8 pages to keep response time reasonable.
        $cmd = sprintf(
            '%s -png -f 1 -l 8 %s %s 2>%s',
            escapeshellarg( $pdftoppm ),
            escapeshellarg( $path ),
            escapeshellarg( $prefix ),
            ( 'Windows' === PHP_OS_FAMILY ? 'NUL' : '/dev/null' )
        );
        @exec( $cmd );
        $images = glob( $prefix . '-*.png' ) ?: [];
    } elseif ( $magick ) {
        $out_pattern = $prefix . '-%d.png';
        $cmd = sprintf(
            '%s -density 220 %s[0-7] -quality 90 %s 2>%s',
            escapeshellarg( $magick ),
            escapeshellarg( $path ),
            escapeshellarg( $out_pattern ),
            ( 'Windows' === PHP_OS_FAMILY ? 'NUL' : '/dev/null' )
        );
        @exec( $cmd );
        $images = glob( $prefix . '-*.png' ) ?: [];
    }

    if ( empty( $images ) ) {
        examhub_cleanup_temp_dir( $tmp_dir );
        return new WP_Error( 'ocr_no_images', __( 'تعذر تجهيز صور الصفحات للـ OCR.', 'examhub' ) );
    }

    natsort( $images );
    $text = '';
    foreach ( $images as $img ) {
        $cmd = sprintf(
            '%s %s stdout -l ara+eng --psm 6 2>%s',
            escapeshellarg( $tesseract ),
            escapeshellarg( $img ),
            ( 'Windows' === PHP_OS_FAMILY ? 'NUL' : '/dev/null' )
        );
        $page_out = [];
        @exec( $cmd, $page_out );
        if ( ! empty( $page_out ) ) {
            $text .= "\n" . implode( "\n", $page_out );
        }
    }

    examhub_cleanup_temp_dir( $tmp_dir );
    return trim( $text );
}

/**
 * Find executable path across OSes.
 *
 * @param string $binary
 * @return string
 */
function examhub_find_binary( $binary ) {
    $null = ( 'Windows' === PHP_OS_FAMILY ) ? 'NUL' : '/dev/null';
    $cmd  = ( 'Windows' === PHP_OS_FAMILY ) ? "where {$binary} 2>{$null}" : "command -v {$binary} 2>{$null}";
    $out  = trim( (string) shell_exec( $cmd ) );
    if ( '' === $out ) {
        // Windows fallback: try common install locations even if PATH is not set.
        if ( 'Windows' === PHP_OS_FAMILY ) {
            $candidates = [];
            if ( 'tesseract' === strtolower( $binary ) ) {
                $candidates = [
                    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                ];
            } elseif ( 'pdftoppm' === strtolower( $binary ) ) {
                $candidates = [
                    'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
                    'C:\\Program Files\\poppler\\bin\\pdftoppm.exe',
                    'C:\\poppler\\Library\\bin\\pdftoppm.exe',
                    'C:\\poppler\\bin\\pdftoppm.exe',
                ];
            } elseif ( 'magick' === strtolower( $binary ) ) {
                $candidates = [
                    'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
                    'C:\\Program Files\\ImageMagick-7.1.0-Q16-HDRI\\magick.exe',
                    'C:\\Program Files\\ImageMagick-7.0.11-Q16\\magick.exe',
                ];
            }

            foreach ( $candidates as $candidate ) {
                if ( file_exists( $candidate ) ) {
                    return $candidate;
                }
            }
        }

        return '';
    }
    $lines = preg_split( '/\r\n|\r|\n/', $out );
    return trim( (string) ( $lines[0] ?? '' ) );
}

/**
 * Recursively cleanup OCR temp folder.
 *
 * @param string $dir
 * @return void
 */
function examhub_cleanup_temp_dir( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    foreach ( glob( $dir . DIRECTORY_SEPARATOR . '*' ) as $f ) {
        if ( is_file( $f ) ) {
            @unlink( $f );
        }
    }
    @rmdir( $dir );
}

/**
 * Extract text from PDF streams (handles common FlateDecode streams and Tj/TJ operators).
 *
 * @param string $pdf_content
 * @return string
 */
function examhub_extract_text_from_pdf_streams( $pdf_content ) {
    if ( ! is_string( $pdf_content ) || '' === $pdf_content ) {
        return '';
    }

    $chunks = [];

    // 1) Parse stream blocks and try to decode them.
    if ( preg_match_all( '/(?:<<[\s\S]*?>>\s*)stream[\r\n]+([\s\S]*?)endstream/s', $pdf_content, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $match ) {
            $raw = $match[1];
            $raw = preg_replace( "/^\r?\n/", '', $raw );
            $raw = preg_replace( "/\r?\n$/", '', $raw );

            $decoded = examhub_try_decode_pdf_stream( $raw );
            if ( is_string( $decoded ) && '' !== $decoded ) {
                $chunks[] = $decoded;
            }
            $chunks[] = $raw;
        }
    }

    // 2) Also parse the raw PDF body as a fallback source.
    $chunks[] = $pdf_content;

    $text_parts = [];
    foreach ( $chunks as $chunk ) {
        $t = examhub_extract_text_from_pdf_chunk( $chunk );
        if ( '' !== $t ) {
            $text_parts[] = $t;
        }
    }

    $text = trim( implode( "\n", $text_parts ) );
    $text = preg_replace( '/\n{3,}/', "\n\n", $text );
    return trim( (string) $text );
}

/**
 * Try decoding a PDF stream payload.
 *
 * @param string $raw
 * @return string
 */
function examhub_try_decode_pdf_stream( $raw ) {
    if ( ! is_string( $raw ) || '' === $raw ) {
        return '';
    }

    // Already readable.
    if ( preg_match( '/BT[\s\S]*?ET/', $raw ) ) {
        return $raw;
    }

    // Try zlib decode variants for Flate streams.
    $try = @gzuncompress( $raw );
    if ( false !== $try && '' !== $try ) {
        return $try;
    }

    $try = @gzinflate( $raw );
    if ( false !== $try && '' !== $try ) {
        return $try;
    }

    $try = @gzdecode( $raw );
    if ( false !== $try && '' !== $try ) {
        return $try;
    }

    return '';
}

/**
 * Extract text operands from a decoded PDF chunk.
 *
 * @param string $chunk
 * @return string
 */
function examhub_extract_text_from_pdf_chunk( $chunk ) {
    if ( ! is_string( $chunk ) || '' === $chunk ) {
        return '';
    }

    $result = [];

    // Text blocks between BT ... ET.
    if ( preg_match_all( '/BT([\s\S]*?)ET/', $chunk, $blocks ) ) {
        foreach ( $blocks[1] as $block ) {
            $result[] = examhub_extract_operands_from_text_block( $block );
        }
    } else {
        $result[] = examhub_extract_operands_from_text_block( $chunk );
    }

    $text = trim( implode( "\n", array_filter( $result ) ) );
    $text = preg_replace( '/[ \t]{2,}/', ' ', $text );
    return trim( (string) $text );
}

/**
 * Parse Tj / TJ operators and decode text strings.
 *
 * @param string $block
 * @return string
 */
function examhub_extract_operands_from_text_block( $block ) {
    $out = [];

    // Literal strings: (...) Tj
    if ( preg_match_all( '/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s', $block, $m1 ) ) {
        foreach ( $m1[1] as $str ) {
            $out[] = examhub_decode_pdf_literal_string( $str );
        }
    }

    // Hex strings: <...> Tj
    if ( preg_match_all( '/<([0-9A-Fa-f\s]+)>\s*Tj/s', $block, $m2 ) ) {
        foreach ( $m2[1] as $hex ) {
            $out[] = examhub_decode_pdf_hex_string( $hex );
        }
    }

    // Arrays for TJ: [ ... ] TJ
    if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $block, $m3 ) ) {
        foreach ( $m3[1] as $arr ) {
            if ( preg_match_all( '/\(((?:\\\\.|[^\\\\)])*)\)|<([0-9A-Fa-f\s]+)>/s', $arr, $parts, PREG_SET_ORDER ) ) {
                foreach ( $parts as $p ) {
                    if ( isset( $p[1] ) && '' !== $p[1] ) {
                        $out[] = examhub_decode_pdf_literal_string( $p[1] );
                    } elseif ( isset( $p[2] ) && '' !== $p[2] ) {
                        $out[] = examhub_decode_pdf_hex_string( $p[2] );
                    }
                }
            }
        }
    }

    $text = trim( implode( ' ', array_filter( $out ) ) );
    $text = preg_replace( '/\s{2,}/', ' ', $text );
    return trim( (string) $text );
}

/**
 * Decode PDF literal string escapes.
 *
 * @param string $str
 * @return string
 */
function examhub_decode_pdf_literal_string( $str ) {
    $str = preg_replace( '/\\\\([\\\\()])/', '$1', $str );
    $str = str_replace( [ '\n', '\r', '\t', '\b', '\f' ], [ "\n", ' ', ' ', '', '' ], $str );
    $str = preg_replace( '/\\\\[0-7]{1,3}/', ' ', $str ); // octal escapes (rare).
    return trim( (string) $str );
}

/**
 * Decode PDF hex string into UTF-8 text when possible.
 *
 * @param string $hex
 * @return string
 */
function examhub_decode_pdf_hex_string( $hex ) {
    $hex = preg_replace( '/\s+/', '', $hex );
    if ( '' === $hex ) {
        return '';
    }
    if ( strlen( $hex ) % 2 !== 0 ) {
        $hex .= '0';
    }

    $bin = @hex2bin( $hex );
    if ( false === $bin || '' === $bin ) {
        return '';
    }

    // UTF-16 BOM.
    if ( 0 === strpos( $bin, "\xFE\xFF" ) || 0 === strpos( $bin, "\xFF\xFE" ) ) {
        $utf8 = function_exists( 'mb_convert_encoding' )
            ? @mb_convert_encoding( $bin, 'UTF-8', 'UTF-16' )
            : @iconv( 'UTF-16', 'UTF-8//IGNORE', $bin );
        if ( is_string( $utf8 ) && '' !== $utf8 ) {
            return trim( $utf8 );
        }
    }

    // Heuristic for UTF-16BE without BOM.
    if ( substr_count( $bin, "\x00" ) > strlen( $bin ) / 4 ) {
        $utf8 = function_exists( 'mb_convert_encoding' )
            ? @mb_convert_encoding( $bin, 'UTF-8', 'UTF-16BE' )
            : @iconv( 'UTF-16BE', 'UTF-8//IGNORE', $bin );
        if ( is_string( $utf8 ) && '' !== $utf8 ) {
            return trim( $utf8 );
        }
    }

    // Fallback: treat as UTF-8/latin bytes.
    if ( function_exists( 'mb_convert_encoding' ) ) {
        return trim( @mb_convert_encoding( $bin, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252' ) );
    }

    $utf8 = @iconv( 'ISO-8859-1', 'UTF-8//IGNORE', $bin );
    return trim( is_string( $utf8 ) ? $utf8 : $bin );
}

/**
 * Clean common OCR / PDF extraction artifacts.
 */
function examhub_clean_ocr_text( $text ) {
    // Remove PDF artifacts
    $text = preg_replace( '/[^\p{L}\p{N}\s\.\،\؟\!\:\-\(\)\[\]\/\|]/u', ' ', $text );
    // Normalize whitespace
    $text = preg_replace( '/\s{3,}/', "\n", $text );
    // Fix common Arabic OCR errors
    $fixes = [
        'ا\s*ل\s*/' => 'ال',
        '\s+([،\.])' => '$1',
    ];
    foreach ( $fixes as $pattern => $replace ) {
        $text = preg_replace( '/' . $pattern . '/u', $replace, $text );
    }
    return trim( $text );
}

/**
 * Validate that AI output is grounded in extracted source text.
 *
 * @param array  $questions
 * @param string $source_text
 * @return array
 */
function examhub_validate_questions_against_source_text( $questions, $source_text ) {
    if ( ! is_array( $questions ) || empty( $questions ) ) {
        return [ 'passed' => false, 'matched' => 0, 'total' => 0, 'ratio' => 0 ];
    }

    $source_norm = examhub_normalize_text_for_match( $source_text, true );
    $source_len = function_exists( 'mb_strlen' ) ? mb_strlen( $source_norm, 'UTF-8' ) : strlen( $source_norm );
    if ( '' === $source_norm || $source_len < 120 ) {
        return [ 'passed' => false, 'matched' => 0, 'total' => count( $questions ), 'ratio' => 0 ];
    }

    $matched = 0;
    $total   = 0;

    foreach ( $questions as $q ) {
        if ( ! is_array( $q ) ) {
            continue;
        }

        $q_text = (string) ( $q['question_text'] ?? '' );
        $q_norm = examhub_normalize_text_for_match( $q_text, true );
        $q_len = function_exists( 'mb_strlen' ) ? mb_strlen( $q_norm, 'UTF-8' ) : strlen( $q_norm );
        if ( '' === $q_norm || $q_len < 8 ) {
            continue;
        }

        $total++;
        if ( examhub_is_question_present_in_source( $q_norm, $source_norm ) ) {
            $matched++;
        }
    }

    if ( $total < 2 ) {
        return [ 'passed' => false, 'matched' => $matched, 'total' => $total, 'ratio' => 0 ];
    }

    $ratio = $matched / $total;
    $min_required = max( 1, (int) ceil( $total * 0.15 ) );
    return [
        'passed'  => ( $matched >= $min_required ),
        'matched' => $matched,
        'total'   => $total,
        'ratio'   => round( $ratio, 3 ),
    ];
}

/**
 * Normalize text for simple source matching.
 *
 * @param string $text
 * @return string
 */
function examhub_normalize_text_for_match( $text, $keep_spaces = false ) {
    $text = wp_strip_all_tags( (string) $text );
    $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

    if ( $keep_spaces ) {
        $text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
        $text = preg_replace( '/\s+/u', ' ', $text );
        return trim( (string) $text );
    }

    $text = preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
    return trim( (string) $text );
}

/**
 * Check whether a normalized question likely exists in normalized source text.
 *
 * @param string $q_norm
 * @param string $source_norm
 * @return bool
 */
function examhub_is_question_present_in_source( $q_norm, $source_norm ) {
    $str_sub = static function( $s, $start, $len ) {
        return function_exists( 'mb_substr' ) ? mb_substr( $s, $start, $len, 'UTF-8' ) : substr( $s, $start, $len );
    };
    $str_pos = static function( $hay, $needle ) {
        return function_exists( 'mb_strpos' ) ? mb_strpos( $hay, $needle, 0, 'UTF-8' ) : strpos( $hay, $needle );
    };
    // Direct phrase check on first meaningful segment.
    $probe = $str_sub( $q_norm, 0, 22 );
    if ( '' !== $probe && false !== $str_pos( $source_norm, $probe ) ) {
        return true;
    }

    // Token overlap check for paraphrase/noise tolerance.
    $tokens = preg_split( '/\s+/u', $q_norm, -1, PREG_SPLIT_NO_EMPTY );
    $tokens = array_values( array_filter( $tokens, static function( $t ) {
        return ( function_exists( 'mb_strlen' ) ? mb_strlen( $t, 'UTF-8' ) : strlen( $t ) ) >= 3;
    } ) );

    if ( count( $tokens ) < 3 ) {
        return false;
    }

    // Pick up to 5 strongest tokens.
    usort( $tokens, static function( $a, $b ) {
        $lb = function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) : strlen( $b );
        $la = function_exists( 'mb_strlen' ) ? mb_strlen( $a, 'UTF-8' ) : strlen( $a );
        return $lb - $la;
    } );
    $tokens = array_slice( array_unique( $tokens ), 0, 5 );

    $hits = 0;
    foreach ( $tokens as $tok ) {
        if ( false !== $str_pos( $source_norm, $tok ) ) {
            $hits++;
        }
    }

    return $hits >= 2;
}

add_action( 'wp_ajax_eh_admin_get_stages_by_system', 'examhub_ajax_admin_get_stages_by_system' );
function examhub_ajax_admin_get_stages_by_system() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $system_id = (int) ( $_POST['system_id'] ?? 0 );
    if ( ! $system_id ) {
        wp_send_json_success( [] );
    }

    $stages = get_posts( [
        'post_type'      => 'eh_stage',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'stage_education_system',
                'value'   => $system_id,
                'compare' => '=',
            ],
        ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $data = [];
    foreach ( $stages as $stage ) {
        $data[] = [
            'id'    => $stage->ID,
            'label' => get_field( 'stage_name_ar', $stage->ID ) ?: $stage->post_title,
        ];
    }

    wp_send_json_success( $data );
}

function examhub_import_collect_question_blocks( $json ) {
    if ( isset( $json['questions'] ) && is_array( $json['questions'] ) ) {
        return $json['questions'];
    }

    if ( array_keys( $json ) === range( 0, count( $json ) - 1 ) ) {
        return $json;
    }

    return [];
}

function examhub_import_normalize_type( $value, $fallback = 'mcq' ) {
    $value = sanitize_key( (string) $value );
    $map = [
        'mcq' => 'mcq',
        'multiple_choice' => 'mcq',
        'multiple-choice' => 'mcq',
        'choice' => 'mcq',
        'true_false' => 'true_false',
        'truefalse' => 'true_false',
        'tf' => 'true_false',
        'fill_blank' => 'fill_blank',
        'fill-in-the-blank' => 'fill_blank',
        'matching' => 'matching',
        'ordering' => 'ordering',
        'essay' => 'essay',
        'correct' => 'correct',
        'math' => 'math',
    ];

    return $map[ $value ] ?? $fallback;
}

function examhub_import_normalize_difficulty( $value ) {
    $value = trim( (string) $value );
    $normalized = [
        'easy'   => 'easy',
        'medium' => 'medium',
        'hard'   => 'hard',
        'سهل'    => 'easy',
        'متوسط'  => 'medium',
        'صعب'    => 'hard',
    ];

    return $normalized[ $value ] ?? 'medium';
}

function examhub_import_build_answers_from_options( $question ) {
    $answers = [];
    $options = $question['options'] ?? [];
    $correct_answer = $question['correct_answer'] ?? '';
    $correct_index  = isset( $question['correct_answer_index'] ) ? (int) $question['correct_answer_index'] : null;

    if ( ! is_array( $options ) ) {
        return [];
    }

    foreach ( $options as $index => $option ) {
        $answer_text = sanitize_text_field( is_string( $option ) ? $option : '' );
        if ( '' === $answer_text ) {
            continue;
        }

        $is_correct = false;
        if ( null !== $correct_index ) {
            $is_correct = ( $index === $correct_index );
        } elseif ( is_string( $correct_answer ) ) {
            $is_correct = ( $answer_text === sanitize_text_field( $correct_answer ) );
        }

        $answers[] = [
            'answer_text' => $answer_text,
            'is_correct'  => $is_correct,
        ];
    }

    return $answers;
}

function examhub_import_normalize_answers( $question, $type ) {
    $answers = [];
    if ( ! empty( $question['answers'] ) && is_array( $question['answers'] ) ) {
        foreach ( $question['answers'] as $answer ) {
            if ( is_array( $answer ) ) {
                $text = sanitize_text_field( (string) ( $answer['answer_text'] ?? $answer['text'] ?? '' ) );
                if ( '' === $text ) {
                    continue;
                }
                $answers[] = [
                    'answer_text' => $text,
                    'is_correct'  => ! empty( $answer['is_correct'] ),
                ];
            } elseif ( is_string( $answer ) ) {
                $text = sanitize_text_field( $answer );
                if ( '' === $text ) {
                    continue;
                }
                $answers[] = [
                    'answer_text' => $text,
                    'is_correct'  => false,
                ];
            }
        }
    } elseif ( in_array( $type, [ 'mcq', 'correct' ], true ) ) {
        $answers = examhub_import_build_answers_from_options( $question );
    }

    return $answers;
}

function examhub_import_map_question( $question, $index ) {
    $question_text = sanitize_textarea_field( (string) ( $question['question_text'] ?? $question['question'] ?? '' ) );
    if ( '' === trim( $question_text ) ) {
        return [
            'valid'  => false,
            'index'  => $index,
            'reason' => 'نص السؤال غير موجود.',
        ];
    }

    $type = examhub_import_normalize_type(
        $question['type'] ?? ( ! empty( $question['options'] ) ? 'mcq' : 'essay' )
    );
    $difficulty = examhub_import_normalize_difficulty( $question['difficulty'] ?? $question['level'] ?? 'medium' );
    $answers = examhub_import_normalize_answers( $question, $type );
    $warnings = [];

    if ( in_array( $type, [ 'mcq', 'correct' ], true ) ) {
        if ( count( $answers ) < 2 ) {
            return [
                'valid'  => false,
                'index'  => $index,
                'reason' => 'سؤال الاختيار من متعدد يحتاج خيارين على الأقل.',
            ];
        }

        $correct_count = count( array_filter( $answers, static function( $answer ) {
            return ! empty( $answer['is_correct'] );
        } ) );

        if ( 1 !== $correct_count ) {
            return [
                'valid'  => false,
                'index'  => $index,
                'reason' => 'يجب تحديد إجابة صحيحة واحدة فقط.',
            ];
        }
    }

    $mapped = [
        'external_id'       => sanitize_text_field( (string) ( $question['external_id'] ?? '' ) ),
        'type'              => $type,
        'question_text'     => $question_text,
        'body'              => wp_kses_post( (string) ( $question['body'] ?? $question['question_body'] ?? $question['content'] ?? $question['body_html'] ?? '' ) ),
        'difficulty'        => $difficulty,
        'answers'           => $answers,
        'correct_answer'    => sanitize_text_field( (string) ( $question['correct_answer'] ?? '' ) ),
        'explanation'       => wp_kses_post( (string) ( $question['explanation'] ?? '' ) ),
        'question_points'   => max( 1, (int) ( $question['question_points'] ?? 1 ) ),
        'question_tags'     => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $question['question_tags'] ?? [] ) ) ) ),
        'academic_year'     => sanitize_text_field( (string) ( $question['academic_year'] ?? '' ) ),
        'blank_answer'      => sanitize_text_field( (string) ( $question['blank_answer'] ?? $question['correct_answer'] ?? '' ) ),
        'tf_correct_answer' => sanitize_text_field( (string) ( $question['tf_correct_answer'] ?? $question['correct_answer'] ?? '' ) ),
        '_warnings'         => $warnings,
    ];

    return [
        'valid'    => true,
        'question' => $mapped,
    ];
}

function examhub_prepare_questions_from_import_json( $json ) {
    $blocks = examhub_import_collect_question_blocks( $json );
    $questions = [];
    $invalid = [];

    foreach ( $blocks as $index => $question ) {
        if ( ! is_array( $question ) ) {
            $invalid[] = [
                'index'  => $index + 1,
                'reason' => 'العنصر ليس كائن JSON صالحًا.',
            ];
            continue;
        }

        $mapped = examhub_import_map_question( $question, $index + 1 );
        if ( empty( $mapped['valid'] ) ) {
            $invalid[] = [
                'index'  => $mapped['index'] ?? ( $index + 1 ),
                'reason' => $mapped['reason'] ?? 'بيانات غير مكتملة.',
            ];
            continue;
        }

        $questions[] = $mapped['question'];
    }

    return [
        'questions' => $questions,
        'invalid'   => $invalid,
        'total'     => count( $blocks ),
    ];
}

function examhub_insert_imported_question( $question, $extra ) {
    $post_id = wp_insert_post( [
        'post_type'   => 'eh_question',
        'post_title'  => wp_trim_words( $question['question_text'], 8 ),
        'post_content'=> (string) ( $question['body'] ?? '' ),
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
    ] );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    update_field( 'question_text', $question['question_text'], $post_id );
    update_field( 'question_type', $question['type'], $post_id );
    update_field( 'difficulty', $question['difficulty'], $post_id );
    update_field( 'ai_generated', 0, $post_id );
    update_field( 'question_points', max( 1, (int) ( $question['question_points'] ?? 1 ) ), $post_id );

    if ( ! empty( $question['explanation'] ) ) {
        update_field( 'explanation', $question['explanation'], $post_id );
    }
    if ( ! empty( $question['academic_year'] ) ) {
        update_field( 'academic_year', $question['academic_year'], $post_id );
    }
    if ( ! empty( $question['question_tags'] ) ) {
        update_field( 'question_tags', implode( ', ', (array) $question['question_tags'] ), $post_id );
    }

    if ( ! empty( $extra['education_system'] ) ) {
        update_field( 'education_system', (int) $extra['education_system'], $post_id );
    }
    if ( ! empty( $extra['stage_id'] ) ) {
        update_field( 'stage', (int) $extra['stage_id'], $post_id );
    }
    if ( ! empty( $extra['grade_id'] ) ) {
        update_field( 'grade', (int) $extra['grade_id'], $post_id );
    }
    if ( ! empty( $extra['subject_id'] ) ) {
        update_field( 'subject', (int) $extra['subject_id'], $post_id );
    }
    if ( ! empty( $extra['question_group_id'] ) ) {
        update_field( 'lesson', (int) $extra['question_group_id'], $post_id );
    }
    if ( ! empty( $extra['book_source'] ) ) {
        update_field( 'book_source', sanitize_text_field( $extra['book_source'] ), $post_id );
    }

    if ( in_array( $question['type'], [ 'mcq', 'correct' ], true ) && ! empty( $question['answers'] ) ) {
        $answers = [];
        foreach ( $question['answers'] as $answer ) {
            $answers[] = [
                'answer_text'  => sanitize_text_field( $answer['answer_text'] ?? '' ),
                'answer_image' => '',
                'is_correct'   => ! empty( $answer['is_correct'] ),
            ];
        }
        update_field( 'answers', $answers, $post_id );
    } elseif ( 'true_false' === $question['type'] ) {
        update_field( 'tf_correct_answer', $question['tf_correct_answer'] ?: 'true', $post_id );
    } elseif ( in_array( $question['type'], [ 'fill_blank', 'math' ], true ) ) {
        update_field( 'blank_answer', $question['blank_answer'], $post_id );
    }

    if ( ! empty( $question['external_id'] ) ) {
        update_post_meta( $post_id, '_eh_import_external_id', $question['external_id'] );
    }

    return $post_id;
}

function examhub_get_group_question_ids( $group_id ) {
    return get_posts( [
        'post_type'      => 'eh_question',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'lesson',
                'value'   => (int) $group_id,
                'compare' => '=',
            ],
        ],
    ] );
}

function examhub_validate_auto_exam_request() {
    $payload = [
        'education_system'   => (int) ( $_POST['education_system'] ?? 0 ),
        'stage_id'           => (int) ( $_POST['stage_id'] ?? 0 ),
        'grade_id'           => (int) ( $_POST['grade_id'] ?? 0 ),
        'subject_id'         => (int) ( $_POST['subject_id'] ?? 0 ),
        'question_group_id'  => (int) ( $_POST['question_group_id'] ?? 0 ),
        'base_title'         => sanitize_text_field( wp_unslash( $_POST['base_title'] ?? '' ) ),
        'exam_count'         => max( 1, (int) ( $_POST['exam_count'] ?? 0 ) ),
        'questions_per_exam' => max( 1, (int) ( $_POST['questions_per_exam'] ?? 0 ) ),
        'random_questions'   => ! empty( $_POST['random_questions'] ),
        'random_answers'     => ! empty( $_POST['random_answers'] ),
    ];

    if ( ! $payload['question_group_id'] ) {
        return new WP_Error( 'missing_group', 'يرجى اختيار مجموعة الأسئلة.' );
    }
    if ( ! $payload['stage_id'] ) {
        return new WP_Error( 'missing_stage', 'يرجى اختيار المرحلة.' );
    }
    if ( '' === $payload['base_title'] ) {
        return new WP_Error( 'missing_title', 'يرجى إدخال العنوان الأساسي.' );
    }

    return $payload;
}

add_action( 'wp_ajax_eh_import_json_preview', 'examhub_ajax_import_json_preview' );
function examhub_ajax_import_json_preview() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    if ( empty( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['import_file']['error'] ) {
        wp_send_json_error( [ 'message' => 'تعذر رفع الملف.' ] );
    }

    $file = $_FILES['import_file'];
    if ( $file['size'] > 10 * MB_IN_BYTES ) {
        wp_send_json_error( [ 'message' => 'حجم الملف أكبر من 10MB.' ] );
    }

    $ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
    if ( 'json' !== $ext ) {
        wp_send_json_error( [ 'message' => 'المسموح فقط ملفات JSON.' ] );
    }

    $json_raw = file_get_contents( $file['tmp_name'] );
    $json = json_decode( (string) $json_raw, true );
    if ( ! is_array( $json ) ) {
        wp_send_json_error( [ 'message' => 'صيغة JSON غير صحيحة.' ] );
    }

    $prepared = examhub_prepare_questions_from_import_json( $json );
    if ( empty( $prepared['questions'] ) ) {
        wp_send_json_error( [ 'message' => 'لم يتم العثور على أسئلة صالحة داخل الملف.' ] );
    }

    wp_send_json_success( $prepared );
}

add_action( 'wp_ajax_eh_import_json_publish', 'examhub_ajax_import_json_publish' );
function examhub_ajax_import_json_publish() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $questions = json_decode( wp_unslash( $_POST['questions'] ?? '[]' ), true );
    $extra = json_decode( wp_unslash( $_POST['extra'] ?? '{}' ), true );

    if ( ! is_array( $questions ) || empty( $questions ) ) {
        wp_send_json_error( [ 'message' => 'لا توجد أسئلة جاهزة للاستيراد.' ] );
    }

    $saved = 0;
    $failed = 0;
    foreach ( $questions as $question ) {
        $inserted = examhub_insert_imported_question( $question, is_array( $extra ) ? $extra : [] );
        if ( is_wp_error( $inserted ) ) {
            $failed++;
            continue;
        }
        $saved++;
    }

    wp_send_json_success( [
        'saved'   => $saved,
        'failed'  => $failed,
        'message' => sprintf( 'تم استيراد ونشر %d سؤال%s.', $saved, $failed ? "، وتعذر حفظ {$failed}" : '' ),
    ] );
}

add_action( 'wp_ajax_eh_preview_auto_exams', 'examhub_ajax_preview_auto_exams' );
function examhub_ajax_preview_auto_exams() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $payload = examhub_validate_auto_exam_request();
    if ( is_wp_error( $payload ) ) {
        wp_send_json_error( [ 'message' => $payload->get_error_message() ] );
    }

    $question_ids = examhub_get_group_question_ids( $payload['question_group_id'] );
    $available_questions = count( $question_ids );

    if ( $available_questions < $payload['questions_per_exam'] ) {
        wp_send_json_error( [ 'message' => 'عدد الأسئلة في المجموعة أقل من العدد المطلوب لكل امتحان.' ] );
    }

    $group_title = get_the_title( $payload['question_group_id'] );
    $sample_names = [];
    for ( $i = 1; $i <= min( 3, $payload['exam_count'] ); $i++ ) {
        $sample_names[] = trim( $payload['base_title'] . ' - ' . $group_title . ' ' . $i );
    }

    wp_send_json_success( [
        'available_questions' => $available_questions,
        'exam_count'          => $payload['exam_count'],
        'questions_per_exam'  => $payload['questions_per_exam'],
        'sample_names'        => $sample_names,
    ] );
}

add_action( 'wp_ajax_eh_create_auto_exams', 'examhub_ajax_create_auto_exams' );
function examhub_ajax_create_auto_exams() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $payload = examhub_validate_auto_exam_request();
    if ( is_wp_error( $payload ) ) {
        wp_send_json_error( [ 'message' => $payload->get_error_message() ] );
    }

    $question_ids = examhub_get_group_question_ids( $payload['question_group_id'] );
    if ( count( $question_ids ) < $payload['questions_per_exam'] ) {
        wp_send_json_error( [ 'message' => 'عدد الأسئلة في المجموعة أقل من العدد المطلوب لكل امتحان.' ] );
    }

    $created = 0;
    $group_title = get_the_title( $payload['question_group_id'] );

    for ( $i = 1; $i <= $payload['exam_count']; $i++ ) {
        $pool = $question_ids;
        shuffle( $pool );
        $selected_questions = array_slice( $pool, 0, $payload['questions_per_exam'] );

        $exam_id = wp_insert_post( [
            'post_type'   => 'eh_exam',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_title'  => trim( $payload['base_title'] . ' - ' . $group_title . ' ' . $i ),
        ] );

        if ( is_wp_error( $exam_id ) ) {
            continue;
        }

        if ( ! empty( $payload['education_system'] ) ) {
            update_field( 'exam_education_system', $payload['education_system'], $exam_id );
        }
        update_field( 'exam_stage', $payload['stage_id'], $exam_id );
        if ( ! empty( $payload['grade_id'] ) ) {
            update_field( 'exam_grade', $payload['grade_id'], $exam_id );
        }
        if ( ! empty( $payload['subject_id'] ) ) {
            update_field( 'exam_subject', $payload['subject_id'], $exam_id );
        }
        update_field( 'exam_lesson', $payload['question_group_id'], $exam_id );
        update_field( 'exam_questions', $selected_questions, $exam_id );
        update_field( 'timer_type', 'exam', $exam_id );
        update_field( 'exam_duration_minutes', 30, $exam_id );
        update_field( 'exam_access', 'free_limit', $exam_id );
        update_field( 'exam_type', 'standard', $exam_id );
        update_field( 'exam_difficulty', 'mixed', $exam_id );
        update_field( 'random_questions', $payload['random_questions'] ? 1 : 0, $exam_id );
        update_field( 'random_answers', $payload['random_answers'] ? 1 : 0, $exam_id );
        update_field( 'show_explanation', 1, $exam_id );
        update_field( 'allow_skip', 1, $exam_id );
        update_field( 'allow_mark_review', 1, $exam_id );
        update_field( 'allow_resume', 1, $exam_id );

        $created++;
    }

    wp_send_json_success( [
        'created' => $created,
        'message' => sprintf( 'تم إنشاء %d امتحان وحفظها كمسودات.', $created ),
    ] );
}

add_action( 'wp_ajax_eh_admin_get_grades_by_stage', 'examhub_ajax_admin_get_grades_by_stage' );
function examhub_ajax_admin_get_grades_by_stage() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $stage_id = (int) ( $_POST['stage_id'] ?? 0 );
    if ( ! $stage_id ) {
        wp_send_json_success( [] );
    }

    $grades = get_posts( [
        'post_type'      => 'eh_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'grade_stage',
                'value'   => $stage_id,
                'compare' => '=',
            ],
        ],
    ] );

    usort(
        $grades,
        static function( $a, $b ) {
            return (int) get_field( 'grade_number', $a->ID ) - (int) get_field( 'grade_number', $b->ID );
        }
    );

    $data = [];
    foreach ( $grades as $grade ) {
        $data[] = [
            'id'    => $grade->ID,
            'label' => get_field( 'grade_name_ar', $grade->ID ) ?: $grade->post_title,
        ];
    }

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_eh_admin_get_subjects_by_grade', 'examhub_ajax_admin_get_subjects_by_grade' );
function examhub_ajax_admin_get_subjects_by_grade() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $grade_id = (int) ( $_POST['grade_id'] ?? 0 );
    if ( ! $grade_id ) {
        wp_send_json_success( [] );
    }

    $subjects = get_posts( [
        'post_type'      => 'eh_subject',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'subject_grade',
                'value'   => $grade_id,
                'compare' => '=',
            ],
        ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $data = [];
    foreach ( $subjects as $subject ) {
        $data[] = [
            'id'    => $subject->ID,
            'label' => get_field( 'subject_name_ar', $subject->ID ) ?: $subject->post_title,
        ];
    }

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_eh_admin_get_question_groups_by_subject', 'examhub_ajax_admin_get_question_groups_by_subject' );
function examhub_ajax_admin_get_question_groups_by_subject() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) {
        wp_send_json_error( [], 403 );
    }

    $subject_id = (int) ( $_POST['subject_id'] ?? 0 );
    if ( ! $subject_id ) {
        wp_send_json_success( [] );
    }

    $groups = get_posts( [
        'post_type'      => 'eh_lesson',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'lesson_subject',
                'value'   => $subject_id,
                'compare' => '=',
            ],
        ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $data = [];
    foreach ( $groups as $group ) {
        $data[] = [
            'id'    => $group->ID,
            'label' => $group->post_title,
        ];
    }

    wp_send_json_success( $data );
}

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX: SAVE IMPORTED QUESTIONS
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_save_imported_questions', 'examhub_ajax_save_imported_questions' );
function examhub_ajax_save_imported_questions() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'examhub_import_content' ) ) wp_send_json_error( [], 403 );

    $questions_raw = wp_unslash( $_POST['questions'] ?? '[]' );
    $extra_raw     = wp_unslash( $_POST['extra'] ?? '{}' );

    $questions = json_decode( $questions_raw, true );
    $extra     = json_decode( $extra_raw, true );

    if ( ! is_array( $questions ) ) {
        wp_send_json_error( [ 'message' => 'بيانات غير صالحة.' ] );
    }

    $saved   = 0;
    $skipped = 0;

    foreach ( $questions as $q ) {
        $text = sanitize_textarea_field( $q['question_text'] ?? '' );
        if ( empty( $text ) ) { $skipped++; continue; }

        // Duplicate detection — simple title match
        $existing = get_page_by_title( wp_trim_words( $text, 5 ), OBJECT, 'eh_question' );
        if ( $existing ) { $skipped++; continue; }

        $type       = sanitize_text_field( $q['type'] ?? 'mcq' );
        $difficulty = sanitize_text_field( $q['difficulty'] ?? 'medium' );

        $post_id = wp_insert_post( [
            'post_type'   => 'eh_question',
            'post_title'  => wp_trim_words( $text, 8 ),
            'post_content'=> wp_kses_post( (string) ( $q['body'] ?? '' ) ),
            'post_status' => 'draft', // Requires manual review
            'post_author' => get_current_user_id(),
        ] );

        if ( is_wp_error( $post_id ) ) { $skipped++; continue; }

        // Core fields
        update_field( 'question_text', $text,       $post_id );
        update_field( 'question_type', $type,        $post_id );
        update_field( 'difficulty',    $difficulty,  $post_id );
        update_field( 'ai_generated',  1,            $post_id );

        // Classification from extra
        if ( ! empty( $extra['education_system'] ) ) update_field( 'education_system', (int)$extra['education_system'], $post_id );
        if ( ! empty( $extra['stage_id'] ) )         update_field( 'stage',            (int)$extra['stage_id'],         $post_id );
        if ( ! empty( $extra['grade_id'] ) )         update_field( 'grade',            (int)$extra['grade_id'],         $post_id );
        if ( ! empty( $extra['subject_id'] ) )       update_field( 'subject',          (int)$extra['subject_id'],       $post_id );
        if ( ! empty( $extra['question_group_id'] ) ) update_field( 'lesson',          (int)$extra['question_group_id'], $post_id );
        if ( ! empty( $extra['book_source'] ) )      update_field( 'book_source',       $extra['book_source'],           $post_id );

        // Answers for MCQ
        if ( in_array( $type, [ 'mcq', 'correct', 'image' ] ) && ! empty( $q['answers'] ) ) {
            $answers_acf = [];
            foreach ( $q['answers'] as $a ) {
                $answers_acf[] = [
                    'answer_text' => sanitize_text_field( $a['answer_text'] ?? ( is_string($a) ? $a : '' ) ),
                    'is_correct'  => ! empty( $a['is_correct'] ),
                    'answer_image'=> '',
                ];
            }
            update_field( 'answers', $answers_acf, $post_id );
        }

        // Correct index mapping
        if ( ! empty( $q['correct_answer_index'] ) && in_array( $type, ['mcq','correct'] ) ) {
            // Ensure the correct answer is marked
        }

        // TF answer
        if ( $type === 'true_false' && ! empty( $q['correct_answer'] ) ) {
            update_field( 'tf_correct_answer', $q['correct_answer'], $post_id );
        }

        // Fill blank
        if ( $type === 'fill_blank' && ! empty( $q['correct_answer'] ) ) {
            update_field( 'blank_answer', sanitize_text_field( $q['correct_answer'] ), $post_id );
        }

        // Explanation
        if ( ! empty( $q['explanation'] ) ) {
            update_field( 'explanation', wp_kses_post( $q['explanation'] ), $post_id );
        }

        $saved++;
    }

    wp_send_json_success( [
        'saved'   => $saved,
        'skipped' => $skipped,
        'message' => sprintf( __( 'تم حفظ %d سؤال. تم تخطي %d (مكررة أو فارغة).', 'examhub' ), $saved, $skipped ),
    ] );
}


