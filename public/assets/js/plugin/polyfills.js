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
