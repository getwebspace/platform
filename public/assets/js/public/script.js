/*[ Polyfills ]
===========================================================*/
// default empty query list
location.query = {};

// build query params
window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, (m, key, value) => {
    location.query[key] = value;
});

/**
 * Функция возвращает окончание для множественного числа слова на основании числа и массива окончаний
 *
 * @param one string например яблоко
 * @param two string напримет яблока
 * @param five string например яблок
 *
 * @return string
 */
String.prototype.eos = Number.prototype.eos = function (one, two, five) {
    if (!isNaN(+this)) {
        let value = this % 100;
        
        if (value >= 11 && value <= 19) {
            return five;
        } else {
            value = this % 10;
            
            switch (value) {
                case (1):
                    return one;
                case (2):
                case (3):
                case (4):
                    return two;
                default:
                    return five;
            }
        }
    }
    
    return this;
};

/**
 * Сравнение объектов
 *
 * @param x
 * @param y
 * @returns {boolean}
 */
Object.equals = function (x, y) {
    if (x === y) {
        return true;
    }
    if (!(x instanceof Object) || !(y instanceof Object)) {
        return false;
    }
    if (x.constructor !== y.constructor) {
        return false;
    }
    
    for (var p in x) {
        if (!x.hasOwnProperty(p)) {
            continue;
        }
        if (!y.hasOwnProperty(p)) {
            return false;
        }
        if (x[p] === y[p]) {
            continue;
        }
        if (typeof (x[p]) !== "object") {
            return false;
        }
        if (!Object.equals(x[p], y[p])) {
            return false;
        }
    }
    for (p in y) {
        if (y.hasOwnProperty(p) && !x.hasOwnProperty(p)) {
            return false;
        }
    }
    
    return true;
};

/*[ Script ]
    ===========================================================*/
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
