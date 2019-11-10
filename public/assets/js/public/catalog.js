(($) => {
    simpleCart({
        checkout: {type: 'SendForm', url: '/cart', extra_data: {}},
        cartColumns: [
            {label: false, attr: 'thumb', view: (item) => { return '<img src="' + item.get('thumb') + '">'; },},
            {label: 'Название', attr: 'link', view: (item) => { return "<a href='" + item.get('link') + "'>" + item.get('name') + "</a>"; }},
            {label: 'Цена', attr: "price", view: 'currency'},
            {label: false, view: () => '<a href="javascript:;" class="site-btn site-btn-small simpleCart_decrement">-</a>'},
            {label: 'Кол-во', attr: 'quantity'},
            {label: false, view: () => '<a href="javascript:;" class="site-btn site-btn-small simpleCart_increment">+</a>'},
            {label: 'Итого', attr: "total", view: 'currency'},
            {label: false, view: 'remove', text: 'Убрать', }
        ],
        cartStyle: 'table'
    });
    
    simpleCart.currency({
        code: 'RU',
        symbol: ' ₽',
        name: 'Рубли',
        delimiter: ' ',
        decimal: '.',
        after: true,
        accuracy: 0
    });
    
    simpleCart.bind('afterAdd', function (item) {
        Swal.fire(
            'Корзина обновлена',
            item.get('name') + ' (x' + item.get('quantity') + ') теперь в корзине.',
            'success'
        );
    });
    
    simpleCart.bind('ready', function () {
        let isEmpty = simpleCart.items().length === 0;
        
        if (location.pathname === '/cart') {
            $('[data-cart-empty]').toggle(isEmpty);
            $('[data-cart-not-empty]').toggle(!isEmpty);
        }
    });
    
    // обработка отправки корзины
    simpleCart.bind('beforeCheckout', function (data) {
        let errors = false;
        
        $('[data-cart-not-empty]')
            .find('input[name], textarea[name], select[name]')
            .each((i, el) => {
                let $el = $(el);
                
                if ($el.attr('required') && !$el.val()) {
                    $el
                        .one('change', () => {
                            $el.removeAttr('style');
                        })
                        .css({'border': '1px solid #f51167'});
                    
                    console.error('Field required selector [name="' + $el.attr('name') + '"]');
                    errors = true;
                }
                
                data[$el.attr('name')] = $el.val();
            });
        
        if (errors) {
            throw new Error('Required fields have errors');
        }
    });
})(jQuery);
