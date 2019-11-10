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
            minimumResultsForSearch: $el.data('data-search') ? $el.data('search') : -1,
            allowClear: $el.data('allow-clear') ? $el.data('allow-clear') : false,
        });
    });
    
    $('[data-table]').DataTable({
        'stateSave': true,
        'language': {
            'search': 'Поиск:',
            'lengthMenu': 'Отображать _MENU_ строк на страницу',
            'zeroRecords': 'Нет результатов',
            'info': 'Страница _PAGE_ из _PAGES_',
            'infoEmpty': 'Нет записей',
            'infoFiltered': '(проверено в _MAX_ результатах)',
            'paginate': {
                'first':      'В начало',
                'previous':   'Сюда',
                'next':       'Туда',
                'last':       'В конец'
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
    
    // notification function
    $.show_notify = (title, message = '', type = 'info', url = '') => {
        $.notify({
            title,
            message,
            url,
            target: '_blank'
        }, {
            type,
            animate: {
                enter: 'animated fadeInRight',
                exit: 'animated fadeOutRight'
            }
        });
    };
});
