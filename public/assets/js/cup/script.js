"use strict";

$(() => {
    // toolbar
    let topbar_open = 0,
        topbar = $('.topbar-toggler');
    
    topbar.on('click', function() {
        if (topbar_open === 1) {
            $('html').removeClass('topbar_open');
            topbar.removeClass('toggled');
            topbar_open = 0;
        } else {
            $('html').addClass('topbar_open');
            topbar.addClass('toggled');
            topbar_open = 1;
        }
    });
    
    // sidenav
    let nav_open = 0,
        nav_el = $('.sidenav-toggler');
    nav_el.on('click', function(){
        if (nav_open === 1){
            $('html').removeClass('nav_open');
            nav_el.removeClass('toggled');
            nav_open = 0;
        }  else {
            $('html').addClass('nav_open');
            nav_el.addClass('toggled');
            nav_open = 1;
        }
    });
    
    // sidebar
    $('body').on('click', '.quick-sidebar-toggler, .close-quick-sidebar, .quick-sidebar-overlay', (e) => {
        $(e.currentTarget).toggleClass('toggled');
        $('html').toggleClass('quick_sidebar_open');
    
        let $el;
        if (($el = $('.quick-sidebar-overlay')) && $el.length) {
            $el.remove();
        } else {
            $('<div class="quick-sidebar-overlay"></div>').insertAfter('.quick-sidebar');
        }
    });
    
    // scrollbars
    $('.sidebar .scrollbar').scrollbar();
    $('.main-panel .content-scroll').scrollbar();
    $('.quick-scroll').scrollbar();
    $('.quick-actions-scroll').scrollbar();
    
    // navigation highlight
    let buf = 0, $active = null;
    $('.sidebar a').each((i, el) => {
        let $el = $(el), href = $el.attr('href');
        
        if (location.pathname.startsWith(href) && href.length > buf) {
            buf = href.length;
            $active = $el;
        }
    });
    $active.parents('li').addClass('active').parents('.nav-item').find('[href^="#"]').click();
    
    $('[data-toggle="tooltip"]').tooltip();
    
    // select2 init
    $('[data-input] select').each((i, el) => {
        let $el = $(el);
        
        $el.select2({
            theme: 'bootstrap',
            width: '100%',
            placeholder: $el.data('placeholder') ? $el.data('placeholder') : null,
            minimumResultsForSearch: $el.data('search') ? $el.data('search') : -1,
            allowClear: $el.data('allow-clear') ? $el.data('allow-clear') : false,
        });
    });
    
    $('[data-table]').DataTable({
        'deferRender': true,
        'stateSave': true,
        'language': {
            'search': 'Поиск:',
            'lengthMenu': 'Отображать _MENU_ строк на страницу',
            'zeroRecords': 'Нет результатов',
            'info': 'Страница _PAGE_ из _PAGES_',
            'infoEmpty': 'Нет записей',
            'infoFiltered': '(проверено в _MAX_ результатах)',
            'paginate': {
                'first': 'В начало',
                'previous': 'Сюда',
                'next': 'Туда',
                'last': 'В конец'
            },
        }
    });
    
    // publication preview
    $('form [data-click="preview"]').on('click', function (e) {
        e.preventDefault();
    
        let $form = $(e.currentTarget).parents('form'),
            preview = window.open('/cup/publication/preview', 'prv', 'height=400,width=750,left=0,top=0,resizable=1,scrollbars=1');
    
        $form.attr('action', '/cup/publication/preview');
        $form.attr('target', 'prv');
        $form.submit();
    
        preview.focus();
        
        setTimeout(() => {
            $form.attr('action', '');
            $form.attr('target', '_self');
        }, 500);
    });
    
    moment.locale('ru');
    
    // modal window settings
    $.modal.defaults = {
        closeExisting: true,      // Close existing modals. Set this to false if you need to stack multiple modal instances.
        escapeClose: true,        // Allows the user to close the modal by pressing `ESC`
        clickClose: true,         // Allows the user to close the modal by clicking the overlay
        closeText: 'Close',       // Text content for the close <a> tag.
        closeClass: '',           // Add additional class(es) to the close <a> tag.
        showClose: false,         // Shows a (X) icon/link in the top-right corner
        modalClass: 'modal',      // CSS class added to the element being displayed in the modal.
        blockerClass: 'blocker',  // CSS class added to the overlay (blocker).
        spinnerHtml: '<div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div>',
        showSpinner: true,        // Enable/disable the default spinner during AJAX requests.
        fadeDuration: null,       // Number of milliseconds the fade transition takes (null means no transition)
        fadeDelay: 1.0            // Point during the overlay's fade-in that the modal begins to fade in (.5 = 50%, 1.5 = 150%, etc.)
    };
    
    // parameters guest user && user group
    {
        let $select = $('select[name="access[]"], [name="user[access][]"]'),
            $options = $select.find('option');
    
        $('[data-access-click]').on('click', (e) => {
            let $btn = $(e.currentTarget),
                type = $btn.attr('data-access-click');
        
            if (type === 'none') {
                $options.prop('selected', false);
            } else {
                $options.each((i, el) => {
                    let $buf = $(el);
                
                    if ($buf.val().startsWith(type)) {
                        $buf.prop('selected', !(+$buf.prop('selected')));
                    }
                });
            }
        
            $select.trigger('change.select2');
        });
    }
    
    // parameters add variables
    {
        let $hidden = $('[name="var[]"]').parents('[data-input]'),
            $value = $('[data-input="variable"]');
        
        $('[data-click="add_variable"]').on('click', () => {
            let $clone = $hidden.clone();
            
            if (!$value.val() || $('[name="var[' + $value.val() + ']"]').length) {
                $value.parents('.form-group').addClass('has-error');
                setTimeout(() => {
                    $value.parents('.form-group').removeClass('has-error');
                }, 2500);
            } else {
                $clone.find('div:first').text('var_' + $value.val());
                $clone.find('input')
                    .attr('type', 'text')
                    .attr('name', 'var[' + $value.val() + ']');
                
                $clone.insertBefore($hidden);
                $value.val('');
            }
        });
    }
});
