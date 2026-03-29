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
    add_submenu_page(
        'examhub-content',
        __( 'استيراد PDF', 'examhub' ),
        __( '📄 استيراد PDF', 'examhub' ),
        'manage_options',
        'examhub-pdf-import',
        'examhub_render_pdf_import_page'
    );

    add_submenu_page(
        'examhub-content',
        __( 'بنك الأسئلة — مراجعة', 'examhub' ),
        __( '✅ مراجعة المستورَد', 'examhub' ),
        'manage_options',
        'examhub-review-questions',
        'examhub_render_review_page'
    );
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
                <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required class="regular-text">
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
              <th><?php esc_html_e( 'الدرس (اختياري)', 'examhub' ); ?></th>
              <td><input type="text" name="lesson_hint" class="regular-text" placeholder="<?php esc_attr_e( 'اسم الدرس للمساعدة في التصنيف', 'examhub' ); ?>"></td>
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
        const $grade   = $('#pdf-grade');
        const $subject = $('#pdf-subject');

        resetSelect($grade, '-- اختر (اختياري)');
        resetSelect($subject, '-- اختر (اختياري)');
        if (!systemId) return;

        $grade.html('<option value="">جاري التحميل...</option>').prop('disabled', true);
        $.post(ajaxurl, {
          action: 'eh_admin_get_grades_by_system',
          nonce: getAdminNonce(),
          system_id: systemId
        }, function(res){
          if (res && res.success) {
            setSelectOptions($grade, res.data, '-- اختر (اختياري)');
          } else {
            resetSelect($grade, '-- لا يوجد صفوف --');
          }
        }).fail(function(){
          resetSelect($grade, '-- حدث خطأ --');
        });
      });

      $('#pdf-grade').on('change', function(){
        const gradeId  = $(this).val();
        const $subject = $('#pdf-subject');
        resetSelect($subject, '-- اختر (اختياري)');
        if (!gradeId) return;

        $subject.html('<option value="">جاري التحميل...</option>').prop('disabled', true);
        $.post(ajaxurl, {
          action: 'eh_admin_get_subjects_by_grade',
          nonce: getAdminNonce(),
          grade_id: gradeId
        }, function(res){
          if (res && res.success) {
            setSelectOptions($subject, res.data, '-- اختر (اختياري)');
          } else {
            resetSelect($subject, '-- لا يوجد مواد --');
          }
        }).fail(function(){
          resetSelect($subject, '-- حدث خطأ --');
        });
      });

      $('#eh-pdf-upload-form').on('submit', function(e){
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'eh_import_pdf');

        $('#pdf-progress').show();
        $('#eh-pdf-submit').prop('disabled', true);
        updateProgress(10, 'جاري رفع الملف...');

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          xhr: function(){
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e){
              if(e.lengthComputable){ updateProgress(Math.round(e.loaded/e.total*30)+10, 'جاري الرفع...'); }
            });
            return xhr;
          },
          success: function(res){
            if(res.success){
              updateProgress(100, 'تم الاستخراج!');
              renderExtractedQuestions(res.data.questions);
            } else {
              alert('خطأ: ' + (res.data?.message || 'حدث خطأ غير متوقع'));
              resetForm();
            }
          },
          error: function(){ alert('خطأ في الاتصال'); resetForm(); }
        });

        // Simulate AI processing progress
        let prog = 30;
        const interval = setInterval(function(){
          prog = Math.min(90, prog + Math.random()*5);
          const msgs = ['جاري معالجة النص...','يعمل الذكاء الاصطناعي...','استخراج الأسئلة...','تحليل الإجابات...'];
          updateProgress(prog, msgs[Math.floor(prog/25)]);
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
        let html = `<p><strong>${questions.length} سؤال تم استخراجه</strong> — حدد الأسئلة التي تريد حفظها:</p>`;
        html += '<label style="margin-bottom:10px;display:block;"><input type="checkbox" id="select-all-q"> تحديد الكل</label>';

        questions.forEach(function(q, i){
          const typeLabel = {mcq:'اختيار متعدد',true_false:'صح/خطأ',fill_blank:'اكمل الفراغ'}[q.type] || q.type;
          const diffLabel = {easy:'سهل',medium:'متوسط',hard:'صعب'}[q.difficulty] || q.difficulty;
          html += `
            <div class="eh-extracted-q" style="border:1px solid #ccc;border-radius:4px;padding:12px;margin:8px 0;background:#fff;">
              <label style="display:flex;align-items:flex-start;gap:10px;">
                <input type="checkbox" class="q-select" value="${i}" checked style="margin-top:3px;flex-shrink:0;">
                <div style="flex:1;">
                  <div style="font-weight:bold;margin-bottom:6px;">${q.question_text || ''}</div>
                  <span style="background:#e5e7eb;padding:2px 8px;border-radius:3px;font-size:12px;">${typeLabel}</span>
                  <span style="background:#fef3c7;padding:2px 8px;border-radius:3px;font-size:12px;margin-right:4px;">${diffLabel}</span>
                  ${q.answers ? `<div style="margin-top:6px;font-size:13px;">${q.answers.map((a,j)=>`<span style="margin-left:8px;">${a.is_correct?'✓':String.fromCharCode(0x200e)+''}${j+1}. ${a.answer_text||a}</span>`).join('')}</div>` : ''}
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
        const questions    = $(this).data('questions');
        const selected_idx = $('.q-select:checked').map((_,el) => parseInt(el.value)).get();
        const to_save      = selected_idx.map(i => questions[i]);

        if(!to_save.length){ alert('اختر أسئلة أولاً'); return; }

        $(this).prop('disabled', true).text('جاري الحفظ...');

        const extra = {
          grade_id:   $('#pdf-grade').val(),
          subject_id: $('#pdf-subject').val(),
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
            alert(`تم حفظ ${res.data.saved} سؤال بنجاح!`);
            window.location.href = '?page=examhub-review-questions';
          } else {
            alert('خطأ: ' + (res.data?.message || ''));
            $('#save-all-questions').prop('disabled', false).text('💾 حفظ الأسئلة المحددة');
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
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

    if ( empty( $_FILES['pdf_file'] ) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => __( 'خطأ في رفع الملف.', 'examhub' ) ] );
    }

    $file = $_FILES['pdf_file'];

    // Validate PDF
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime  = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );

    if ( $mime !== 'application/pdf' ) {
        wp_send_json_error( [ 'message' => __( 'الملف ليس PDF صالحاً.', 'examhub' ) ] );
    }

    if ( $file['size'] > 10 * MB_IN_BYTES ) {
        wp_send_json_error( [ 'message' => __( 'حجم الملف يتجاوز 10MB.', 'examhub' ) ] );
    }

    // Extract text from PDF
    $text = examhub_extract_pdf_text( $file['tmp_name'] );
    if ( is_wp_error( $text ) || empty( $text ) ) {
        $msg = is_wp_error( $text ) ? $text->get_error_message() : __( 'تعذر استخراج النص من الملف.', 'examhub' );
        wp_send_json_error( [ 'message' => $msg ] );
    }

    // Clean up OCR artifacts
    $text = examhub_clean_ocr_text( $text );

    // Context for AI
    $context = [
        'grade'   => sanitize_text_field( $_POST['lesson_hint'] ?? '' ),
        'subject' => '',
        'lesson'  => sanitize_text_field( $_POST['lesson_hint'] ?? '' ),
    ];

    $grade_id = (int) ( $_POST['grade_id'] ?? 0 );
    if ( $grade_id ) {
        $context['grade'] = get_the_title( $grade_id );
    }

    $subject_id = (int) ( $_POST['subject_id'] ?? 0 );
    if ( $subject_id ) {
        $context['subject'] = get_the_title( $subject_id );
    }

    // AI extraction
    $questions = examhub_ai_extract_questions( $text, $context );
    if ( is_wp_error( $questions ) ) {
        wp_send_json_error( [ 'message' => $questions->get_error_message() ] );
    }

    // Guardrail: reject hallucinated output that does not match extracted PDF text.
    $source_check = examhub_validate_questions_against_source_text( $questions, $text );
    if ( empty( $source_check['passed'] ) ) {
        wp_send_json_error( [
            'message' => __( 'تعذر مطابقة الأسئلة مع نص ملف PDF المرفوع. تأكد أن الملف يحتوي نصا واضحا (وليس صورا فقط) ثم حاول مرة أخرى.', 'examhub' ),
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
 * Extract text from PDF using pdftotext (if available) or fallback.
 */
function examhub_extract_pdf_text( $path ) {
    // 0) Preferred: send file to provider OCR API when enabled.
    if ( function_exists( 'examhub_ai_extract_pdf_text_remote' ) && get_field( 'ai_ocr_enabled', 'option' ) ) {
        $remote = examhub_ai_extract_pdf_text_remote( $path );
        if ( is_string( $remote ) && strlen( trim( $remote ) ) >= 40 ) {
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

add_action( 'wp_ajax_eh_admin_get_grades_by_system', 'examhub_ajax_admin_get_grades_by_system' );
function examhub_ajax_admin_get_grades_by_system() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [], 403 );
    }

    $system_id = (int) ( $_POST['system_id'] ?? 0 );
    if ( ! $system_id ) {
        wp_send_json_success( [] );
    }

    $grades = get_posts( [
        'post_type'      => 'eh_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'grade_education_system',
                'value'   => $system_id,
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
    if ( ! current_user_can( 'manage_options' ) ) {
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

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX: SAVE IMPORTED QUESTIONS
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_eh_save_imported_questions', 'examhub_ajax_save_imported_questions' );
function examhub_ajax_save_imported_questions() {
    check_ajax_referer( 'examhub_admin_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

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
        if ( ! empty( $extra['grade_id'] ) )         update_field( 'grade',            (int)$extra['grade_id'],         $post_id );
        if ( ! empty( $extra['subject_id'] ) )       update_field( 'subject',          (int)$extra['subject_id'],       $post_id );
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
