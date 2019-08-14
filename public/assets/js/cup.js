"use strict";

$(() => {
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
    
    $('[data-toggle="tooltip"]').tooltip()
    
    $('[data-input] select').select2({
        theme: 'bootstrap',
        width: '100%',
        minimumResultsForSearch: -1,
    });
    
    $('[data-table]').DataTable({
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
    
    // editor
    $('[data-summernote]').each((i, el) => {
        let $el = $(el);
        
        $el.summernote({
            codeviewFilter: false,
            codeviewIframeFilter: false,
            codeviewFilterRegex: '',
            lang: 'ru-RU',
            height: 350,
            placeholder: 'вводите текст здесь...',
            callbacks: {
                onInit: function (e) {
                    let $this = $(this);
                    
                    $this.summernote('code', 'Загрузка..');
                    
                    setTimeout(() => {
                        $this.summernote('code', $this.data('value') || '');
                        
                        setInterval(() => {
                            $this.val($this.summernote('code'));
                        }, 250);
                    }, 500);
                },
            },
            codemirror: {
                theme: 'monokai',
                lineNumbers: true,
            }
        });
        
        setTimeout(() => {
            if ($el.data('summernote-code') === '') {
                $el.next().find('.btn-codeview').click();
            }
            
            if ($el.data('summernote-toolbar-disable') === '') {
                $el.next().find('.note-toolbar').remove();
            }
            
            // kostil.js
            $('html, body').animate({scrollTop: 0}, 'fast').find(':focus').blur();
        }, 50);
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
    })
})
