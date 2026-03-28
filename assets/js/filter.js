/**
 * ExamHub - Filter & Hierarchy JS
 * Cascade sidebar filters, AJAX exam loading, and URL state management.
 */
(function($) {
  'use strict';

  const state = {
    system_id:  window.examhubFilterConfig?.initial_system  || 0,
    stage_id:   window.examhubFilterConfig?.initial_stage   || 0,
    grade_id:   window.examhubFilterConfig?.initial_grade   || 0,
    subject_id: window.examhubFilterConfig?.initial_subject || 0,
    difficulty: window.examhubFilterConfig?.initial_diff    || '',
    paged: 1,
    loading: false,
    search: '',
    sort: 'date_desc',
  };

  let searchTimer;

  $(function() {
    if (!$('#exam-grid').length) return;

    // Restore values from query string.
    const params = new URLSearchParams(window.location.search);
    ['system_id', 'stage_id', 'grade_id', 'subject_id', 'difficulty'].forEach(k => {
      const v = params.get(k.replace('_id', ''));
      if (v) state[k] = parseInt(v, 10) || v;
    });

    bindSystemButtons();
    bindCascadeSelects();
    bindDifficultyButtons();
    bindSearch();
    bindSort();
    bindClearFilters();

    restoreInitialState();
  });

  function bindSystemButtons() {
    $(document).on('click', '.eh-system-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const id = parseInt($(this).data('id'), 10);
      const isActive = $(this).hasClass('active');

      if (isActive) {
        resetFrom('system');
        $('.eh-system-btn').removeClass('active').removeAttr('style');
        fetchExams();
        return;
      }

      $('.eh-system-btn').removeClass('active').removeAttr('style');
      $(this).addClass('active');

      const color = $(this).find('i').css('color');
      if (color) {
        $(this).css({
          borderColor: color,
          color: color,
        });
      }

      state.system_id = id;
      state.stage_id = 0;
      state.grade_id = 0;
      state.subject_id = 0;
      state.paged = 1;

      hideCards(['#stage-card', '#grade-card', '#subject-card']);
      $('#sel-stage, #sel-grade').val('');
      $('#subject-chips').empty();

      loadStages(id).then(() => fetchExams());
    });
  }

  function bindCascadeSelects() {
    $('#sel-stage').on('change', function() {
      const id = parseInt($(this).val(), 10) || 0;
      state.stage_id = id;
      state.grade_id = 0;
      state.subject_id = 0;
      state.paged = 1;

      hideCards(['#grade-card', '#subject-card']);
      $('#sel-grade').val('');
      $('#subject-chips').empty();

      if (id) {
        loadGrades(id).then(() => fetchExams());
      } else {
        fetchExams();
      }
    });

    $('#sel-grade').on('change', function() {
      const id = parseInt($(this).val(), 10) || 0;
      state.grade_id = id;
      state.subject_id = 0;
      state.paged = 1;

      hideCards(['#subject-card']);
      $('#subject-chips').empty();

      if (id) {
        loadSubjects(id).then(() => fetchExams());
      } else {
        fetchExams();
      }
    });
  }

  function bindDifficultyButtons() {
    $(document).on('click', '.eh-diff-btn', function() {
      const diff = $(this).data('diff');

      if (state.difficulty === diff) {
        state.difficulty = '';
        $(this).removeClass('active');
      } else {
        state.difficulty = diff;
        $('.eh-diff-btn').removeClass('active');
        $(this).addClass('active');
      }

      state.paged = 1;
      fetchExams();
    });
  }

  function bindSearch() {
    $('#eh-exam-search').on('input', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        state.search = $(this).val() || '';
        state.paged = 1;
        fetchExams();
      }, 350);
    });
  }

  function bindSort() {
    $('#sel-sort').on('change', function() {
      state.sort = $(this).val() || 'date_desc';
      state.paged = 1;
      fetchExams();
    });
  }

  function bindClearFilters() {
    $('#btn-clear-filters').on('click', function() {
      resetFrom('system');
      $('.eh-system-btn').removeClass('active').removeAttr('style');
      $('.eh-diff-btn').removeClass('active');
      $('#eh-exam-search').val('');
      $('#sel-stage, #sel-grade').val('');
      $('#subject-chips').empty();
      fetchExams();
    });
  }

  function loadStages(systemId) {
    return ajaxGet('eh_get_stages', { system_id: systemId }).then(items => {
      populateSelect('#sel-stage', items, 'اختر المرحلة');
      if (items.length) showCard('#stage-card');
      return items;
    });
  }

  function loadGrades(stageId) {
    return ajaxGet('eh_get_grades', { stage_id: stageId }).then(items => {
      populateSelect('#sel-grade', items, 'اختر الصف');
      if (items.length) showCard('#grade-card');
      return items;
    });
  }

  function loadSubjects(gradeId) {
    return ajaxGet('eh_get_subjects', { grade_id: gradeId }).then(items => {
      renderSubjectChips(items);
      if (items.length) showCard('#subject-card');
      return items;
    });
  }

  function fetchExams() {
    if (state.loading) return;
    state.loading = true;

    $('#exam-grid').hide();
    $('#exam-grid-loading').show();

    $.ajax({
      url: window.examhubAjax.url,
      type: 'POST',
      data: {
        action: 'eh_filter_exams',
        nonce: window.examhubAjax.nonce,
        system_id: state.system_id,
        stage_id: state.stage_id,
        grade_id: state.grade_id,
        subject_id: state.subject_id,
        difficulty: state.difficulty,
        search: state.search || '',
        sort: state.sort || 'date_desc',
        paged: state.paged,
      },
      success(res) {
        if (res.success) {
          $('#exam-grid').html(res.data.html).show();
          updateResultsCount(res.data.found);
          renderPagination(res.data.max_pages, res.data.paged);
          updateFilterBreadcrumb();
          updateURL();
        }
      },
      error() {
        $('#exam-grid').show();
        showToast('حدث خطأ في تحميل النتائج.', 'danger');
      },
      complete() {
        state.loading = false;
        $('#exam-grid-loading').hide();
      }
    });
  }

  function renderSubjectChips(subjects) {
    const $cont = $('#subject-chips').empty();

    subjects.forEach(s => {
      const $chip = $(
        `<button class="btn badge" data-id="${s.id}" style="background:${s.color}20; color:${s.color}; border:1px solid ${s.color}40;">${escHtml(s.label)}</button>`
      );

      $chip.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (state.subject_id === s.id) {
          state.subject_id = 0;
          $(this).removeClass('active').css({ borderWidth: '1px', fontWeight: '600' });
        } else {
          state.subject_id = s.id;

          $('#subject-chips .badge').removeClass('active').css({ borderWidth: '1px', fontWeight: '600' });
          $(this).addClass('active').css({ borderWidth: '2px', fontWeight: '700' });
        }

        state.paged = 1;
        fetchExams();
      });

      $cont.append($chip);
    });
  }

  function renderPagination(maxPages, currentPage) {
    if (maxPages <= 1) {
      $('#exam-pagination').html('');
      return;
    }

    let html = '<nav><ul class="pagination pagination-sm justify-content-center">';

    for (let p = 1; p <= maxPages; p += 1) {
      html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
        <button class="page-link eh-page-btn"
          style="background:${p === currentPage ? 'var(--eh-accent)' : 'var(--eh-bg-tertiary)'}; border-color:var(--eh-border); color:${p === currentPage ? '#fff' : 'var(--eh-text-secondary)'};"
          data-page="${p}">${p}</button>
      </li>`;
    }

    html += '</ul></nav>';
    $('#exam-pagination').html(html);

    $(document)
      .off('click', '.eh-page-btn')
      .on('click', '.eh-page-btn', function() {
        state.paged = parseInt($(this).data('page'), 10) || 1;
        fetchExams();
        $('html, body').animate({ scrollTop: $('#exam-grid').offset().top - 80 }, 250);
      });
  }

  function populateSelect(selector, items, placeholder) {
    const $sel = $(selector).empty().append(`<option value="">${placeholder}</option>`);
    items.forEach(item => {
      $sel.append(`<option value="${item.id}">${escHtml(item.label)}</option>`);
    });
  }

  function showCard(selector) {
    $(selector).stop(true, true).fadeIn(120);
  }

  function hideCards(selectors) {
    selectors.forEach(s => $(s).hide());
  }

  function resetFrom(level) {
    const levels = ['system', 'stage', 'grade', 'subject'];
    const idx = levels.indexOf(level);

    levels.slice(idx).forEach(l => {
      state[`${l}_id`] = 0;
    });

    state.difficulty = '';
    state.paged = 1;
    hideCards(['#stage-card', '#grade-card', '#subject-card']);
  }

  function restoreInitialState() {
    if (!state.system_id) {
      fetchExams();
      return;
    }

    const $system = $(`.eh-system-btn[data-id="${state.system_id}"]`);
    if ($system.length) {
      $('.eh-system-btn').removeClass('active').removeAttr('style');
      $system.addClass('active');
      const color = $system.find('i').css('color');
      if (color) $system.css({ borderColor: color, color });
    }

    loadStages(state.system_id)
      .then(stages => {
        if (!stages.length || !state.stage_id) return null;
        $('#sel-stage').val(String(state.stage_id));
        return loadGrades(state.stage_id);
      })
      .then(grades => {
        if (!grades || !grades.length || !state.grade_id) return null;
        $('#sel-grade').val(String(state.grade_id));
        return loadSubjects(state.grade_id);
      })
      .then(() => {
        if (!state.subject_id) {
          fetchExams();
          return;
        }

        const $chip = $(`#subject-chips [data-id="${state.subject_id}"]`);
        if ($chip.length) {
          $chip.trigger('click');
        } else {
          fetchExams();
        }
      })
      .catch(() => fetchExams());
  }

  function updateResultsCount(count) {
    $('#results-count').text(count > 0 ? `${count.toLocaleString('ar-EG')} امتحان` : 'لا توجد نتائج');
  }

  function updateFilterBreadcrumb() {
    const $bc = $('#filter-breadcrumb');
    $bc.empty();

    if (!state.system_id && !state.grade_id && !state.subject_id && !state.difficulty) {
      $bc.hide();
      return;
    }

    $bc.show();
  }

  function updateURL() {
    const params = new URLSearchParams();

    if (state.system_id) params.set('system', state.system_id);
    if (state.stage_id) params.set('stage', state.stage_id);
    if (state.grade_id) params.set('grade', state.grade_id);
    if (state.subject_id) params.set('subject', state.subject_id);
    if (state.difficulty) params.set('difficulty', state.difficulty);

    const newUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
    history.replaceState(null, '', newUrl);
  }

  function ajaxGet(action, data) {
    return $.ajax({
      url: window.examhubAjax.url,
      type: 'POST',
      data: Object.assign({ action, nonce: window.examhubAjax.nonce }, data),
    }).then(res => (res.success ? res.data : []));
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.showToast = function(msg, type = 'success') {
    const $t = $(`
      <div class="alert alert-${type} position-fixed shadow-lg" role="alert"
        style="bottom:80px; left:50%; transform:translateX(-50%); z-index:9999; min-width:260px; text-align:center; animation: fadeIn .2s ease;">
        ${msg}
      </div>
    `);

    $('body').append($t);
    setTimeout(() => $t.fadeOut(300, () => $t.remove()), 3000);
  };

})(jQuery);
